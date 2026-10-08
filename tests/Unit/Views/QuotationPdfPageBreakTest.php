<?php

namespace Tests\Unit\Views;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Tests\TestCase;

class QuotationPdfPageBreakTest extends TestCase
{
    public function test_items_table_may_break_across_pages_without_avoid_on_the_whole_table(): void
    {
        $html = $this->renderPdfHtml([
            [
                'description' => 'Kitchen pantry',
                'amount' => 0,
                'apply_hst' => false,
                'is_heading' => true,
            ],
            [
                'description' => 'Custom millwork (labour)',
                'amount' => 1200,
                'apply_hst' => true,
            ],
            [
                'description' => 'Materials',
                'amount' => 450,
                'apply_hst' => true,
            ],
        ]);

        $this->assertStringContainsString('class="items"', $html);
        $this->assertStringContainsString('items-last', $html);

        $items = $this->cssDeclaration($html, '.items');
        $this->assertStringContainsString('page-break-inside: auto', $items);
        $this->assertStringNotContainsString('page-break-inside: avoid', $items);
        $this->assertStringNotContainsString('break-inside: avoid', $items);

        $thead = $this->cssDeclaration($html, '.items thead');
        $this->assertStringContainsString('display: table-header-group', $thead);

        $tr = $this->cssDeclaration($html, '.items tr');
        $this->assertStringContainsString('page-break-inside: avoid', $tr);

        $totals = $this->cssDeclaration($html, '.items tbody tr.items-totals');
        $this->assertStringContainsString('page-break-inside: avoid', $totals);
        $this->assertStringContainsString('page-break-before: avoid', $totals);

        $keepWithTotals = $this->cssDeclaration($html, '.items tbody tr.items-last');
        $this->assertStringContainsString('page-break-after: avoid', $keepWithTotals);
        $this->assertStringContainsString('items-totals', $html);
        $this->assertStringNotContainsString('<tfoot>', $html);

        $heading = $this->cssDeclaration($html, '.items tbody tr.items-heading');
        $this->assertStringContainsString('page-break-after: avoid', $heading);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function renderPdfHtml(array $items): string
    {
        $project = new ClientProject([
            'name' => 'Kitchen pantry',
            'client_name' => 'Ada Client',
            'client_address' => '12 Demo Street, Ottawa, ON',
            'client_phone' => '6135550100',
            'client_email' => 'ada@example.test',
        ]);

        $quotation = new ClientProjectQuotation([
            'title' => 'Custom millwork',
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'QUO-2026-0255',
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'labour' => true,
            'material' => true,
            'items' => $items,
            'work_description' => 'Kitchen pantry built-in with painted doors.',
        ]);

        return view('admin.client_projects.quotation.pdf', [
            'project' => $project,
            'quotation' => $quotation,
            'pdfPhotos' => collect(),
            'acceptUrl' => 'https://example.test/accept',
        ])->render();
    }

    private function cssDeclaration(string $html, string $selector): string
    {
        $pattern = '/'.preg_quote($selector, '/').'\s*\{([^}]+)\}/';
        $this->assertSame(
            1,
            preg_match($pattern, $html, $match),
            "Missing CSS rule for {$selector}"
        );

        return $match[1];
    }
}
