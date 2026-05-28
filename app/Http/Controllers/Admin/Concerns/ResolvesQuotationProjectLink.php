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

}
