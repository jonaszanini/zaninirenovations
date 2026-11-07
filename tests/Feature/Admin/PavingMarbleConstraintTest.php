<?php

namespace Tests\Feature\Admin;

use App\Models\PavingMarbleType;
use App\Models\PavingQuotation;
use App\Models\PavingStoneType;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PavingMarbleConstraintTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_form_does_not_constrain_disabled_marble_area(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.paving.create'))
            ->assertOk()
            ->getContent();

        $area = $this->elementById($html, 'pq-marble-area');
        $this->assertTrue($area->hasAttribute('disabled'), 'disabled marble area must skip HTML5 constraints');
        $this->assertFalse($area->hasAttribute('required'));
        $this->assertSame('', $area->getAttribute('min'));

        $type = $this->elementById($html, 'pq-marble-type');
        $this->assertTrue($type->hasAttribute('disabled'));
        $this->assertFalse($type->hasAttribute('required'));

        $enabled = $this->elementById($html, 'pq-marble-enabled');
        $this->assertFalse($enabled->hasAttribute('checked'));
        $fields = $this->elementById($html, 'pq-marble-fields');
        $this->assertTrue($fields->hasAttribute('hidden'));
    }

    public function test_ordinary_paving_saves_with_marble_off_and_zero_area_posted(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.paving.create'))
            ->post(route('admin.quotations.paving.store'), $this->pavingPayload([
                'client_name' => 'Ordinary Paving No Marble',
                'marble_area_sq_ft' => 0,
                'marble_depth_in' => 2,
            ]))
            ->assertSessionDoesntHaveErrors(['marble_area_sq_ft', 'marble_type_id', 'marble_depth_in'])
            ->assertRedirect();

        $quote = PavingQuotation::query()->where('client_name', 'Ordinary Paving No Marble')->first();
        $this->assertNotNull($quote);
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertFalse((bool) ($inputs['marble_enabled'] ?? true));
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);

        $edit = $this->get(route('admin.quotations.paving.edit', $quote))
            ->assertOk()
            ->getContent();
        $area = $this->elementById($edit, 'pq-marble-area');
        $this->assertTrue($area->hasAttribute('disabled'));
        $this->assertSame('', $area->getAttribute('min'));
    }

    public function test_ordinary_paving_publishes_without_marble_and_survives_edit_reopen(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.paving.create'))
            ->post(route('admin.quotations.paving.store'), $this->pavingPayload([
                'save_intent' => 'publish',
                'client_name' => 'Ordinary Paving Publish No Marble',
            ]))
            ->assertSessionDoesntHaveErrors(['marble_area_sq_ft', 'marble_type_id', 'marble_depth_in'])
            ->assertRedirect();

        $quote = PavingQuotation::query()->where('client_name', 'Ordinary Paving Publish No Marble')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $this->assertFalse((bool) ($inputs['marble_enabled'] ?? true));
        $this->assertNull($calc['marble'] ?? null);
        $publishedTotal = (float) $quote->final_quote_total;
        $this->assertGreaterThan(0, $publishedTotal);

        $edit = $this->get(route('admin.quotations.paving.edit', $quote))
            ->assertOk()
            ->getContent();
        $area = $this->elementById($edit, 'pq-marble-area');
        $this->assertTrue($area->hasAttribute('disabled'));
        $this->assertFalse($this->elementById($edit, 'pq-marble-enabled')->hasAttribute('checked'));

        $this->put(route('admin.quotations.paving.update', $quote), $this->pavingPayload([
            'save_intent' => 'publish',
            'client_name' => 'Ordinary Paving Publish No Marble',
        ]))->assertSessionDoesntHaveErrors(['marble_area_sq_ft', 'marble_type_id'])
            ->assertRedirect(route('admin.quotations.paving.edit', $quote));

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertFalse((bool) (($quote->inputs ?? [])['marble_enabled'] ?? true));
        $this->assertNull(($quote->calculation ?? [])['marble'] ?? null);
        $this->assertEqualsWithDelta($publishedTotal, (float) $quote->final_quote_total, 0.02);
    }

    public function test_stale_marble_values_do_not_charge_or_block_when_marble_is_off(): void
    {
        $this->actingAsAdmin();
        $marbleId = PavingMarbleType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($marbleId);

        $baseline = $this->pavingPayload([
            'save_intent' => 'publish',
            'client_name' => 'Paving Baseline No Marble',
        ]);
        $this->post(route('admin.quotations.paving.store'), $baseline)->assertRedirect();
        $plain = PavingQuotation::query()->where('client_name', 'Paving Baseline No Marble')->first();
        $this->assertNotNull($plain);

        $this->from(route('admin.quotations.paving.create'))
            ->post(route('admin.quotations.paving.store'), $this->pavingPayload([
                'save_intent' => 'publish',
                'client_name' => 'Paving Stale Marble Off',
                'marble_type_id' => $marbleId,
                'marble_area_sq_ft' => 50,
                'marble_depth_in' => 2,
                'marble_include_geotextile' => '1',
            ]))
            ->assertSessionDoesntHaveErrors(['marble_area_sq_ft', 'marble_type_id', 'marble_depth_in'])
            ->assertRedirect();

        $quote = PavingQuotation::query()->where('client_name', 'Paving Stale Marble Off')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertFalse((bool) ($inputs['marble_enabled'] ?? true));
        $this->assertNull(($quote->calculation ?? [])['marble'] ?? null);
        $this->assertEqualsWithDelta((float) $plain->final_quote_total, (float) $quote->final_quote_total, 0.02);
    }

    public function test_enabled_marble_rejects_area_below_minimum_and_accepts_valid_area(): void
    {
        $this->actingAsAdmin();
        $marbleId = PavingMarbleType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($marbleId);

        $this->from(route('admin.quotations.paving.create'))
            ->post(route('admin.quotations.paving.store'), $this->pavingPayload([
                'save_intent' => 'publish',
                'client_name' => 'Marble Too Small',
                'marble_enabled' => '1',
                'marble_type_id' => $marbleId,
                'marble_area_sq_ft' => 0.05,
                'marble_depth_in' => 2,
            ]))
            ->assertRedirect(route('admin.quotations.paving.create'))
            ->assertSessionHasErrors('marble_area_sq_ft');

        $this->assertSame(0, PavingQuotation::query()->where('client_name', 'Marble Too Small')->count());

        $this->from(route('admin.quotations.paving.create'))
            ->post(route('admin.quotations.paving.store'), $this->pavingPayload([
                'save_intent' => 'publish',
                'client_name' => 'Marble Valid Area',
                'marble_enabled' => '1',
                'marble_type_id' => $marbleId,
                'marble_area_sq_ft' => 50,
                'marble_depth_in' => 2,
            ]))
            ->assertSessionDoesntHaveErrors(['marble_area_sq_ft', 'marble_type_id', 'marble_depth_in'])
            ->assertRedirect();

        $quote = PavingQuotation::query()->where('client_name', 'Marble Valid Area')->first();
        $this->assertNotNull($quote);
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertTrue((bool) ($inputs['marble_enabled'] ?? false));
        $this->assertEqualsWithDelta(50.0, (float) ($inputs['marble_area_sq_ft'] ?? 0), 0.01);

        $edit = $this->get(route('admin.quotations.paving.edit', $quote))
            ->assertOk()
            ->getContent();
        $area = $this->elementById($edit, 'pq-marble-area');
        $this->assertFalse($area->hasAttribute('disabled'));
        $this->assertSame('0.1', $area->getAttribute('min'));
        $this->assertTrue($area->hasAttribute('required'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pavingPayload(array $overrides = []): array
    {
        $stoneId = PavingStoneType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($stoneId);

        return array_merge([
            'save_intent' => 'draft',
            'stone_entries' => [[
                'stone_type_id' => $stoneId,
                'input_mode' => 'sqft',
                'area_sq_ft' => 80,
            ]],
            'helper_hourly_rate' => 40,
            'owner_hourly_rate' => 80,
            'mini_skid_days' => 1,
            'plate_compactor_days' => 1,
            'profit_margin' => 5,
            'waste_factor' => 5,
            'excavation_depth_in' => 12,
            'bedding_sand_depth_in' => 1,
            'hst_rate' => 13,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }

    private function elementById(string $html, string $id): \DOMElement
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $nodes = (new \DOMXPath($dom))->query('//*[@id="'.htmlspecialchars($id, ENT_QUOTES).'"]');
        $this->assertNotFalse($nodes);
        $this->assertGreaterThan(0, $nodes->length, $id.' missing');
        $el = $nodes->item(0);
        $this->assertInstanceOf(\DOMElement::class, $el);

        return $el;
    }
}
