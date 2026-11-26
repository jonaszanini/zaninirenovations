<?php

namespace Tests\Unit\Services;

use App\Models\BasementQuotation;
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

    public function test_flood_restoration_generated_copy_has_required_assumptions_and_no_forbidden_terms(): void
    {
        $copy = BasementClientScope::generatedCopy([
            'scope_preset' => 'flood',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('flood'),
            'insulate_walls' => true,
            'flood_cut_height_in' => 24,
            'flood_casing_mode' => 'legs',
            'flooring_type' => 'lvp',
        ]);

        $this->assertStringContainsString(
            'Water extraction, drying, moisture readings and mould remediation are completed by others before we start.',
            $copy['assumptions']
        );
        $this->assertStringContainsString('O.Reg. 332/12', $copy['assumptions']);
        $this->assertStringContainsString('fully insured', $copy['assumptions']);

        $allCopy = $copy['included']."\n".$copy['not_included']."\n".$copy['assumptions']."\n".$copy['allowances'];
        $this->assertDoesNotMatchRegularExpression('/\bR-?\d+/i', $allCopy);
        $this->assertDoesNotMatchRegularExpression('/\b(Buy|Purchase|Discount|licensed|licence|WSIB|Price)\b/i', $allCopy);
    }

    public function test_stale_auto_scope_moves_final_details_when_that_section_is_enabled(): void
    {
        $label = BasementQuotationCalculator::SECTIONS['finishing'];
        $offSections = array_values(array_diff(
            BasementQuotationCalculator::presetEnabledSections('full'),
            ['finishing']
        ));
        $off = [
            'scope_preset' => 'full',
            'enabled_sections' => $offSections,
            'include_cleaning' => false,
            'include_touchups' => false,
        ];
        $generatedOff = BasementClientScope::generatedCopy($off);
        $this->assertContains($label, $this->scopeLines($generatedOff['not_included']));
        $this->assertNotContains($label, $this->scopeLines($generatedOff['included']));

        $on = $off;
        $on['enabled_sections'] = array_merge($offSections, ['finishing']);
        $on['include_cleaning'] = true;
        $on['include_touchups'] = true;
        $on['client_scope_included'] = $generatedOff['included'];
        $on['client_scope_not_included'] = $generatedOff['not_included'];

        $resolved = BasementClientScope::resolveCopy($on);
        $this->assertContains($label, $this->scopeLines($resolved['included']));
        $this->assertNotContains($label, $this->scopeLines($resolved['not_included']));
        $this->assertSame($resolved['generated']['included'], $resolved['included']);
        $this->assertSame($resolved['generated']['not_included'], $resolved['not_included']);

        $quote = new BasementQuotation([
            'inputs' => $on,
            'calculation' => [
                'client_scope' => [
                    'included' => $generatedOff['included'],
                    'not_included' => $generatedOff['not_included'],
                    'assumptions' => 'Saved assumption stays.',
                    'allowances' => 'No separate cash allowances beyond the priced lines on this quotation.',
                ],
            ],
        ]);
        $pdfAndEmail = $quote->clientScopeCopy();
        $this->assertContains($label, $this->scopeLines($pdfAndEmail['included']));
        $this->assertNotContains($label, $this->scopeLines($pdfAndEmail['not_included']));
        $this->assertSame('Saved assumption stays.', $pdfAndEmail['assumptions']);

        $withNotes = $on;
        $withNotes['client_scope_included'] = $generatedOff['included']."\nSite note stays.";
        $withNotes['client_scope_not_included'] = $generatedOff['not_included']."\nPlease keep this note.";
        $noted = BasementClientScope::resolveCopy($withNotes);
        $this->assertContains($label, $this->scopeLines($noted['included']));
        $this->assertContains('Site note stays.', $this->scopeLines($noted['included']));
        $this->assertNotContains($label, $this->scopeLines($noted['not_included']));
        $this->assertContains('Please keep this note.', $this->scopeLines($noted['not_included']));

        $custom = BasementClientScope::resolveCopy(array_merge($on, [
            'client_scope_included' => 'Custom included list',
            'client_scope_not_included' => 'Carpet flooring.',
        ]));
        $this->assertSame('Custom included list', $custom['included']);
        $this->assertSame('Carpet flooring.', $custom['not_included']);

        $html = view('admin.quotations.basement._client_scope', [
            'inputs' => $on,
            'isEdit' => true,
            'wizardStep' => 12,
            'enabledCount' => 14,
        ])->render();
        $this->assertStringContainsString($label, $this->textareaValue($html, 'br-scope-included'));
        $this->assertStringNotContainsString($label, $this->textareaValue($html, 'br-scope-not-included'));
        $this->assertDoesNotMatchRegularExpression(
            '/id="br-scope-included"[^>]*data-br-scope-persisted="1"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="br-scope-not-included"[^>]*data-br-scope-persisted="1"/',
            $html
        );
    }

    /**
     * @return list<string>
     */
    private function scopeLines(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function textareaValue(string $html, string $id): string
    {
        $this->assertMatchesRegularExpression(
            '/<textarea\b[^>]*id="'.preg_quote($id, '/').'"[^>]*>.*?<\/textarea>/s',
            $html
        );
        preg_match('/<textarea\b[^>]*id="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/textarea>/s', $html, $match);

        return html_entity_decode($match[1] ?? '', ENT_QUOTES);
    }
}
