<?php

namespace Tests\Feature;

use App\Models\FlooringQuotation;
use App\Models\FlooringSettings;
use App\Models\FlooringType;
use App\Models\SharedCrewRate;
use App\Models\SharedWork;
use App\Models\User;
use App\Services\FlooringQuotationCalculator;
use App\Services\SharedWorkCatalog;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('Show as reference on', false)
            ->assertSee('data-cp-money-entry="direct"', false)
            ->assertSee('hrs per each', false)
            ->getContent();

        $blade = file_get_contents(resource_path('views/admin/quotations/shared_prices/edit.blade.php'));
        $this->assertStringContainsString('<x-admin.info-tip', $blade);
        $this->assertStringNotContainsString('.sp-add label {', $blade);
        $this->assertStringContainsString('fieldset class="sp-add__consumers"', $blade);
        $this->assertStringContainsString('data-submitting', $blade);
        $this->assertStringNotContainsString('class="sp-work-key"', $blade);
        $this->assertStringContainsString('reference-only', $html);
        $this->assertStringContainsString('shoe_install', $html);
        $ottawa = file_get_contents(database_path('migrations/2026_09_27_173000_apply_ottawa_trim_pricing_and_new_works.php'));
        $this->assertStringContainsString("unset(\$payload['label'])", $ottawa);
    }

    public function test_can_add_item_to_a_group_with_stable_custom_key(): void
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
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $created = SharedWork::query()->where('label', 'Custom stair return')->first();
        $this->assertNotNull($created);
        $this->assertSame('flooring', $created->group_key);
        $this->assertSame('custom_stair_return', $created->key);
        $this->assertSame('each', $created->unit);
        $this->assertEqualsWithDelta(1.25, (float) $created->rate, 0.0001);
        $this->assertEqualsWithDelta(45.0, (float) $created->flat_rate, 0.0001);
        $this->assertEqualsCanonicalizing(['flooring', 'basement'], $created->consumers);
        $this->assertTrue($created->isCustom());

        SharedWorkCatalog::forgetCache();
        $this->assertContains($created->key, SharedWorkCatalog::worksForConsumer('flooring'));

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Custom stair return', false)
            ->assertSee('Reference', false)
            ->getContent();
        $this->assertStringContainsString('Reference only', $html);
        $this->assertStringNotContainsString('class="sp-work-key"', $html);

        $this->get(route('admin.quotations.flooring.price-configuration'))
            ->assertOk()
            ->assertSee('Custom stair return', false);
    }

    public function test_typing_4_75_saves_4_75_and_keeps_cost_when_sell_is_filled(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'trim' => [
                    [
                        'label' => 'Extra return',
                        'unit' => 'lf',
                        'rate' => '4.75',
                        'flat_rate' => '6.50',
                        'consumers' => ['trimming'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionDoesntHaveErrors();

        $created = SharedWork::query()->where('label', 'Extra return')->first();
        $this->assertNotNull($created);
        $this->assertEqualsWithDelta(4.75, (float) $created->rate, 0.0001);
        $this->assertEqualsWithDelta(6.50, (float) $created->flat_rate, 0.0001);
        $this->assertNotEquals(4.75 / 100, (float) $created->rate);
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
        $this->assertSame('custom_shoe_install', $created->key);
        $this->assertNotSame('shoe_install', $created->key);
        $this->assertTrue(SharedWork::query()->where('key', 'shoe_install')->exists());
    }

    public function test_normalizes_names_and_transliterates_keys(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'doors' => [
                    [
                        'label' => '  Café door.  ',
                        'unit' => 'each',
                        'rate' => '1.00',
                        'flat_rate' => '',
                        'consumers' => ['trimming'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $created = SharedWork::query()->where('label_normalized', 'café door')->first()
            ?? SharedWork::query()->where('label_normalized', 'cafe door')->first();
        $this->assertNotNull($created);
        $this->assertSame('Café door', $created->label);
        $this->assertTrue(str_starts_with($created->key, 'custom_'));
        $this->assertStringContainsString('cafe', $created->key);

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'tile' => [
                    [
                        'label' => '安装地板',
                        'unit' => 'sf',
                        'rate' => '',
                        'flat_rate' => '8.00',
                        'consumers' => ['tile'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $cjk = SharedWork::query()->where('label', '安装地板')->first();
        $this->assertNotNull($cjk);
        $this->assertTrue(str_starts_with($cjk->key, 'custom_'));
        $this->assertTrue($cjk->isCustom());
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

    public function test_double_submit_of_the_same_new_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        $payload = $this->basePayload([
            'new_works' => [
                'painting' => [
                    [
                        'label' => 'Accent wall glaze',
                        'unit' => 'sf',
                        'rate' => '2.00',
                        'flat_rate' => '3.00',
                        'consumers' => ['painting'],
                    ],
                ],
            ],
        ]);

        $this->put(route('admin.quotations.shared-prices.update'), $payload)
            ->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $payload)
            ->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors();

        $this->assertSame(1, SharedWork::query()->where('label_normalized', 'accent wall glaze')->count());
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
        $this->assertSame('custom_install_flooring', $created->key);
    }

    public function test_rejects_empty_name_when_prices_are_typed_or_rename_is_cleared(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'new_works' => [
                    'flooring' => [
                        [
                            'label' => '   ',
                            'unit' => 'each',
                            'rate' => '2.00',
                            'flat_rate' => '9.00',
                            'consumers' => ['flooring'],
                        ],
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['new_works.flooring.0.label']);

        $work = SharedWork::query()->where('key', 'transition')->firstOrFail();
        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'works' => [
                    $work->id => [
                        'id' => $work->id,
                        'label' => '',
                        'rate' => $work->rate,
                        'flat_rate' => $work->flat_rate,
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['works.'.$work->id.'.label']);

        $work->refresh();
        $this->assertNotSame('', trim((string) $work->label));
    }

    public function test_rejects_prices_at_or_above_one_million(): void
    {
        $this->actingAsAdmin();
        $work = SharedWork::query()->where('key', 'transition')->firstOrFail();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'works' => [
                    $work->id => [
                        'id' => $work->id,
                        'label' => $work->label,
                        'rate' => '1000000',
                        'flat_rate' => $work->flat_rate,
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['works.'.$work->id.'.rate']);

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'new_works' => [
                    'flooring' => [
                        [
                            'label' => 'Huge number',
                            'unit' => 'each',
                            'rate' => '1',
                            'flat_rate' => '1000000.00',
                            'consumers' => ['flooring'],
                        ],
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['new_works.flooring.0.flat_rate']);

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'flooring' => [
                    [
                        'label' => 'Max allowed',
                        'unit' => 'each',
                        'rate' => '999999.99',
                        'flat_rate' => '1.00',
                        'consumers' => ['flooring'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionDoesntHaveErrors();

        $this->assertNotNull(SharedWork::query()->where('label', 'Max allowed')->first());
    }

    public function test_friendly_max_length_message_and_escapes_names(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.shared-prices.edit'))
            ->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
                'new_works' => [
                    'flooring' => [
                        [
                            'label' => str_repeat('a', 161),
                            'unit' => 'each',
                            'rate' => '1',
                            'flat_rate' => '',
                            'consumers' => ['flooring'],
                        ],
                    ],
                ],
            ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHasErrors(['new_works.flooring.0.label']);

        $this->assertStringContainsString(
            'The name may not be longer than 160 characters.',
            (string) session('errors')->first('new_works.flooring.0.label')
        );

        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'new_works' => [
                'flooring' => [
                    [
                        'label' => '<script>alert(1)</script>',
                        'unit' => 'each',
                        'rate' => '1.00',
                        'flat_rate' => '',
                        'consumers' => ['flooring'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $html = $this->get(route('admin.quotations.shared-prices.edit'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
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
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $work->refresh();
        $this->assertSame($originalKey, $work->key);
        $this->assertSame('Install primed shoe', $work->label);
        $this->assertEquals($originalRate, $work->flat_rate);

        SharedWorkCatalog::forgetCache();
        $this->assertSame('Install primed shoe', SharedWorkCatalog::work('shoe_install')?->label);
        $this->assertContains('shoe_install', SharedWorkCatalog::worksForConsumer('flooring'));
    }

    public function test_rename_does_not_change_saved_quote_price_even_with_a_decoy_name(): void
    {
        $this->actingAsAdmin();

        $shoe = SharedWork::query()->where('key', 'shoe_install')->firstOrFail();
        $oldLabel = $shoe->label;
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
            'new_works' => [
                'trim' => [
                    [
                        'label' => $oldLabel,
                        'unit' => 'lf',
                        'rate' => '',
                        'flat_rate' => '99.0000',
                        'consumers' => ['flooring', 'trimming'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $shoe->refresh();
        $this->assertSame('shoe_install', $shoe->key);
        $this->assertSame('Install decorative shoe profile', $shoe->label);
        $this->assertEqualsWithDelta(5.25, (float) $shoe->flat_rate, 0.0001);

        $decoy = SharedWork::query()->where('label', $oldLabel)->where('key', '!=', 'shoe_install')->first();
        $this->assertNotNull($decoy, 'Decoy row with the old name must exist so a label lookup would fail');
        $this->assertEqualsWithDelta(99.0, (float) $decoy->flat_rate, 0.0001);

        SharedWorkCatalog::forgetCache();
        $quote->refresh();
        $after = $calculator->calculate($quote->inputs, FlooringSettings::current(), collect([$type]));

        $this->assertSame($before['summary']['grand_total'], $after['summary']['grand_total']);
        $beforeShoe = collect($before['breakdown'])->firstWhere('key', 'shoe_moulding');
        $afterShoe = collect($after['breakdown'])->firstWhere('key', 'shoe_moulding');
        $this->assertNotNull($beforeShoe);
        $this->assertNotNull($afterShoe);
        $this->assertEqualsWithDelta(210.0, (float) $afterShoe['amount'], 0.01);
        $this->assertNotEquals(40 * 99.0, (float) $afterShoe['amount']);
        $this->assertEqualsWithDelta(5.25, SharedWorkCatalog::flatRate('shoe_install'), 0.0001);
        $this->assertEqualsWithDelta((float) $quote->final_quote_total, (float) $after['summary']['grand_total'], 0.01);
    }

    public function test_staff_can_still_add_and_rename_matching_existing_price_edits(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($staff);

        $work = SharedWork::query()->where('key', 'paint_walls')->firstOrFail();
        $this->put(route('admin.quotations.shared-prices.update'), $this->basePayload([
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'label' => 'Paint wall surfaces',
                    'rate' => $work->rate,
                    'flat_rate' => $work->flat_rate,
                ],
            ],
            'new_works' => [
                'painting' => [
                    [
                        'label' => 'Staff reference glaze',
                        'unit' => 'sf',
                        'rate' => '1.10',
                        'flat_rate' => '2.20',
                        'consumers' => ['painting'],
                    ],
                ],
            ],
        ]))->assertRedirect(route('admin.quotations.shared-prices.edit'))
            ->assertSessionHas('success');

        $work->refresh();
        $this->assertSame('Paint wall surfaces', $work->label);
        $this->assertNotNull(SharedWork::query()->where('label', 'Staff reference glaze')->first());
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
