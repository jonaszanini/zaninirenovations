<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClientProject;
use App\Services\ClientProjectContactService;
use App\Services\ClientProjectStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait ResolvesQuotationProjectLink
{
    /**
     * Strip a "create project" phone field down to digits before validation
     * (`digits:10` rejects formatted input like "(123) 456-7890" otherwise).
     * Was copy-pasted per-controller under names like
     * normaliseFenceProjectPhoneOnRequest; every quotation type needs the
     * exact same normalisation.
     */
    protected function normalisePhoneOnRequest(Request $request, string $field = 'fp_client_phone'): void
    {
        if (! $request->has($field)) {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) $request->input($field));

        $request->merge([
            $field => $digits === '' ? null : $digits,
        ]);
    }

    /** Guard for the top of linkToProject/createProjectAndLink: bail out if already linked. */
    protected function guardQuotationAlreadyLinked(object $quotation, string $label): ?RedirectResponse
    {
        if ($quotation->isLinked()) {
            return back()->with('error', "This {$label} quotation is already linked to a project.");
        }

        return null;
    }

    protected function quotationIsDraft(object $quotation): bool
    {
        return (bool) ($quotation->is_draft ?? false);
    }

    /** Drafts must stay off the project's client quotation (link / create-project). */
    protected function guardQuotationIsDraft(object $quotation, string $action = 'link'): ?RedirectResponse
    {
        if (! $this->quotationIsDraft($quotation)) {
            return null;
        }

        $message = $action === 'create-project'
            ? 'Publish the quotation before creating a project.'
            : 'Publish the quotation (not Save as draft) before linking.';

        return back()->with('error', $message);
    }

    /** Guard used before linking to an existing project: its status must still accept new quotations. */
    protected function guardProjectClosedToNewQuotations(ClientProject $project): ?RedirectResponse
    {
        if (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
            return back()->with('error', 'Cannot link to this project — it no longer accepts new quotations.');
        }

        return null;
    }

    /** "New project" title: use what was typed, else "{client} — {suffix}", capped to the column length. */
    protected function resolveNewProjectTitle(?string $requestedTitle, string $clientName, string $suffix): string
    {
        $title = trim((string) $requestedTitle);
        if ($title === '') {
            $title = $clientName.' — '.$suffix;
        }

        return Str::limit($title, 255, '');
    }

    /**
     * Shared tail of createProjectAndLink once the project row exists:
     * apply status-change side effects and make sure it's linked into the
     * client directory (Client model), same as every other project entry point.
     */
    protected function finalizeCreatedProjectFromQuotation(ClientProject $project, ClientProjectStatusService $statusService): void
    {
        $statusService->onCreated($project, false);
        app(ClientProjectContactService::class)->ensureProjectLinkedToDirectory($project->fresh());
    }
    /** Optional FK from query string (?project=) or POST body (`link_client_project_id`). */
    protected function quotationLinkProjectRawId(Request $request): ?int
    {
        $raw = $request->input('link_client_project_id', $request->query('project'));
        if ($raw === null || $raw === '') {
            return null;
        }

        return max(0, (int) $raw) ?: null;
    }

    protected function quotationLinkCandidateProject(Request $request): ?ClientProject
    {
        $id = $this->quotationLinkProjectRawId($request);
        if (!$id || $id < 1) {
            return null;
        }

        return ClientProject::query()->find($id);
    }

    /**
     * Prefer ?project= / posted link id; otherwise the Client Project attached
     * to a Room Area layout being imported into this wizard.
     *
     * @param  array{client_project?: ClientProject|null}|null  $roomAreaImport
     */
    protected function mergeRoomAreaLinkProject(?ClientProject $linkProject, ?array $roomAreaImport): ?ClientProject
    {
        if (! $linkProject) {
            $fromRoom = $roomAreaImport['client_project'] ?? null;
            $linkProject = $fromRoom instanceof ClientProject ? $fromRoom : null;
        }

        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            return null;
        }

        return $linkProject;
    }

    /** True when linking is blocked for this project's status (soft-fail returns false). */
    protected function quotationProjectAllowsNewLink(ClientProject $project): bool
    {
        return ! in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true);
    }

    /** When opening a quotation list from a project (?project=), only show unlinked quotes. */
    protected function quotationIndexLinkProject(Request $request): ?ClientProject
    {
        $project = $this->quotationLinkCandidateProject($request);
        if (! $project || ! $this->quotationProjectAllowsNewLink($project)) {
            return null;
        }

        return $project;
    }

    protected function quotationIndexFiltersUnlinkedOnly(Request $request): bool
    {
        return $this->quotationIndexLinkProject($request) !== null;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder  $query */
    protected function applyUnlinkedOnlyQuotationIndexFilter($query, Request $request)
    {
        if ($this->quotationIndexFiltersUnlinkedOnly($request)) {
            $query->whereNull('client_project_id');
        }

        return $query;
    }

    /** @return array<string, mixed> */
    protected function quotationLinkClientNameAttributes(ClientProject $project): array
    {
        $name = trim((string) $project->client_name);

        return $name !== '' ? ['client_name' => $name] : [];
    }

    /**
     * Before linking, copy the project's client name onto the quotation and
     * auto-publish calculator quotes that already have a total (common when
     * client name was filled via the link picker but not saved on the main form).
     */
    protected function prepareQuotationForProjectLink(object $quotation, ClientProject $project): void
    {
        $updates = $this->quotationLinkClientNameAttributes($project);

        if (($quotation->is_draft ?? false)) {
            $clientName = $updates['client_name'] ?? trim((string) ($quotation->client_name ?? ''));
            if (
                $clientName !== ''
                && $this->quotationLinkGrandTotal($quotation) > 0
                && $this->quotationHasPublishOnlyChoices($quotation)
            ) {
                $updates['is_draft'] = false;
            }
        }

        if (method_exists($this, 'refreshLinkedCommuteExpense')) {
            $this->refreshLinkedCommuteExpense($quotation, $project);
        }

        if ($updates === [] && ! $quotation->isDirty()) {
            return;
        }

        foreach ($updates as $key => $value) {
            $quotation->{$key} = $value;
        }
        $quotation->save();
        $quotation->refresh();
    }

    protected function quotationHasPublishOnlyChoices(object $quotation): bool
    {
        $payment = $quotation->getRawOriginal('payment_installments');
        if ($payment === null || $payment === '') {
            return false;
        }

        $debris = $quotation->getRawOriginal('include_debris_removal');
        if ($debris === null || $debris === '') {
            return false;
        }

        $accept = $quotation->getRawOriginal(\App\Support\QuotationReplyToAccept::FIELD);
        if ($accept === null || $accept === '') {
            return false;
        }

        return true;
    }

    protected function quotationLinkGrandTotal(object $quotation): float
    {
        if (isset($quotation->final_quote_total)) {
            return (float) $quotation->final_quote_total;
        }

        if (isset($quotation->grand_total)) {
            return (float) $quotation->grand_total;
        }

        return 0.0;
    }

}
