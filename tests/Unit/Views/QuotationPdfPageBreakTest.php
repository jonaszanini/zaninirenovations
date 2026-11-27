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

    public function test_section_headings_stay_with_the_first_body_lines_and_long_lists_can_split(): void
    {
        $included = [];
        $assumptions = [];
        for ($i = 1; $i <= 40; $i++) {
            $included[] = 'Included line '.$i;
        }
        for ($i = 1; $i <= 20; $i++) {
            $assumptions[] = 'Assumption '.$i;
        }

        $html = $this->renderPdfHtml(
            [[
                'description' => 'Basement renovation (labour)',
                'amount' => 1200,
                'apply_hst' => true,
            ]],
            implode("\n", $included),
            implode("\n", $assumptions),
            '<p>First short paragraph stays with the heading.</p><p>'.str_repeat('More scope detail. ', 40).'</p>'
        );

        $scopeTable = $this->cssDeclaration($html, '.quote-client-scope-table');
        $this->assertStringContainsString('page-break-inside: auto', $scopeTable);
        $this->assertStringNotContainsString('page-break-inside: avoid', $scopeTable);

        $scopeRow = $this->cssDeclaration($html, '.quote-client-scope-table tr');
        $this->assertStringContainsString('page-break-inside: avoid', $scopeRow);

        $this->assertMatchesRegularExpression(
            '/\.quote-client-scope-table tr\.quote-client-scope-head[^{]*\{[^}]*page-break-after:\s*avoid/s',
            $html
        );

        $keep = $this->cssDeclaration($html, '.quote-heading-keep');
        $this->assertStringContainsString('page-break-inside: avoid', $keep);
        $this->assertStringContainsString('page-break-after: avoid', $keep);

        $this->assertSame(2, substr_count($html, 'class="quote-client-scope-kicker"'));
        $this->assertStringContainsString('>Value<', $html);
        $this->assertStringContainsString('>Special terms<', $html);
        $this->assertGreaterThan(40, substr_count($html, 'quote-client-scope-line'));
        $this->assertStringNotContainsString('page-break-before: always', $html);

        $titleAt = strpos($html, 'Description of the work');
        $firstAt = strpos($html, 'First short paragraph stays with the heading.');
        $restAt = strpos($html, 'More scope detail.');
        $this->assertNotFalse($titleAt);
        $this->assertNotFalse($firstAt);
        $this->assertNotFalse($restAt);
        $keepClose = strpos($html, '</table>', $titleAt);
        $this->assertNotFalse($keepClose);
        $this->assertLessThan($firstAt, $titleAt);
        $this->assertLessThan($keepClose, $firstAt);
        $this->assertLessThan($restAt, $keepClose);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function renderPdfHtml(array $items, string $included = '', string $assumptions = '', ?string $workDescription = null): string
    {
        $project = new ClientProject([
            'name' => 'Kitchen pantry',
            'client_name' => 'Ada Client',
            'client_address' => '12 Demo Street, Ottawa, ON',
            'client_phone' => '6135550100',
            'client_email' => 'ada@example.test',
        ]);

        $quotation = new class([
            'title' => 'Custom millwork',
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'QUO-2026-0255',
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'labour' => true,
            'material' => true,
            'items' => $items,
            'work_description' => $workDescription ?? 'Kitchen pantry built-in with painted doors.',
        ]) extends ClientProjectQuotation {
            public function clientScopeForPdf(): ?array
            {
                $included = (string) ($this->attributes['__scope_included'] ?? '');
                $assumptions = (string) ($this->attributes['__scope_assumptions'] ?? '');
                if (trim($included) === '' && trim($assumptions) === '') {
                    return null;
                }

                return [
                    'included' => $included,
                    'not_included' => 'Work outside this quotation.',
                    'assumptions' => $assumptions,
                    'allowances' => 'Allowance stays with the heading.',
                ];
            }
        };
        $quotation->setAttribute('__scope_included', $included);
        $quotation->setAttribute('__scope_assumptions', $assumptions);

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
