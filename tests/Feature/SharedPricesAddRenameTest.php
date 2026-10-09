<?php

namespace Tests\Feature;

use App\Models\FlooringQuotation;
use App\Models\FlooringSettings;
use App\Models\FlooringType;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Services\FlooringQuotationCalculator;
use App\Services\SharedWorkCatalog;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedPricesAddRenameTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_edit_page_shows_rename_and_add_controls(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('name="works[', false)
            ->assertSee('sp-work-label-input', false)
            ->assertSee('Add item', false)
            ->assertSee('new_works[flooring][0][label]', false)
            ->assertSee('new_works[flooring][0][unit]', false)
            ->assertSee('new_works[flooring][0][rate]', false)
            ->assertSee('new_works[flooring][0][flat_rate]', false)
            ->assertSee('Show on quote types', false)
            ->getContent();

        $this->assertStringContainsString('permanent key', $html);
        $this->assertStringContainsString('shoe_install', $html);
    }

    public function test_can_add_item_to_a_group_with_stable_key(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'flooring' => [
                    [
                        'label' => 'Custom stair return',
                        'unit' => 'each',
                        'rate' => '1.2500',
                        'flat_rate' => '45.0000',
                        'consumers' => ['flooring', 'basement'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $created = SharedWork::query()->where('label', 'Custom stair return')->first();
        $this->assertNotNull($created);
        $this->assertSame('flooring', $created->group_key);
        $this->assertSame('custom_stair_return', $created->key);
        $this->assertSame('each', $created->unit);
        $this->assertEqualsWithDelta(1.25, (float) $created->rate, 0.0001);
        $this->assertEqualsWithDelta(45.0, (float) $created->flat_rate, 0.0001);
        $this->assertEqualsCanonicalizing(['flooring', 'basement'], $created->consumers);
        $this->assertFalse(SharedWorkCatalog::isCanonicalKey($created->key));

        SharedWorkCatalog::forgetCache();
        $this->assertContains($created->key, SharedWorkCatalog::worksForConsumer('flooring'));
        $this->assertContains($created->key, SharedWorkCatalog::worksForConsumer('basement'));
        $this->assertNotContains($created->key, SharedWorkCatalog::worksForConsumer('painting'));

        $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Custom stair return', false)
            ->assertSee('custom_stair_return', false);

        $this->get(route('admin.quotations.flooring.price-configuration'))
            ->assertOk()
            ->assertSee('Custom stair return', false);
    }

    public function test_new_item_key_stays_unique_when_slug_exists(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'trim' => [
                    [
                        'label' => 'Shoe install',
                        'unit' => 'lf',
                        'rate' => '',
                        'flat_rate' => '4.1000',
                        'consumers' => ['trimming'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $created = SharedWork::query()->where('label', 'Shoe install')->first();
        $this->assertNotNull($created);
        $this->assertSame('shoe_install_2', $created->key);
        $this->assertNotSame('shoe_install', $created->key);
        $this->assertTrue(SharedWork::query()->where('key', 'shoe_install')->exists());
    }

    public function test_rejects_duplicate_name_in_same_group(): void
    {
        $this->actingAsAdmin();

        $existing = SharedWork::query()->where('key', 'flooring_install')->firstOrFail();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'new_works' => [
                    'flooring' => [
                        [
                            'label' => '  INSTALL FLOORING  ',
                            'unit' => 'sf',
                            'rate' => '',
                            'flat_rate' => '9.00',
                            'consumers' => ['flooring'],
                        ],
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['new_works.flooring.0.label']);

        $this->assertSame(0, SharedWork::query()->where('label', '  INSTALL FLOORING  ')->count());

        $other = SharedWork::query()
            ->where('group_key', 'flooring')
            ->where('id', '!=', $existing->id)
            ->firstOrFail();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'works' => [
                    $other->id => [
                        'id' => $other->id,
                        'label' => $existing->label,
                        'rate' => $other->rate,
                        'flat_rate' => $other->flat_rate,
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['works.'.$other->id.'.label']);

        $other->refresh();
        $this->assertNotSame($existing->label, $other->label);
    }

    public function test_same_name_is_allowed_in_a_different_group(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'tile' => [
                    [
                        'label' => 'Install flooring',
                        'unit' => 'sf',
                        'rate' => '',
                        'flat_rate' => '11.00',
                        'consumers' => ['tile'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionDoesntHaveErrors();

        $created = SharedWork::query()
            ->where('group_key', 'tile')
            ->where('label', 'Install flooring')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame('install_flooring_2', $created->key);
    }

    public function test_rename_keeps_stable_key(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $originalKey = $work->key;
        $originalRate = $work->flat_rate;

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'label' => 'Install primed shoe',
                    'rate' => $work->rate,
                    'flat_rate' => $work->flat_rate,
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionDoesntHaveErrors();

        $work->refresh();
        $this->assertSame($originalKey, $work->key);
        $this->assertSame('Install primed shoe', $work->label);
        $this->assertEquals($originalRate, $work->flat_rate);

        SharedWorkCatalog::forgetCache();
        $this->assertSame('Install primed shoe', SharedWorkCatalog::work('shoe_install')?->label);
        $this->assertContains('shoe_install', SharedWorkCatalog::worksForConsumer('flooring'));
    }

    public function test_rename_does_not_change_saved_quote_price(): void
    {
        $this->actingAsAdmin();

        $shoe = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $shoe->flat_rate = 5.25;
        $shoe->save();
        SharedWorkCatalog::forgetCache();

        $type = FlooringType::query()->where('key', 'lvp_click')->firstOrFail();
        $settings = FlooringSettings::current();
        $inputs = [
            'flooring_type_id' => $type->id,
            'square_footage' => 120,
            'remove_carpet' => false,
            'rescrew_subfloor' => false,
            'install_shoe_moulding' => true,
            'shoe_moulding_linear_ft' => 40,
            'caulk_paint_shoe_moulding' => false,
            'install_baseboard' => false,
            'baseboard_linear_ft' => 0,
            'caulk_paint_baseboard' => false,
            'closet_doors' => false,
            'closet_door_qty' => 0,
            'include_material_details' => false,
        ];

        $calculator = new FlooringQuotationCalculator;
        $before = $calculator->calculate($inputs, $settings, collect([$type]));
        $this->assertEqualsWithDelta(5.25, $settings->shoeInstallRate(), 0.0001);

        $quote = FlooringQuotation::query()->create([
            'quote_number' => FlooringQuotation::generateNumber(),
            'client_name' => 'Rename lock test',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $before,
            'final_quote_total' => $before['summary']['grand_total'],
        ]);

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'works' => [
                $shoe->id => [
                    'id' => $shoe->id,
                    'label' => 'Install decorative shoe profile',
                    'rate' => $shoe->rate,
                    'flat_rate' => '5.2500',
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $shoe->refresh();
        $this->assertSame('shoe_install', $shoe->key);
        $this->assertSame('Install decorative shoe profile', $shoe->label);
        $this->assertEqualsWithDelta(5.25, (float) $shoe->flat_rate, 0.0001);

        SharedWorkCatalog::forgetCache();
        $quote->refresh();
        $after = $calculator->calculate($quote->inputs, FlooringSettings::current(), collect([$type]));

        $this->assertSame($before['summary']['grand_total'], $after['summary']['grand_total']);
        $this->assertSame($before['summary']['labor_pre_hst'], $after['summary']['labor_pre_hst']);
        $beforeShoe = collect($before['breakdown'])->firstWhere('key', 'shoe_moulding');
        $afterShoe = collect($after['breakdown'])->firstWhere('key', 'shoe_moulding');
        $this->assertNotNull($beforeShoe);
        $this->assertNotNull($afterShoe);
        $this->assertEqualsWithDelta((float) $beforeShoe['amount'], (float) $afterShoe['amount'], 0.01);
        $this->assertEqualsWithDelta(210.0, (float) $afterShoe['amount'], 0.01);
        $this->assertEqualsWithDelta(5.25, SharedWorkCatalog::flatRate('shoe_install'), 0.0001);
        $this->assertSame((string) $quote->final_quote_total, (string) $after['summary']['grand_total']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function basePayload(array $extra = []): array
    {
        $crew = SharedCrewRate::current();

        return array_merge([
            'owner_hourly_rate' => number_format((float) $crew->owner_hourly_rate, 2, '.', ''),
            'helper_hourly_rate' => number_format((float) $crew->helper_hourly_rate, 2, '.', ''),
            'hours_per_day' => (string) $crew->hours_per_day,
        ], $extra);
    }
}
