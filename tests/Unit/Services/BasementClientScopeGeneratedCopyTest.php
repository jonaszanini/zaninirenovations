<?php

namespace Tests\Unit\Services;

use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementClientScopeGeneratedCopyTest extends TestCase
{
    public function test_disabled_trades_do_not_generate_electrical_or_plumbing_allowances(): void
    {
        $copy = BasementClientScope::generatedCopy([
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'demolition', 'drywall'],
            'include_bathroom' => true,
            'electrical_lines' => [
                ['description' => 'STALE PANEL UPGRADE', 'amount' => 1800, 'hst_included' => true],
            ],
            'plumbing_lines' => [
                ['description' => 'STALE ROUGH-IN', 'amount' => 900, 'hst_included' => true],
            ],
        ]);

        $this->assertStringContainsString('Electrical', $copy['not_included']);
        $this->assertStringContainsString('Plumbing', $copy['not_included']);
        $this->assertStringContainsString('Bathroom', $copy['not_included']);
        $this->assertStringNotContainsString('Electrical work allowance', $copy['allowances']);
        $this->assertStringNotContainsString('Plumbing work allowance', $copy['allowances']);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $copy['included'].$copy['not_included'].$copy['allowances']);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $copy['included'].$copy['not_included'].$copy['allowances']);
    }

    public function test_enabled_trades_keep_allowances_and_do_not_list_those_trades_as_excluded(): void
    {
        $copy = BasementClientScope::generatedCopy([
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'electrical', 'plumbing'],
            'electrical_lines' => [
                ['description' => 'Panel upgrade', 'amount' => 1130, 'hst_included' => true],
            ],
            'plumbing_lines' => [
                ['description' => 'Rough-in stack', 'amount' => 900, 'hst_included' => true],
            ],
        ]);

        $this->assertStringContainsString('Electrical work allowance CA$', $copy['allowances']);
        $this->assertStringContainsString('Plumbing work allowance CA$', $copy['allowances']);
        $this->assertStringNotContainsString(
            BasementQuotationCalculator::SECTIONS['electrical'],
            $copy['not_included']
        );
        $this->assertStringNotContainsString(
            BasementQuotationCalculator::SECTIONS['plumbing'],
            $copy['not_included']
        );
    }

    public function test_enabled_demolition_with_no_choice_is_not_included_in_generated_scope(): void
    {
        $copy = BasementClientScope::generatedCopy([
            'scope_preset' => 'full',
            'include_demolition' => false,
            'demo_include_flooring' => true,
            'demo_flooring_sf' => 400,
        ]);

        $this->assertStringContainsString(
            BasementQuotationCalculator::SECTIONS['demolition'],
            $copy['not_included']
        );
        $this->assertStringNotContainsString(
            BasementQuotationCalculator::SECTIONS['demolition'],
            $copy['included']
        );
    }

    public function test_enabled_demolition_yes_stays_in_generated_included_scope(): void
    {
        $copy = BasementClientScope::generatedCopy([
            'scope_preset' => 'full',
            'include_demolition' => true,
        ]);

        $this->assertStringContainsString(
            BasementQuotationCalculator::SECTIONS['demolition'],
            $copy['included']
        );
        $this->assertStringNotContainsString(
            BasementQuotationCalculator::SECTIONS['demolition'],
            $copy['not_included']
        );
    }
}
