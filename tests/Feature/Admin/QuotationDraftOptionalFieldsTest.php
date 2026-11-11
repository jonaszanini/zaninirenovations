<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\PavingStoneType;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use App\Support\QuotationReplyToAccept;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\QuoteAcceptance\MakesAcceptableQuotations;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationDraftOptionalFieldsTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;

    /** @var list<string> */
    private const PUBLISH_ONLY = [
        'payment_installments',
        'include_debris_removal',
        QuotationReplyToAccept::FIELD,
    ];

    /**
     * @return array<string, array{0: string, 1: class-string}>
     */
    public function draftCapableTypes(): array
    {
        return [
            'standard' => ['standard', StandardQuotation::class],
            'basement' => ['basement', BasementQuotation::class],
            'millwork' => ['millwork', MillworkQuotation::class],
            'flooring' => ['flooring', FlooringQuotation::class],
            'trimming' => ['trimming', TrimmingQuotation::class],
            'shed' => ['shed', ShedQuotation::class],
            'tile' => ['tile', TileQuotation::class],
            'fence' => ['fence', FenceQuotation::class],
            'paving' => ['paving', PavingQuotation::class],
            'stairs' => ['stairs', StairsQuotation::class],
            'low_deck' => ['low-deck', LowDeckQuotation::class],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function allQuoteTypes(): array
    {
        return [
            'standard' => ['standard'],
            'basement' => ['basement'],
            'millwork' => ['millwork'],
            'flooring' => ['flooring'],
            'trimming' => ['trimming'],
            'shed' => ['shed'],
            'tile' => ['tile'],
            'fence' => ['fence'],
            'paving' => ['paving'],
            'stairs' => ['stairs'],
            'low_deck' => ['low-deck'],
        ];
    }

    /**
     * @dataProvider draftCapableTypes
     *
     * @param  class-string  $modelClass
     */
    public function test_draft_saves_when_publish_only_fields_are_blank(string $type, string $modelClass): void
    {
        $this->actingAsAdmin();

        $clientName = 'Draft optional '.$type;
        $payload = $this->minimalPayload($type, true, $clientName);

        foreach (self::PUBLISH_ONLY as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }

        $this->from(route('admin.quotations.'.$type.'.create'))
            ->post(route('admin.quotations.'.$type.'.store'), $payload)
            ->assertSessionDoesntHaveErrors(self::PUBLISH_ONLY)
            ->assertRedirect();

        $quote = $modelClass::query()->where('client_name', $clientName)->first();
        $this->assertNotNull($quote, "{$type} draft should persist with blank publish-only fields");
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->getRawOriginal('payment_installments'));
        $this->assertNull($quote->getRawOriginal('include_debris_removal'));
        $this->assertNull($quote->getRawOriginal(QuotationReplyToAccept::FIELD));
    }

    /**
     * @dataProvider allQuoteTypes
     */
    public function test_save_quotation_rejects_blank_publish_only_fields(string $type): void
    {
        $this->actingAsAdmin();

        $payload = $this->minimalPayload($type, false, 'Publish optional '.$type);

        foreach (self::PUBLISH_ONLY as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }

        $this->from(route('admin.quotations.'.$type.'.create'))
            ->post(route('admin.quotations.'.$type.'.store'), $payload)
            ->assertRedirect(route('admin.quotations.'.$type.'.create'))
            ->assertSessionHasErrors(self::PUBLISH_ONLY);

        $clientName = 'Publish optional '.$type;
        $this->assertNull(
            $this->modelClassForType($type)::query()->where('client_name', $clientName)->first(),
            "{$type} publish without required fields must not persist a row for this client"
        );
    }

    public function test_email_send_is_blocked_when_publish_only_fields_are_blank(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation([
            'payment_installments' => null,
            'include_debris_removal' => null,
            'ask_reply_to_accept' => null,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertNull($quotation->fresh()->sent_at);
        $this->assertNotSame([], $quotation->fresh()->missingPublishOnlyChoices());
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public function craftedFlagCombos(): array
    {
        $out = [];
        foreach ($this->allQuoteTypes() as $key => [$type]) {
            $out[$key.' publish+is_draft=1'] = [$type, ['save_intent' => 'publish', 'is_draft' => '1']];
            $out[$key.' draft+is_draft=0'] = [$type, ['save_intent' => 'draft', 'is_draft' => '0']];
        }

        return $out;
    }

    /**
     * @dataProvider craftedFlagCombos
     *
     * @param  array<string, string>  $flags
     */
    public function test_crafted_save_flags_cannot_publish_without_required_fields(string $type, array $flags): void
    {
        $this->actingAsAdmin();

        $draftIntent = ($flags['save_intent'] ?? '') === 'draft';
        $clientName = 'Crafted '.$type.' '.($draftIntent ? 'draft' : 'publish');
        $payload = array_merge($this->minimalPayload($type, $draftIntent, $clientName), $flags);

        foreach (self::PUBLISH_ONLY as $field) {
            unset($payload[$field]);
            $this->assertArrayNotHasKey($field, $payload);
        }

        $from = route('admin.quotations.'.$type.'.create');
        $response = $this->from($from)->post(route('admin.quotations.'.$type.'.store'), $payload);

        $quote = $this->modelClassForType($type)::query()->where('client_name', $clientName)->first();

        if ($draftIntent) {
            $response->assertSessionDoesntHaveErrors(self::PUBLISH_ONLY)->assertRedirect();
            $this->assertNotNull($quote, "{$type} crafted draft flags should persist as a draft");
            $this->assertTrue((bool) $quote->is_draft, "{$type} crafted draft flags must not publish");
        } else {
            $response->assertRedirect($from)->assertSessionHasErrors(self::PUBLISH_ONLY);
            $this->assertNull($quote, "{$type} crafted publish flags must not persist without required fields");
        }
    }

    public function test_linking_a_draft_without_publish_only_fields_does_not_publish(): void
    {
        $this->actingAsAdmin();

        $quote = BasementQuotation::query()->create([
            'quote_number' => 'BR-LINK-DRAFT-1',
            'client_name' => 'Link Draft Client',
            'is_draft' => true,
            'inputs' => [],
            'calculation' => [],
            'final_quote_total' => 1500,
            'payment_installments' => null,
            'include_debris_removal' => null,
            'ask_reply_to_accept' => null,
        ]);
        $project = \App\Models\ClientProject::factory()->create();

        $this->from(route('admin.quotations.basement.edit', $quote))
            ->post(route('admin.quotations.basement.link', $quote), [
                'client_project_id' => $project->id,
            ])
            ->assertSessionHas('error');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertNull($quote->getRawOriginal('payment_installments'));
        $this->assertNull($quote->getRawOriginal('include_debris_removal'));
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalPayload(string $type, bool $draft, string $clientName): array
    {
        $base = ['client_name' => $clientName];
        $base['save_intent'] = $draft ? 'draft' : 'publish';
        if ($type === 'tile') {
            $base['is_draft'] = $draft ? '1' : '0';
        }

        $withCommute = $type !== 'flooring';

        $extra = match ($type) {
            'standard' => [
                'display_title' => 'Kitchen work',
                'document_type' => 'quotation',
                'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
                'includes_labour' => '1',
                'works' => [['title' => 'Labour', 'labour_use_fixed' => '1', 'labour_fixed_amount' => '100']],
            ],
            'basement' => [
                'area_sqft' => 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'millwork' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
                ]],
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'flooring' => [],
            'trimming' => [
                'enabled_families' => ['floor_wall'],
                'floor_lf' => 80,
                'include_baseboard' => 1,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'painting' => [
                'total_property_sqf' => $draft ? null : 1200,
            ],
            'shed' => [
                'length_ft' => 10,
                'width_ft' => 12,
                'wall_height_ft' => 8,
                'shed_type' => 'gable',
                'foundation' => 'skids',
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'tile' => [
                'total_area_sqft' => 80,
                'waste_margin_percent' => 10,
                'trowel_size' => '1/4x1/4',
                'joint_width_decimal' => 0.125,
                'labor_rate' => 12,
            ],
            'fence' => [
                'fence_height_ft' => 6,
                'board_style' => 'standard',
                'gate_count' => 0,
                'labour_rate_per_ft' => 55,
                'labour_rate_8ft_per_ft' => 60,
                'sides' => [['length_ft' => '20']],
            ],
            'paving' => $this->pavingFields(),
            'stairs' => $draft ? [] : [
                'total_steps' => 12,
            ],
            'low-deck' => [
                'width_ft' => 12,
                'length_ft' => 16,
                'labour_rate_per_sqft' => 10,
            ],
            default => [],
        };

        if ($withCommute) {
            $extra['include_commute'] = 0;
        }

        return array_merge($base, $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function pavingFields(): array
    {
        $stoneId = PavingStoneType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($stoneId);

        return [
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
        ];
    }

    /**
     * @return class-string
     */
    private function modelClassForType(string $type): string
    {
        return match ($type) {
            'standard' => StandardQuotation::class,
            'basement' => BasementQuotation::class,
            'millwork' => MillworkQuotation::class,
            'flooring' => FlooringQuotation::class,
            'trimming' => TrimmingQuotation::class,
            'painting' => PaintingQuotation::class,
            'shed' => ShedQuotation::class,
            'tile' => TileQuotation::class,
            'fence' => FenceQuotation::class,
            'paving' => PavingQuotation::class,
            'stairs' => StairsQuotation::class,
            'low-deck' => LowDeckQuotation::class,
            default => StandardQuotation::class,
        };
    }
}
