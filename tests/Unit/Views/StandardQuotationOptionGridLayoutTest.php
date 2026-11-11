<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class StandardQuotationOptionGridLayoutTest extends TestCase
{
    public function test_create_and_edit_share_the_option_grid_template(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/StandardQuotationController.php'));
        $this->assertNotFalse($controller);
        $this->assertGreaterThanOrEqual(2, substr_count($controller, "view('admin.quotations.standard.create'"));
        $this->assertDoesNotMatchRegularExpression(
            "/return view\\('admin\\.quotations\\.standard\\.edit'/",
            $controller
        );
    }

    public function test_standard_editor_wraps_option_cards_in_a_grid(): void
    {
        $blade = $this->createBlade();
        $this->assertStringContainsString('class="sq-option-grid"', $blade);

        $gridPos = strpos($blade, 'class="sq-option-grid"');
        $this->assertNotFalse($gridPos);

        $buildingPos = strpos($blade, "admin.quotations._building");
        $pairPos = strpos($blade, "admin.quotations._commute_debris_row");
        $payPos = strpos($blade, "admin.quotations._payment_schedule");
        $closePos = strpos($blade, 'sq-grand-card', $gridPos);

        $this->assertNotFalse($buildingPos);
        $this->assertNotFalse($pairPos);
        $this->assertNotFalse($payPos);
        $this->assertNotFalse($closePos);
        $this->assertGreaterThan($gridPos, $buildingPos);
        $this->assertGreaterThan($buildingPos, $pairPos);
        $this->assertGreaterThan($pairPos, $payPos);
        $this->assertGreaterThan($payPos, $closePos);
        $gridClose = strpos($blade, '</div>', $payPos);
        $this->assertNotFalse($gridClose);
        $this->assertLessThan($closePos, $gridClose);
    }

    public function test_option_grid_css_is_one_column_until_1024px(): void
    {
        $css = $this->optionCss();

        $this->assertMatchesRegularExpression(
            '/\.sq-option-grid\s*\{[^}]*grid-template-columns:\s*1fr/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*1024px\)\s*\{[^}]*\.sq-option-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.sq-option-grid\s+\.q-expense-pair\s*\{[^}]*display:\s*contents/s',
            $css
        );
    }

    public function test_option_partials_keep_saved_field_hooks(): void
    {
        $building = file_get_contents(resource_path('views/admin/quotations/_building.blade.php'));
        $commute = file_get_contents(resource_path('views/admin/quotations/_commute_expense.blade.php'));
        $debris = file_get_contents(resource_path('views/admin/quotations/_debris_removal.blade.php'));
        $pay = file_get_contents(resource_path('views/admin/quotations/_payment_schedule.blade.php'));

        $this->assertStringContainsString('id="q-building"', $building);
        $this->assertStringContainsString('id="q-building-include"', $building);
        $this->assertStringContainsString('name="include_building"', $building);
        $this->assertStringContainsString('id="q-building-percent"', $building);
        $this->assertStringContainsString('name="building_labor_percent"', $building);
        $this->assertStringContainsString('id="q-building-added-amount"', $building);
        $this->assertStringContainsString('Added to this quotation', $building);

        $this->assertStringContainsString('id="q-commute-expense"', $commute);
        $this->assertStringContainsString('name="include_commute"', $commute);
        $this->assertStringContainsString('data-cp-commute-include-yes', $commute);
        $this->assertStringContainsString('data-cp-commute-include-no', $commute);
        $this->assertStringContainsString('data-cp-commute-round-trip', $commute);
        $this->assertStringContainsString('id="q-commute-percent"', $commute);
        $this->assertStringContainsString('name="commute_trip_percent"', $commute);
        $this->assertStringContainsString('id="q-commute-trips"', $commute);
        $this->assertStringContainsString('name="commute_estimated_trips"', $commute);
        $this->assertStringContainsString('data-cp-commute-status', $commute);

        $this->assertStringContainsString('id="q-debris-removal"', $debris);
        $this->assertStringContainsString('name="include_debris_removal"', $debris);
        $this->assertStringContainsString('data-cp-debris-include-yes', $debris);
        $this->assertStringContainsString('id="q-debris-amount"', $debris);
        $this->assertStringContainsString('name="debris_removal_amount"', $debris);

        $this->assertStringContainsString('id="q-payment-schedule"', $pay);
        $this->assertStringContainsString('name="payment_installments"', $pay);
        $this->assertStringContainsString('name="payment_percentages', $pay);
        $this->assertStringContainsString('data-plan-summary', $pay);
    }

    public function test_create_does_not_change_the_sticky_footer_include(): void
    {
        $blade = $this->createBlade();
        $this->assertStringContainsString("admin.partials.quotation_editor_footer", $blade);
        $this->assertStringContainsString("'formId' => 'sq-standard-form'", $blade);
        $this->assertStringContainsString("'amountId' => 'sq-toolbar-grand-amount'", $blade);
        $this->assertStringContainsString("'grandWrapId' => 'sq-toolbar-grand'", $blade);
    }

    private function createBlade(): string
    {
        $blade = file_get_contents(resource_path('views/admin/quotations/standard/create.blade.php'));
        $this->assertNotFalse($blade);

        return $blade;
    }

    private function optionCss(): string
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        return $css;
    }
}
