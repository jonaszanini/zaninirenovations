<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClientProject;
use Illuminate\Http\Request;

trait ResolvesQuotationProjectLink
{
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
            if ($clientName !== '' && $this->quotationLinkGrandTotal($quotation) > 0) {
                $updates['is_draft'] = false;
            }
        }

        if ($updates === []) {
            return;
        }

        $quotation->update($updates);
        $quotation->refresh();
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
