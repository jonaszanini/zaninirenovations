<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Services\GeminiService;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PortfolioCategoryAiTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_edit_page_offers_auto_categorize(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProject();

        $this->get(route('admin.portfolio.edit', $project))
            ->assertOk()
            ->assertSee('Auto categorize with AI', false)
            ->assertSee('id="portfolio-category-ai-btn"', false)
            ->assertSee('id="portfolio-category-checkboxes"', false)
            ->assertSee(route('admin.portfolio.ai-categorize.edit', $project), false);
    }

    public function test_create_page_offers_auto_categorize(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.portfolio.create'))
            ->assertOk()
            ->assertSee('Auto categorize with AI', false)
            ->assertSee(route('admin.portfolio.ai-categorize'), false);
    }

    public function test_suggest_checks_existing_categories_and_creates_missing_ones(): void
    {
        $this->actingAsAdmin();
        $kitchen = $this->makeCategory('Kitchen Renovations', 'kitchen-renovations');
        $tile = $this->makeCategory('Tile', 'tile-ai-'.uniqid());
        $project = $this->makeProject();

        $this->mock(GeminiService::class, function ($mock) use ($kitchen, $tile) {
            $mock->shouldReceive('generateStructuredJsonWithImages')
                ->once()
                ->withArgs(function ($system, $userText) use ($kitchen) {
                    return is_string($system)
                        && str_contains($userText, 'Oak kitchen')
                        && str_contains($userText, 'quartz counters and a tile backsplash')
                        && str_contains($userText, 'id '.$kitchen->id.': Kitchen Renovations');
                })
                ->andReturn([
                    'existing_category_ids' => [$kitchen->id, $tile->id],
                    'new_category_names' => ['Quartz Countertops'],
                ]);
        });

        $response = $this->postJson(route('admin.portfolio.ai-categorize.edit', $project), [
            'title' => 'Oak kitchen',
            'description' => 'quartz counters and a tile backsplash',
        ]);

        $response->assertOk();
        $response->assertJsonPath('image_count', 0);

        $created = ProjectCategory::query()->where('name', 'Quartz Countertops')->first();
        $this->assertNotNull($created);

        $ids = collect($response->json('categories'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertEqualsCanonicalizing([
            (int) $kitchen->id,
            (int) $tile->id,
            (int) $created->id,
        ], $ids);

        $createdRow = collect($response->json('categories'))->firstWhere('id', $created->id);
        $this->assertTrue($createdRow['created']);
        $existingRow = collect($response->json('categories'))->firstWhere('id', $kitchen->id);
        $this->assertFalse($existingRow['created']);
    }

    public function test_new_name_that_matches_an_existing_category_is_not_duplicated(): void
    {
        $this->actingAsAdmin();
        $kitchen = $this->makeCategory('Kitchen Renovations', 'kitchen-renovations-dup-'.uniqid());
        $before = ProjectCategory::query()->count();
        $project = $this->makeProject();

        $tile = ProjectCategory::query()->where('slug', 'tile')->firstOrFail();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateStructuredJsonWithImages')
                ->once()
                ->andReturn([
                    'existing_category_ids' => [],
                    'new_category_names' => ['kitchen renovations', 'Tiles'],
                ]);
        });

        $response = $this->postJson(route('admin.portfolio.ai-categorize.edit', $project), [
            'title' => 'Kitchen refresh',
            'description' => 'New cabinets.',
        ]);

        $response->assertOk();
        $this->assertSame($before, ProjectCategory::query()->count());
        $response->assertJsonPath('categories.0.id', $kitchen->id);
        $response->assertJsonPath('categories.0.created', false);
        $response->assertJsonPath('categories.1.id', $tile->id);
        $response->assertJsonPath('categories.1.created', false);
        $this->assertCount(2, $response->json('categories'));
    }

    public function test_empty_description_is_sent_when_the_user_confirms(): void
    {
        $this->actingAsAdmin();
        $deck = $this->makeCategory('Deck', 'deck-ai-'.uniqid());
        $project = $this->makeProject();

        $this->mock(GeminiService::class, function ($mock) use ($deck) {
            $mock->shouldReceive('generateStructuredJsonWithImages')
                ->once()
                ->withArgs(function ($system, $userText) {
                    return str_contains($userText, '(none provided)')
                        && str_contains($userText, 'Front deck');
                })
                ->andReturn([
                    'existing_category_ids' => [$deck->id],
                    'new_category_names' => [],
                ]);
        });

        $this->postJson(route('admin.portfolio.ai-categorize.edit', $project), [
            'title' => 'Front deck',
            'description' => '   ',
        ])
            ->assertOk()
            ->assertJsonPath('categories.0.id', $deck->id)
            ->assertJsonPath('categories.0.created', false);
    }

    public function test_missing_title_is_rejected(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProject();

        $this->postJson(route('admin.portfolio.ai-categorize.edit', $project), [
            'description' => 'A kitchen.',
        ])->assertStatus(422);
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'title' => 'Category AI '.uniqid(),
            'description' => 'Saved project used by the category AI test.',
            'completed_on' => '2026-06-09',
        ]);
    }

    private function makeCategory(string $name, string $slug): ProjectCategory
    {
        return ProjectCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => 0,
        ]);
    }
}
