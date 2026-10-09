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
            ->assertSee('class="sp-col-trailing"', false)
            ->assertSee('class="sp-trailing"', false)
            ->assertSee('class="sp-work-label"', false)
            ->assertSee('class="sp-work-meta"', false)
            ->assertSee('class="sp-chip"', false)
            ->assertSee('cp-money-input', false)
            ->getContent();

        $this->assertStringContainsString('Install window trim', $html);
        $this->assertStringContainsString('Install built-up / hardwood baseboard', $html);
        $this->assertStringContainsString('Picture-frame window casing', $html);

        $this->assertStringNotContainsString('sp-col-catalog', $html);
        $this->assertStringNotContainsString('sp-col-consumers', $html);
        $this->assertStringNotContainsString('class="sp-effective"', $html);
        $this->assertStringNotContainsString('class="admin-form-help"', $html);

        $this->assertStringContainsString('admin-info-tip', $html);
        $this->assertStringContainsString('Hrs each', $html);
        $this->assertStringContainsString('/linft', $html);

        $this->assertStringContainsString('.sp-col-trailing', $html);
        $this->assertStringContainsString('.sp-trailing', $html);
        $this->assertStringContainsString('align-items: center', $html);
        $this->assertStringContainsString('inline-flex', $html);
        $this->assertStringContainsString('minmax(6.5rem, 6.75rem)', $html);
        $this->assertStringContainsString('minmax(5.75rem, 0.65fr)', $html);
    }
}
