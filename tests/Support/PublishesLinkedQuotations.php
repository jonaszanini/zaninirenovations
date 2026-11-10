<?php

namespace Tests\Support;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\FlooringType;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\PaintingPaintLine;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\PavingStoneType;
use App\Models\ShedQuotation;
use App\Models\StairsMaterialType;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use App\Services\StairsQuotationCalculator;

trait PublishesLinkedQuotations
{
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
            'painting' => ['painting'],
            'shed' => ['shed'],
            'tile' => ['tile'],
            'fence' => ['fence'],
            'paving' => ['paving'],
            'stairs' => ['stairs'],
            'low_deck' => ['low-deck'],
        ];
    }

    protected function quoteTypeLabel(string $type): string
    {
        return match ($type) {
            'low-deck' => 'Low deck',
            default => ucfirst($type),
        };
    }

    protected function quoteSourceType(string $type): string
    {
        return match ($type) {
            'low-deck' => ClientProjectQuotation::SOURCE_LOW_DECK,
            default => $type,
        };
    }

    /**
     * @return class-string
     */
    protected function quoteModelClass(string $type): string
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

    /**
     * @return array{0: object, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    protected function publishLinkedQuote(string $type): array
    {
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $payload = $this->publishPayload($type, $project->client_name);
        $storeRoute = $type === 'tile'
            ? route('admin.quotations.tile.store')
            : route('admin.quotations.'.$type.'.store', ['project' => $project->id]);
        if ($type === 'tile') {
            $payload['client_project_id'] = $project->id;
        }

        $this->post($storeRoute, $payload)->assertRedirect();
        $quote = $this->quoteModelClass($type)::query()
            ->where('client_name', $project->client_name)
            ->latest('id')
            ->firstOrFail();
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', $this->quoteSourceType($type))
            ->where('source_id', $quote->id)
            ->first();
        if ($cpq === null && $quote->isLinked() === false) {
            $this->post(route('admin.quotations.'.$type.'.link', $quote), [
                'client_project_id' => $project->id,
            ]);
            $quote->refresh();
            $cpq = ClientProjectQuotation::query()
                ->where('source_type', $this->quoteSourceType($type))
                ->where('source_id', $quote->id)
                ->first();
        }
        $this->assertNotNull($cpq, $type.' client quotation should exist after publish+link');

        return [$quote, $cpq, $payload, route('admin.quotations.'.$type.'.update', $quote)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function publishPayload(string $type, string $clientName): array
    {
        $base = [
            'save_intent' => 'publish',
            'client_name' => $clientName,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ];
        if ($type !== 'flooring') {
            $base['include_commute'] = 0;
        }
        if ($type === 'tile') {
            $base['is_draft'] = '0';
        }

        return array_merge($base, $this->typeFields($type, false));
    }

    /**
     * @param  array<string, mixed>  $publish
     * @return array<string, mixed>
     */
    protected function draftUpdatePayload(string $type, object $quote, array $publish): array
    {
        $payload = array_merge($publish, [
            'save_intent' => 'draft',
        ]);
        if ($type === 'tile') {
            $payload['is_draft'] = '1';
        }

        return array_merge($payload, $this->typeFields($type, true));
    }

    /**
     * @return array<string, mixed>
     */
    protected function typeFields(string $type, bool $draftMutation): array
    {
        return match ($type) {
            'standard' => [
                'display_title' => 'Kitchen work',
                'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
                'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
                'includes_labour' => '1',
                'works' => [[
                    'title' => 'Labour',
                    'labour_use_fixed' => '1',
                    'labour_fixed_amount' => $draftMutation ? '500' : '100',
                ]],
            ],
            'basement' => [
                'area_sqft' => $draftMutation ? 400 : 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'include_demolition' => 0,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'millwork' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [[
                        'qty' => $draftMutation ? 3 : 1,
                        'length' => 24,
                        'depth' => 10,
                        'unit' => 'in',
                    ]],
                ]],
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'flooring' => [
                'flooring_type_id' => FlooringType::query()->orderBy('id')->value('id'),
                'square_footage' => $draftMutation ? 80 : 200,
            ],
            'trimming' => [
                'enabled_families' => ['floor_wall'],
                'floor_lf' => $draftMutation ? 40 : 80,
                'include_baseboard' => 1,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'painting' => [
                'total_property_sqf' => $draftMutation ? 600 : 1200,
                'paint_line_id' => PaintingPaintLine::query()->orderBy('id')->value('id'),
            ],
            'shed' => [
                'length_ft' => $draftMutation ? 8 : 10,
                'width_ft' => 12,
                'wall_height_ft' => 8,
                'shed_type' => 'gable',
                'foundation' => 'skids',
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ],
            'tile' => [
                'total_area_sqft' => $draftMutation ? 20 : 80,
                'waste_margin_percent' => 10,
                'trowel_size' => '1/4x1/4',
                'joint_width_decimal' => 0.125,
                'labor_rate' => $draftMutation ? 40 : 12,
            ],
            'fence' => [
                'sides' => [['length_ft' => $draftMutation ? 48 : 24]],
                'fence_height_ft' => 6,
                'board_style' => 'standard',
                'gate_count' => 0,
                'labour_rate_per_ft' => 55,
                'labour_rate_8ft_per_ft' => 60,
                'existing_post_labour_credit' => 30,
                'include_labour_hst' => true,
            ],
            'paving' => [
                'stone_entries' => [[
                    'stone_type_id' => PavingStoneType::query()->orderBy('sort_order')->value('id'),
                    'input_mode' => 'sqft',
                    'area_sq_ft' => $draftMutation ? 40 : 80,
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
            ],
            'stairs' => [
                'total_steps' => $draftMutation ? 8 : 12,
                'segments' => [[
                    'stair_type' => 'standard',
                    'qty' => $draftMutation ? 8 : 12,
                    'open_sides' => StairsQuotationCalculator::OPEN_NONE,
                ]],
                'material_type_id' => StairsMaterialType::query()->orderBy('sort_order')->value('id'),
                'material_price' => $draftMutation ? 40 : 80,
            ],
            'low-deck' => [
                'width_ft' => $draftMutation ? 8 : 12,
                'length_ft' => $draftMutation ? 10 : 16,
                'labour_rate_per_sqft' => 10,
            ],
            default => [],
        };
    }

    protected function quoteCalculatorTotal(string $type, object $quote): float
    {
        return $this->storedQuoteTotal($quote);
    }

    /**
     * Persisted grand total column only — not a calculator call.
     */
    protected function storedQuoteTotal(object $quote): float
    {
        foreach (['final_quote_total', 'grand_total'] as $field) {
            if (isset($quote->{$field}) && $quote->{$field} !== null) {
                return (float) $quote->{$field};
            }
        }

        return 0.0;
    }

    /**
     * Independent expected qty + grand total for typeFields() fixtures.
     * Standard labour is labour_fixed_amount × 13% HST. Other totals are
     * pinned literals for this fixture (not derived from the calculator).
     *
     * @return array{
     *   publishQty: float,
     *   publishTotal: float,
     *   httpEditQty: float,
     *   httpEditTotal: float,
     *   browserEditQty: float,
     *   browserEditTotal: float,
     *   scopeWord: string
     * }
     */
    protected function lifecycleFixtureExpected(string $type): array
    {
        $hst = 1.13;

        return match ($type) {
            'standard' => [
                'publishQty' => 100.0,
                'publishTotal' => round(100 * $hst, 2),
                'httpEditQty' => 500.0,
                'httpEditTotal' => round(500 * $hst, 2),
                'browserEditQty' => 500.0,
                'browserEditTotal' => round(500 * $hst, 2),
                'scopeWord' => 'Labour',
            ],
            'basement' => [
                'publishQty' => 850.0,
                'publishTotal' => 31434.49,
                'httpEditQty' => 400.0,
                'httpEditTotal' => 17862.36,
                'browserEditQty' => 400.0,
                'browserEditTotal' => 17862.36,
                'scopeWord' => 'basement',
            ],
            'millwork' => [
                'publishQty' => 1.0,
                'publishTotal' => 109.05,
                'httpEditQty' => 3.0,
                'httpEditTotal' => 192.67,
                'browserEditQty' => 3.0,
                'browserEditTotal' => 192.67,
                'scopeWord' => 'shelf',
            ],
            'flooring' => [
                'publishQty' => 200.0,
                'publishTotal' => 960.50,
                'httpEditQty' => 80.0,
                'httpEditTotal' => 384.20,
                'browserEditQty' => 80.0,
                'browserEditTotal' => 384.20,
                'scopeWord' => 'floor',
            ],
            'trimming' => [
                'publishQty' => 80.0,
                'publishTotal' => 700.49,
                'httpEditQty' => 40.0,
                'httpEditTotal' => 403.98,
                'browserEditQty' => 40.0,
                'browserEditTotal' => 403.98,
                'scopeWord' => 'baseboard',
            ],
            'shed' => [
                'publishQty' => 10.0,
                'publishTotal' => 8545.18,
                'httpEditQty' => 8.0,
                'httpEditTotal' => 7725.15,
                'browserEditQty' => 8.0,
                'browserEditTotal' => 7725.15,
                'scopeWord' => 'shed',
            ],
            'tile' => [
                'publishQty' => 80.0,
                'publishTotal' => 1193.28,
                'httpEditQty' => 20.0,
                'httpEditTotal' => 994.40,
                'browserEditQty' => 20.0,
                'browserEditTotal' => 298.32,
                'scopeWord' => 'tile',
            ],
            'fence' => [
                'publishQty' => 24.0,
                'publishTotal' => 1491.60,
                'httpEditQty' => 48.0,
                'httpEditTotal' => 2983.20,
                'browserEditQty' => 48.0,
                'browserEditTotal' => 2983.20,
                'scopeWord' => 'fence',
            ],
            'paving' => [
                'publishQty' => 80.0,
                'publishTotal' => 4179.03,
                'httpEditQty' => 40.0,
                'httpEditTotal' => 2938.89,
                'browserEditQty' => 40.0,
                'browserEditTotal' => 2938.89,
                'scopeWord' => 'Paving',
            ],
            'stairs' => [
                'publishQty' => 12.0,
                'publishTotal' => 90.40,
                'httpEditQty' => 8.0,
                'httpEditTotal' => 45.20,
                'browserEditQty' => 8.0,
                'browserEditTotal' => 45.20,
                'scopeWord' => 'stair',
            ],
            'low-deck' => [
                'publishQty' => 16.0,
                'publishTotal' => 2237.40,
                'httpEditQty' => 10.0,
                'httpEditTotal' => 971.80,
                'browserEditQty' => 10.0,
                'browserEditTotal' => 1423.80,
                'scopeWord' => 'deck',
            ],
            default => [
                'publishQty' => 0.0,
                'publishTotal' => 0.0,
                'httpEditQty' => 0.0,
                'httpEditTotal' => 0.0,
                'browserEditQty' => 0.0,
                'browserEditTotal' => 0.0,
                'scopeWord' => 'quote',
            ],
        };
    }

    protected function storedLifecycleQty(string $type, object $quote): float
    {
        $inputs = is_array($quote->inputs ?? null) ? $quote->inputs : [];

        return (float) match ($type) {
            'standard' => data_get($quote->works, '0.labour_fixed_amount', $quote->labour_fixed_amount),
            'basement' => $inputs['area_sqft'] ?? 0,
            'millwork' => data_get($inputs, 'pieces.0.sets.0.qty', 0),
            'flooring' => $inputs['square_footage'] ?? 0,
            'trimming' => $inputs['floor_lf'] ?? 0,
            'shed' => $inputs['length_ft'] ?? 0,
            'tile' => $inputs['total_area_sqft'] ?? 0,
            'fence' => data_get($quote->sides, '0.length_ft', 0),
            'paving' => data_get($inputs, 'stone_entries.0.area_sq_ft', 0),
            'stairs' => $inputs['total_steps'] ?? 0,
            'low-deck' => $quote->length_ft ?? 0,
            default => 0,
        };
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectQuotation, 2: object}
     */
    protected function makeLinkedSentSource(string $type): array
    {
        $project = ClientProject::factory()->create([
            'name' => 'Kitchen renovation',
            'client_name' => 'Ada Client',
            'client_email' => 'ada@example.test',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $quoteNumber = strtoupper(substr($type, 0, 2)).'-REV-'.random_int(1000, 9999);
        $source = $this->createPublishedSource($type, $project, $quoteNumber);

        $cpq = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => $this->quoteTypeLabel($type).' quotation',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-REV-'.random_int(1000, 9999),
            'source_type' => $this->quoteSourceType($type),
            'source_id' => $source->id,
            'source_snapshot' => ['quote_number' => $quoteNumber],
            'sent_at' => now(),
            'ask_reply_to_accept' => 1,
            'items' => [[
                'description' => 'Labour and materials',
                'amount' => 1000,
                'apply_hst' => true,
            ]],
        ]);

        return [$project, $cpq, $source];
    }

    protected function createPublishedSource(string $type, ClientProject $project, string $quoteNumber): object
    {
        $common = [
            'quote_number' => $quoteNumber,
            'client_name' => $project->client_name,
            'is_draft' => false,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => 1,
        ];

        return match ($type) {
            'standard' => StandardQuotation::query()->create(array_merge($common, [
                'display_title' => 'Kitchen work',
                'materials' => [],
                'grand_total' => 1130,
            ])),
            'fence' => FenceQuotation::query()->create(array_merge($common, [
                'sides' => [['length_ft' => 20]],
                'calculation' => [],
                'grand_total' => 1500,
            ])),
            'low-deck' => LowDeckQuotation::query()->create(array_merge($common, [
                'width_ft' => 12,
                'length_ft' => 16,
                'calculation' => [],
                'grand_total' => 1800,
            ])),
            'basement' => BasementQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 1500,
            ])),
            'millwork' => MillworkQuotation::query()->create(array_merge($common, [
                'inputs' => ['pieces' => []],
                'calculation' => [],
                'final_quote_total' => 900,
            ])),
            'flooring' => FlooringQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 800,
            ])),
            'trimming' => TrimmingQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 700,
            ])),
            'painting' => PaintingQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 600,
            ])),
            'shed' => ShedQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 2200,
            ])),
            'tile' => TileQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 1100,
            ])),
            'paving' => PavingQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 1300,
            ])),
            'stairs' => StairsQuotation::query()->create(array_merge($common, [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 950,
            ])),
            default => StandardQuotation::query()->create(array_merge($common, [
                'display_title' => 'Kitchen work',
                'materials' => [],
                'grand_total' => 1130,
            ])),
        };
    }
}
