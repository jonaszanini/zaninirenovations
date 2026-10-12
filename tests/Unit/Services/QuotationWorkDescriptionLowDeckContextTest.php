<?php

namespace Tests\Unit\Services;

use App\Services\QuotationWorkDescriptionContextBuilder;
use Tests\TestCase;

class QuotationWorkDescriptionLowDeckContextTest extends TestCase
{
    public function test_low_deck_context_omits_geometry_counts_waste_percent_and_prices(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('low_deck', [
            'client_name' => 'Jonas',
            'width_ft' => 12,
            'length_ft' => 14,
            'joist_spacing_in' => 16,
            'geometry_summary' => "168 sq ft · 12' × 14'\n8 blocks (3 × 3 grid)\nMaterial waste: 10% (internal)",
            'materials_summary' => "2x6 joistPosts: 4.00 ft per block — CA$40\n5/4 deck board (10% waste) — CA$220",
            'totals_summary' => 'Materials: CA$900; Material waste (10%): CA$90; Grand total: CA$1,200',
            'breakdown_summary' => 'Material waste: 10% (internal)',
            'material_waste_percent' => 10,
            'material_waste_amount' => 90,
        ]);

        $this->assertStringContainsString('Low floating deck', $text);
        $this->assertStringContainsString('12 ft', $text);
        $this->assertStringContainsString('14 ft', $text);
        $this->assertStringContainsString('Joist spacing: 16 in', $text);
        $this->assertStringNotContainsString('16 in in', $text);
        $this->assertStringNotContainsString('max 15', $text);
        $this->assertStringNotContainsString('auto', $text);
        $this->assertStringContainsString('2x6 joist', $text);
        $this->assertStringContainsString('5/4 deck board', $text);

        $this->assertStringNotContainsString('Layout / geometry', $text);
        $this->assertStringNotContainsString('8 blocks', $text);
        $this->assertStringNotContainsString('Material waste', $text);
        $this->assertStringNotContainsString('(internal)', $text);
        $this->assertStringNotContainsString('10%', $text);
        $this->assertStringNotContainsString('CA$', $text);
        $this->assertStringNotContainsString('Grand total', $text);
        $this->assertStringNotContainsString('Pricing snapshot', $text);
    }

    public function test_low_deck_joist_spacing_accepts_inch_strings_and_drops_formula_notes(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();

        $fromNote = $builder->build('low_deck', [
            'width_ft' => 12,
            'length_ft' => 14,
            'joist_spacing_in' => '16 in (auto, max 15 in.)',
        ]);
        $fromInchWord = $builder->build('low_deck', [
            'width_ft' => 12,
            'length_ft' => 14,
            'joist_spacing_in' => '16-inch',
        ]);
        $empty = $builder->build('low_deck', [
            'width_ft' => 12,
            'length_ft' => 14,
            'joist_spacing_in' => '',
        ]);

        $this->assertStringContainsString('Joist spacing: 16 in', $fromNote);
        $this->assertStringNotContainsString('auto', $fromNote);
        $this->assertStringNotContainsString('max 15', $fromNote);
        $this->assertStringContainsString('Joist spacing: 16 in', $fromInchWord);
        $this->assertStringNotContainsString('Joist spacing', $empty);
    }
}
