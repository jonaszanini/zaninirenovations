<?php

namespace Tests\Unit\Models;

use App\Models\BasementQuotation;
use App\Models\ClientProjectQuotation;
use Tests\TestCase;

class ClientProjectQuotationJobLabelTest extends TestCase
{
    public function test_basement_wall_job_label_uses_scope_not_project_name(): void
    {
        $basement = new BasementQuotation([
            'quote_number' => 'BR-2026-0001',
            'inputs' => [
                'scope_preset' => 'wall',
                'include_bathroom' => false,
            ],
        ]);

        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_BASEMENT,
            'source_id' => 1,
            'title' => $basement->displayTitle(),
            'source_snapshot' => $basement->only(['quote_number', 'inputs']),
        ]);

        $this->assertSame('New wall', $quote->jobLabelForPdf());
    }

    public function test_basement_bathroom_job_label(): void
    {
        $basement = new BasementQuotation([
            'quote_number' => 'BR-2026-0002',
            'inputs' => [
                'scope_preset' => 'bathroom',
                'include_bathroom' => true,
            ],
        ]);

        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_BASEMENT,
            'source_id' => 2,
            'source_snapshot' => $basement->only(['quote_number', 'inputs']),
        ]);

        $this->assertSame('New bathroom', $quote->jobLabelForPdf());
    }

    public function test_strip_display_title_quote_number(): void
    {
        $this->assertSame(
            'Custom Millwork',
            ClientProjectQuotation::stripDisplayTitleQuoteNumber('Custom Millwork — MW-2026-0042')
        );
        $this->assertSame(
            'Basement Renovation',
            ClientProjectQuotation::stripDisplayTitleQuoteNumber('Basement Renovation — BR-2026-0280')
        );
    }

    public function test_manual_quote_uses_stored_title(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_MANUAL,
            'title' => 'Mudroom doors & drawers',
        ]);

        $this->assertSame('Mudroom doors & drawers', $quote->jobLabelForPdf());
    }
}
