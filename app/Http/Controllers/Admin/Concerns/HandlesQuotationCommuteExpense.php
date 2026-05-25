<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\ClientProject;
use App\Services\CommuteExpenseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait HandlesQuotationCommuteExpense
{
    protected function resolveCommuteProject(Request $request, ?object $existingQuotation = null): ?ClientProject
    {
        $project = $this->quotationLinkCandidateProject($request);
        if ($project) {
            return $project;
        }

        $formId = $request->input('client_project_id');
        if (is_numeric($formId) && (int) $formId > 0) {
            $fromForm = ClientProject::query()->find((int) $formId);
            if ($fromForm) {
                return $fromForm;
            }
        }

        if ($existingQuotation && ! empty($existingQuotation->client_project_id)) {
            return ClientProject::query()->find((int) $existingQuotation->client_project_id);
        }

        return null;
    }

    /**
     * @return array{
     *   include_commute: bool,
     *   commute_trip_percent: float,
     *   commute_estimated_trips: int,
     *   commute_round_trip_cost: ?float,
     *   commute_amount: float
     * }
     */
    protected function commutePersistenceFromRequest(
        Request $request,
        ?object $existingQuotation = null,
        ?string $table = null
    ): array {
        $table = $table ?? ($existingQuotation instanceof Model ? $existingQuotation->getTable() : null);
        if ($table === null || ! Schema::hasColumn($table, 'include_commute')) {
            return [];
        }

        $project = $this->resolveCommuteProject($request, $existingQuotation);

        return app(CommuteExpenseService::class)->persistenceFromRequest(
            $request,
            $project,
            $existingQuotation === null
        );
    }

    /**
     * @param  array<string, mixed>  $persistence
     * @return array<string, mixed>
     */
    protected function mergeCommuteIntoPersistence(array $persistence, array $commute, string $totalKey): array
    {
        if ($commute === []) {
            return $persistence;
        }

        $base = (float) ($persistence[$totalKey] ?? 0);
        $commuteAmount = (float) ($commute['commute_amount'] ?? 0);

        return array_merge($persistence, $commute, [
            $totalKey => round($base + $commuteAmount, 2),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function appendCommuteProjectLine(array &$items, object $quotation): void
    {
        if (empty($quotation->include_commute)) {
            return;
        }

        $line = CommuteExpenseService::projectLineItem(
            (float) ($quotation->commute_amount ?? 0),
            (int) ($quotation->commute_estimated_trips ?? 1)
        );

        if ($line !== null) {
            $items[] = $line;
        }
    }
}
