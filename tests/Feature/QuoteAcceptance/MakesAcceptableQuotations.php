<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;

trait MakesAcceptableQuotations
{
    /**
     * @param  array<string, mixed>  $quoteOverrides
     * @param  array<string, mixed>  $projectOverrides
     * @return array{0: ClientProject, 1: ClientProjectQuotation, 2: StandardQuotation}
     */
    protected function makeLinkedQuotation(array $quoteOverrides = [], array $projectOverrides = []): array
    {
        $project = ClientProject::factory()->create(array_merge([
            'name' => 'Kitchen renovation',
            'client_name' => 'Ada Client',
            'client_email' => 'ada@example.test',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
        ], $projectOverrides));

        $quoteNumber = (string) ($quoteOverrides['quote_number'] ?? 'SQ-2026-0001');
        $ask = array_key_exists('ask_reply_to_accept', $quoteOverrides)
            ? $quoteOverrides['ask_reply_to_accept']
            : 1;

        $standard = StandardQuotation::query()->create([
            'quote_number' => $quoteNumber,
            'client_name' => $project->client_name,
            'display_title' => 'Kitchen work',
            'ask_reply_to_accept' => $ask,
            'materials' => [],
            'grand_total' => 1130,
        ]);

        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Standard quotation',
            'status' => $quoteOverrides['status'] ?? ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => $quoteOverrides['quotation_number'] ?? 'QUO-2026-0150',
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $standard->id,
            'source_snapshot' => ['quote_number' => $quoteNumber],
            'ask_reply_to_accept' => $ask,
            'items' => [
                [
                    'description' => 'Labour and materials',
                    'amount' => 1000,
                    'apply_hst' => true,
                ],
            ],
        ]);

        return [$project, $quotation, $standard];
    }
}
