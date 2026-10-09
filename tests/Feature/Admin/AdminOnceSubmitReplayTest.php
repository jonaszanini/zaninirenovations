<?php

namespace Tests\Feature\Admin;

use App\Models\AdminCalendarEvent;
use App\Models\BasementQuotation;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Client;
use App\Models\ClientTag;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\Helper;
use App\Models\LowDeckQuotation;
use App\Models\Material;
use App\Models\MillworkQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\PavingStoneType;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\SupplierSite;
use App\Models\Task;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use App\Models\Vehicle;
use App\Support\AdminOnceSubmitToken;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class AdminOnceSubmitReplayTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_replay_does_not_create_a_second_record_on_the_other_create_endpoints(): void
    {
        $this->actingAsAdmin();

        $seen = [];
        foreach ($this->createEndpointCases() as $label => $case) {
            $seen[] = $label;
            $token = bin2hex(random_bytes(16));
            $firstValue = $case['first'];
            $replayValue = $case['replay'];
            $column = $case['column'];
            $model = $case['model'];

            $firstPayload = ($case['payload'])($firstValue) + [
                AdminOnceSubmitToken::FIELD => $token,
                '_hotkey_save' => '1',
            ];
            $replayPayload = ($case['payload'])($replayValue) + [
                AdminOnceSubmitToken::FIELD => $token,
                '_hotkey_save' => '1',
            ];

            $from = $case['from'] ?? null;
            $first = $from
                ? $this->from($from)->post($case['route'], $firstPayload)
                : $this->post($case['route'], $firstPayload);
            $this->assertFalse(
                $first->isRedirect() && session()->has('errors'),
                $label.' first create failed validation: '.json_encode(session('errors'))
            );
            $this->assertSame(
                1,
                $model::query()->where($column, $firstValue)->count(),
                $label.' first create must insert one row'
            );

            $second = $this->from($from ?: url('/admin'))
                ->post($case['route'], $replayPayload);
            $second->assertRedirect();
            $second->assertSessionHas('warning');
            $warning = (string) session('warning');
            $this->assertStringContainsString('already submitted', $warning, $label);
            $this->assertStringContainsString('No second record was created', $warning, $label);
            $this->assertSame(
                1,
                $model::query()->where($column, $firstValue)->count(),
                $label.' replay must keep the first row'
            );
            $this->assertSame(
                0,
                $model::query()->where($column, $replayValue)->count(),
                $label.' replay must not insert a second row'
            );
        }

        $this->assertCount(24, $seen, 'expected replay coverage for the 24 non-project create endpoints');
    }

    public function test_calendar_personal_event_replay_returns_409_and_one_event(): void
    {
        $this->actingAsAdmin();

        $token = bin2hex(random_bytes(16));
        $payload = [
            'title' => 'Replay holiday',
            'description' => 'Off',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-26',
            AdminOnceSubmitToken::FIELD => $token,
        ];

        $first = $this->postJson(route('admin.calendar.personal-events.store'), $payload);
        $first->assertCreated();
        $first->assertJsonPath('ok', true);

        $second = $this->postJson(route('admin.calendar.personal-events.store'), $payload);
        $second->assertStatus(409);
        $second->assertJsonPath('ok', false);
        $second->assertJsonPath('message', 'This form was already submitted. No second record was created.');

        $this->assertSame(1, AdminCalendarEvent::query()->where('title', 'Replay holiday')->count());
    }

    public function test_replay_redirect_keeps_the_posted_input(): void
    {
        $this->actingAsAdmin();

        $token = bin2hex(random_bytes(16));
        $first = [
            'name' => 'Replay keep first',
            'contacted_via' => 'email',
            'client_name' => 'Pat Client',
            'billing_type' => 'fixed',
            'send_client_email' => '0',
            'confirm_duplicate_project' => '1',
            '_hotkey_save' => '1',
            AdminOnceSubmitToken::FIELD => $token,
        ];

        $this->post(route('admin.client-projects.store'), $first)
            ->assertRedirect()
            ->assertSessionHas('success');

        $replay = $first;
        $replay['name'] = 'Replay typed name';
        $replay['client_name'] = 'Typed on second Ctrl+S';

        $this->from(route('admin.client-projects.create'))
            ->post(route('admin.client-projects.store'), $replay)
            ->assertRedirect(route('admin.client-projects.create'))
            ->assertSessionHas('warning')
            ->assertSessionHasInput('name', 'Replay typed name')
            ->assertSessionHasInput('client_name', 'Typed on second Ctrl+S');

        $this->assertSame(1, \App\Models\ClientProject::query()->where('name', 'Replay keep first')->count());
        $this->assertSame(0, \App\Models\ClientProject::query()->where('name', 'Replay typed name')->count());
    }

    /**
     * @return array<string, array{route: string, from?: string, model: class-string, column: string, first: string, replay: string, payload: callable}>
     */
    private function createEndpointCases(): array
    {
        $quoteExtras = [
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $category = ProjectCategory::query()->create([
            'name' => 'Replay Portfolio Cat',
            'slug' => 'replay-portfolio-cat-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'sort_order' => 0,
        ]);
        $pavingStone = PavingStoneType::query()->orderBy('sort_order')->firstOrFail();

        return [
            'fence' => [
                'route' => route('admin.quotations.fence.store'),
                'from' => route('admin.quotations.fence.create'),
                'model' => FenceQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay fence A',
                'replay' => 'Replay fence B',
                'payload' => fn (string $name) => [
                    'sides' => [['length_ft' => 24]],
                    'fence_height_ft' => 6,
                    'board_style' => 'standard',
                    'gate_count' => 0,
                    'labour_rate_per_ft' => 55,
                    'labour_rate_8ft_per_ft' => 60,
                    'existing_post_labour_credit' => 30,
                    'include_labour_hst' => true,
                    'client_name' => $name,
                ] + $quoteExtras,
            ],
            'tile' => [
                'route' => route('admin.quotations.tile.store'),
                'from' => route('admin.quotations.tile.create'),
                'model' => TileQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay tile A',
                'replay' => 'Replay tile B',
                'payload' => fn (string $name) => [
                    'client_name' => $name,
                    'total_area_sqft' => 80,
                    'waste_margin_percent' => 10,
                    'trowel_size' => '1/4x1/4',
                    'joint_width_decimal' => 0.125,
                    'labor_rate' => 12,
                    'is_draft' => '1',
                ] + $quoteExtras,
            ],
            'portfolio' => [
                'route' => route('admin.portfolio.store'),
                'from' => route('admin.portfolio.create'),
                'model' => Project::class,
                'column' => 'title',
                'first' => 'Replay portfolio A',
                'replay' => 'Replay portfolio B',
                'payload' => fn (string $name) => [
                    'title' => $name,
                    'description' => 'Replay portfolio description',
                    'project_category_ids' => [$category->id],
                    'completed_on' => '2026-09-01',
                ],
            ],
            'blog-post' => [
                'route' => route('admin.blog.posts.store'),
                'from' => route('admin.blog.posts.create'),
                'model' => BlogPost::class,
                'column' => 'title',
                'first' => 'Replay blog post A',
                'replay' => 'Replay blog post B',
                'payload' => fn (string $name) => [
                    'title' => $name,
                    'html_content' => '<p>Replay</p>',
                    'status' => BlogPost::STATUS_DRAFT,
                    'author_name' => 'Jonas',
                    'category_ids' => [],
                    'tag_names' => '',
                ],
            ],
            'blog-tag' => [
                'route' => route('admin.blog.tags.store'),
                'from' => route('admin.blog.tags.create'),
                'model' => BlogTag::class,
                'column' => 'name',
                'first' => 'Replay blog tag A',
                'replay' => 'Replay blog tag B',
                'payload' => fn (string $name) => ['name' => $name],
            ],
            'low-deck' => [
                'route' => route('admin.quotations.low-deck.store'),
                'from' => route('admin.quotations.low-deck.create'),
                'model' => LowDeckQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay low deck A',
                'replay' => 'Replay low deck B',
                'payload' => fn (string $name) => [
                    'client_name' => $name,
                    'width_ft' => 12,
                    'length_ft' => 16,
                    'labour_rate_per_sqft' => 10,
                ] + $quoteExtras,
            ],
            'task' => [
                'route' => route('admin.tasks.store'),
                'from' => route('admin.tasks.index'),
                'model' => Task::class,
                'column' => 'title',
                'first' => 'Replay task A',
                'replay' => 'Replay task B',
                'payload' => fn (string $name) => ['title' => $name],
            ],
            'blog-category' => [
                'route' => route('admin.blog.categories.store'),
                'from' => route('admin.blog.categories.create'),
                'model' => BlogCategory::class,
                'column' => 'name',
                'first' => 'Replay blog cat A',
                'replay' => 'Replay blog cat B',
                'payload' => fn (string $name) => ['name' => $name],
            ],
            'supplier' => [
                'route' => route('admin.supplier-sites.store'),
                'from' => route('admin.supplier-sites.create'),
                'model' => SupplierSite::class,
                'column' => 'name',
                'first' => 'Replay supplier A',
                'replay' => 'Replay supplier B',
                'payload' => fn (string $name) => [
                    'name' => $name,
                    'url' => 'https://example.com/'.$name,
                    'tags_input' => 'lumber',
                    'phone' => '6135550100',
                    'email' => 'orders@example.com',
                    'address' => '1 Industrial Rd',
                    'is_favorite' => '0',
                ],
            ],
            'painting' => [
                'route' => route('admin.quotations.painting.store'),
                'from' => route('admin.quotations.painting.create'),
                'model' => PaintingQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay painting A',
                'replay' => 'Replay painting B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'draft',
                    'client_name' => $name,
                ] + $quoteExtras,
            ],
            'paving' => [
                'route' => route('admin.quotations.paving.store'),
                'from' => route('admin.quotations.paving.create'),
                'model' => PavingQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay paving A',
                'replay' => 'Replay paving B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'draft',
                    'client_name' => $name,
                    'stone_entries' => [[
                        'stone_type_id' => $pavingStone->id,
                        'input_mode' => 'sqft',
                        'area_sq_ft' => 168,
                    ]],
                    'mini_skid_days' => 1,
                    'plate_compactor_days' => 1,
                    'profit_margin' => 5,
                    'waste_factor' => 5,
                    'excavation_depth_in' => 12,
                    'hst_rate' => 13,
                ] + $quoteExtras,
            ],
            'standard' => [
                'route' => route('admin.quotations.standard.store'),
                'from' => route('admin.quotations.standard.create'),
                'model' => StandardQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay standard A',
                'replay' => 'Replay standard B',
                'payload' => fn (string $name) => [
                    'client_name' => $name,
                    'display_title' => $name.' title',
                    'document_type' => 'quotation',
                    'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
                    'includes_labour' => '1',
                    'works' => [[
                        'title' => 'Job',
                        'labour_use_fixed' => '1',
                        'labour_fixed_amount' => '100',
                    ]],
                ] + $quoteExtras,
            ],
            'stairs' => [
                'route' => route('admin.quotations.stairs.store'),
                'from' => route('admin.quotations.stairs.create'),
                'model' => StairsQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay stairs A',
                'replay' => 'Replay stairs B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'draft',
                    'client_name' => $name,
                ] + $quoteExtras,
            ],
            'millwork' => [
                'route' => route('admin.quotations.millwork.store'),
                'from' => route('admin.quotations.millwork.create'),
                'model' => MillworkQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay millwork A',
                'replay' => 'Replay millwork B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'draft',
                    'client_name' => $name,
                    'pieces' => [[
                        'type' => 'shelf',
                        'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
                    ]],
                ] + $quoteExtras,
            ],
            'basement' => [
                'route' => route('admin.quotations.basement.store'),
                'from' => route('admin.quotations.basement.create'),
                'model' => BasementQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay basement A',
                'replay' => 'Replay basement B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'publish',
                    'client_name' => $name,
                    'area_sqft' => 850,
                    'ceiling_height_ft' => 8,
                    'room_count' => 3,
                ] + $quoteExtras,
            ],
            'trimming' => [
                'route' => route('admin.quotations.trimming.store'),
                'from' => route('admin.quotations.trimming.create'),
                'model' => TrimmingQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay trimming A',
                'replay' => 'Replay trimming B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'publish',
                    'client_name' => $name,
                    'enabled_families' => ['floor_wall'],
                    'floor_lf' => 80,
                    'include_baseboard' => 1,
                    'include_base_cap' => 0,
                    'include_shoe' => 0,
                    'include_quarter_round' => 0,
                ] + $quoteExtras,
            ],
            'shed' => [
                'route' => route('admin.quotations.shed.store'),
                'from' => route('admin.quotations.shed.create'),
                'model' => ShedQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay shed A',
                'replay' => 'Replay shed B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'publish',
                    'client_name' => $name,
                    'length_ft' => 10,
                    'width_ft' => 12,
                    'wall_height_ft' => 8,
                    'shed_type' => 'gable',
                    'foundation' => 'skids',
                ] + $quoteExtras,
            ],
            'flooring' => [
                'route' => route('admin.quotations.flooring.store'),
                'from' => route('admin.quotations.flooring.create'),
                'model' => FlooringQuotation::class,
                'column' => 'client_name',
                'first' => 'Replay flooring A',
                'replay' => 'Replay flooring B',
                'payload' => fn (string $name) => [
                    'save_intent' => 'draft',
                    'client_name' => $name,
                ] + $quoteExtras,
            ],
            'material' => [
                'route' => route('admin.materials.store'),
                'from' => route('admin.materials.create'),
                'model' => Material::class,
                'column' => 'name',
                'first' => 'Replay material A',
                'replay' => 'Replay material B',
                'payload' => fn (string $name) => [
                    'name' => $name,
                    'short_name' => $name,
                    'price_cad' => '12.00',
                    'delivery_cad' => '0.00',
                    'unit' => 'each',
                    'product_url' => 'https://example.com/replay-material',
                    'is_active' => '1',
                    'sort_order' => '0',
                ],
            ],
            'category' => [
                'route' => route('admin.categories.store'),
                'from' => route('admin.categories.create'),
                'model' => ProjectCategory::class,
                'column' => 'name',
                'first' => 'Replay category A',
                'replay' => 'Replay category B',
                'payload' => fn (string $name) => ['name' => $name],
            ],
            'vehicle' => [
                'route' => route('admin.vehicles.store'),
                'from' => route('admin.vehicles.create'),
                'model' => Vehicle::class,
                'column' => 'name',
                'first' => 'Replay vehicle A',
                'replay' => 'Replay vehicle B',
                'payload' => fn (string $name) => ['name' => $name],
            ],
            'helper' => [
                'route' => route('admin.helpers.store'),
                'from' => route('admin.helpers.create'),
                'model' => Helper::class,
                'column' => 'name',
                'first' => 'Replay helper A',
                'replay' => 'Replay helper B',
                'payload' => fn (string $name) => [
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'hourly_rate' => '40',
                    'is_active' => '1',
                ],
            ],
            'client' => [
                'route' => route('admin.clients.store'),
                'from' => route('admin.clients.create'),
                'model' => Client::class,
                'column' => 'name',
                'first' => 'Replay client A',
                'replay' => 'Replay client B',
                'payload' => fn (string $name) => [
                    'name' => $name,
                    'confirm_duplicate' => '1',
                ],
            ],
            'client-tag' => [
                'route' => route('admin.client-tags.store'),
                'from' => route('admin.client-tags.create'),
                'model' => ClientTag::class,
                'column' => 'name',
                'first' => 'Replay client tag A',
                'replay' => 'Replay client tag B',
                'payload' => fn (string $name) => [
                    'name' => $name,
                    'color' => '#024768',
                ],
            ],
        ];
    }
}
