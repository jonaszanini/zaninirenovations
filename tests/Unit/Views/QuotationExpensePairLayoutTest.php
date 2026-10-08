<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class QuotationExpensePairLayoutTest extends TestCase
{
    public function test_commute_and_debris_share_a_stacked_expense_pair(): void
    {
        $blade = file_get_contents(resource_path('views/admin/quotations/_commute_debris_row.blade.php'));
        $this->assertNotFalse($blade);
        $this->assertStringContainsString('class="q-expense-pair"', $blade);
        $this->assertStringContainsString("admin.quotations._commute_expense", $blade);
        $this->assertStringContainsString("admin.quotations._debris_removal", $blade);
    }

    public function test_expense_pair_css_stays_one_column_on_desktop(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.q-expense-pair\s*\{[^}]*grid-template-columns:\s*1fr/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.q-expense-pair\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s',
            $css,
            'Charge cards must stay stacked; do not restore a two-column .q-expense-pair grid.'
        );
    }

    public function test_quotation_editors_use_shared_expense_pair_partial(): void
    {
        $createViews = glob(resource_path('views/admin/quotations/*/create.blade.php')) ?: [];
        $this->assertNotEmpty($createViews);

        $pairUsers = [];
        $missingPair = [];

        foreach ($createViews as $file) {
            $html = (string) file_get_contents($file);
            $type = basename(dirname($file));
            $hasPair = str_contains($html, 'admin.quotations._commute_debris_row');
            $hasCommute = str_contains($html, 'admin.quotations._commute_expense');
            $hasDebris = str_contains($html, 'admin.quotations._debris_removal');

            if ($hasPair) {
                $pairUsers[] = $type;

                continue;
            }

            if ($hasCommute && $hasDebris) {
                $missingPair[] = $type;
            }
        }

        $this->assertNotEmpty($pairUsers, 'Expected quotation create views to include the shared commute/debris pair.');
        $this->assertSame(
            [],
            $missingPair,
            'Quotation types that show both charge cards must use _commute_debris_row so they stay stacked.'
        );
    }
}
