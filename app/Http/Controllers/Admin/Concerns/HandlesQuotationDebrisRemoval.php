<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\DebrisRemovalExpenseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait HandlesQuotationDebrisRemoval
{
    /**
     * @return array{include_debris_removal: bool, debris_removal_amount: float}|array{}
     */
    protected function debrisRemovalPersistenceFromRequest(
        Request $request,
        ?object $existingQuotation = null,
        ?string $table = null
    ): array {
        $table = $table ?? ($existingQuotation instanceof Model ? $existingQuotation->getTable() : null);
        if ($table === null || ! Schema::hasColumn($table, 'include_debris_removal')) {
            return [];
        }

        return app(DebrisRemovalExpenseService::class)->persistenceFromRequest($request);
    }

    /**
     * @param  array<string, mixed>  $persistence
     * @param  array<string, mixed>  $debris
     * @return array<string, mixed>
     */
    protected function mergeDebrisRemovalIntoPersistence(array $persistence, array $debris, string $totalKey): array
    {
        if ($debris === []) {
            return $persistence;
        }

        $base = (float) ($persistence[$totalKey] ?? 0);
        $amount = (float) ($debris['debris_removal_amount'] ?? 0);

        return array_merge($persistence, $debris, [
            $totalKey => round($base + $amount, 2),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function appendDebrisRemovalProjectLine(array &$items, object $quotation): void
    {
        if (empty($quotation->include_debris_removal)) {
            return;
        }

        $line = DebrisRemovalExpenseService::projectLineItem(
            (float) ($quotation->debris_removal_amount ?? 0)
        );

        if ($line !== null) {
            $items[] = $line;
        }
    }
}
