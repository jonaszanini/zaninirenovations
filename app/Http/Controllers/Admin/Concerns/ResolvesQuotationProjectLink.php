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
        return ! in_array($project->status, [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED], true);
    }

}
