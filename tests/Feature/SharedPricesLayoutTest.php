<?php

namespace Tests\Feature;

use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedPricesLayoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_shared_prices_page_uses_column_and_trailing_classes(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Shared prices', false)
            ->assertSee('class="sp-col-label"', false)
            ->assertSee('class="sp-col-rate"', false)
            ->assertSee('class="sp-col-sell"', false)
            ->assertSee('class="sp-col-unit"', false)
            ->assertSee('class="sp-col-price"', false)
            ->assertSee('class="sp-col-name"', false)
            ->assertSee('class="sp-col-catalog"', false)
            ->assertSee('class="sp-col-consumers"', false)
            ->assertSee('class="sp-consumers"', false)
            ->assertSee('class="sp-work-label"', false)
            ->assertSee('class="sp-work-meta"', false)
            ->assertSee('class="sp-chip"', false)
            ->assertSee('sp-chip--more', false)
            ->assertSee('cp-money-input', false)
            ->getContent();

        $this->assertStringContainsString('Install window trim', $html);
        $this->assertStringContainsString('Install built-up / hardwood baseboard', $html);
        $this->assertStringContainsString('Picture-frame window casing', $html);

        $this->assertStringNotContainsString('sp-col-trailing', $html);
        $this->assertStringNotContainsString('class="sp-trailing"', $html);
        $this->assertStringNotContainsString('class="sp-effective"', $html);
        $this->assertStringNotContainsString('class="admin-form-help"', $html);

        $this->assertStringContainsString('admin-info-tip', $html);
        $this->assertStringContainsString('Hrs each', $html);
        $this->assertStringContainsString('/linft', $html);

        $this->assertStringContainsString('.sp-col-catalog', $html);
        $this->assertStringContainsString('.sp-col-consumers', $html);
        $this->assertStringContainsString('.sp-table td.sp-col-catalog', $html);
        $this->assertStringContainsString('align-items: center', $html);
        $this->assertStringContainsString('align-items: stretch', $html);
        $this->assertStringContainsString('vertical-align: middle', $html);
        $this->assertStringContainsString('justify-content: flex-start', $html);
        $this->assertStringContainsString('minmax(9.375rem, 1.45fr)', $html);
        $this->assertStringContainsString("\n      5rem", $html);
        $this->assertStringContainsString('minmax(4.75rem, 1fr)', $html);
        $this->assertStringContainsString('2.5rem', $html);
        $this->assertStringContainsString("\n      7rem;", $html);
        $this->assertStringContainsString('-webkit-line-clamp: 2', $html);
        $this->assertStringContainsString('sp-chip-more__tip', $html);
        $this->assertStringContainsString('+2', $html);

        $this->assertStringNotContainsString('minmax(6.5rem, 6.75rem)', $html);
        $this->assertStringNotContainsString('minmax(0, 1.1fr)', $html);
    }
}
