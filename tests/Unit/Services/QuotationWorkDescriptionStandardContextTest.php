<?php

namespace Tests\Unit\Services;

use App\Services\QuotationWorkDescriptionContextBuilder;
use Tests\TestCase;

class QuotationWorkDescriptionStandardContextTest extends TestCase
{
    public function test_standard_context_sends_names_per_job_without_prices_or_totals(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('standard', [
            'client_name' => 'Jonas',
            'display_title' => 'Kitchen refresh',
            'document_type' => 'quotation',
            'pdf_detail_mode' => 'combined',
            'includes_material' => true,
            'includes_labour' => true,
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'labour_hourly_rate' => '65.00',
            'labour_fixed_amount' => '1130',
            'totals_summary' => 'Materials w/ HST: CA$565.00; Grand total: CA$1,678.00',
            'jobs' => [
                [
                    'title' => 'Kitchen',
                    'labour_use_fixed' => false,
                    'labour_mode' => 'days',
                    'labour_hourly_rate' => '65',
                    'materials' => [
                        ['short_name' => 'Plywood', 'description' => 'Birch sheet', 'unit_price' => '48.98'],
                        ['short_name' => '', 'description' => 'Electrical work (Including material)'],
                    ],
                ],
                [
                    'title' => 'Hall',
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => '400',
                    'materials' => [
                        ['short_name' => 'Trim', 'description' => 'Pine casing'],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('Standard labour and materials', $text);
        $this->assertStringContainsString('Job 1: Kitchen', $text);
        $this->assertStringContainsString('Plywood — Birch sheet', $text);
        $this->assertStringContainsString('Electrical work (Including material)', $text);
        $this->assertStringContainsString('Labour: estimated in days', $text);
        $this->assertStringContainsString('Job 2: Hall', $text);
        $this->assertStringContainsString('Trim — Pine casing', $text);
        $this->assertStringContainsString('Labour: flat fee', $text);
        $this->assertStringContainsString('Contingency allowance: 10%', $text);

        $this->assertStringNotContainsString('48.98', $text);
        $this->assertStringNotContainsString('65', $text);
        $this->assertStringNotContainsString('1130', $text);
        $this->assertStringNotContainsString('CA$', $text);
        $this->assertStringNotContainsString('Grand total', $text);
        $this->assertStringNotContainsString('Rate ', $text);
        $this->assertStringNotContainsString('unit ', $text);
        $this->assertStringNotContainsString('Totals snapshot', $text);
    }

    public function test_standard_ai_collector_does_not_send_prices_or_missing_totals_id(): void
    {
        $js = file_get_contents(public_path('js/admin-quotation-work-description-ai.js'));
        $this->assertNotFalse($js);

        $start = strpos($js, 'function collectStandardContext');
        $this->assertNotFalse($start);
        $next = strpos($js, 'function collect', $start + 10);
        $chunk = $next === false ? substr($js, $start) : substr($js, $start, $next - $start);

        $this->assertStringNotContainsString('sq-mat-with-hst', $chunk);
        $this->assertStringNotContainsString('unit_price', $chunk);
        $this->assertStringNotContainsString('labour_hourly_rate', $chunk);
        $this->assertStringNotContainsString('totals_summary', $chunk);
        $this->assertStringContainsString('labour_mode', $chunk);
    }
}
