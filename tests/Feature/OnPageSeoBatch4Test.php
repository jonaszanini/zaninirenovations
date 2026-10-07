<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPhoto;
use App\Models\ProjectSlugRedirect;
use App\Support\SeoBatch4PortfolioUpgrade;
use App\Support\SeoStructuredData;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class OnPageSeoBatch4Test extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_public_project_page_and_portfolio_cards_show_town(): void
    {
        $this->makePublicProject([
            'title' => 'Floating deck with gravel around',
            'slug' => 'floating-deck-with-gravel-around',
            'town' => 'Kanata',
            'description' => 'Ground-level deck with steps.',
        ]);

        $this->get('/portfolio/floating-deck-with-gravel-around')
            ->assertOk()
            ->assertSee('Kanata', false)
            ->assertSee('Town', false);

        $this->get('/portfolio')
            ->assertOk()
            ->assertSee('Kanata', false)
            ->assertSee('portfolio-card-town', false);
    }

    public function test_toronto_projects_are_excluded_from_local_contexts_and_schema(): void
    {
        $fencing = ProjectCategory::query()->create([
            'name' => 'Fencing',
            'slug' => 'fencing-batch4',
        ]);

        $local = $this->makePublicProject([
            'title' => 'Three fences at once',
            'slug' => 'three-fences-at-once',
            'town' => 'Carleton Place',
            'description' => 'Backyard privacy fence in Carleton Place.',
        ]);
        $local->categories()->attach($fencing->id);

        $otherLocal = $this->makePublicProject([
            'title' => 'Lattice fence',
            'slug' => 'lattice-fence',
            'town' => 'Alta Vista',
            'description' => 'Privacy screen fence section.',
        ]);
        $otherLocal->categories()->attach($fencing->id);

        $toronto = $this->makePublicProject([
            'title' => 'Flagstones patio renovation',
            'slug' => 'flagstones-patio-renovation',
            'town' => 'Toronto',
            'description' => 'Backyard platform decks and stone path.',
        ]);
        $toronto->categories()->attach($fencing->id);

        $this->assertTrue($toronto->isOutOfMarket());
        $this->assertFalse($toronto->isInServiceArea());
        $this->assertSame(0, Project::query()->nearTown('Toronto')->count());
        $this->assertSame(
            [$local->id],
            Project::query()->nearTown('Carleton Place')->orderBy('id')->pluck('id')->all()
        );
        $this->assertFalse(
            Project::query()->inServiceArea()->whereKey($toronto->id)->exists()
        );

        $this->get('/portfolio/three-fences-at-once')
            ->assertOk()
            ->assertSee('Lattice fence', false)
            ->assertSee('/portfolio/lattice-fence', false)
            ->assertDontSee('/portfolio/flagstones-patio-renovation', false)
            ->assertDontSee('Flagstones patio renovation', false);

        $localHtml = $this->get('/portfolio/three-fences-at-once')->getContent();
        $this->assertStringContainsString('"contentLocation"', $localHtml);
        $this->assertStringContainsString('Carleton Place', $localHtml);

        $torontoHtml = $this->get('/portfolio/flagstones-patio-renovation')->getContent();
        $this->assertStringContainsString('Toronto', $torontoHtml);
        $this->assertStringNotContainsString('"contentLocation"', $torontoHtml);

        $toronto->setRelation('photos', collect());
        $toronto->setRelation('categories', collect());
        $graph = SeoStructuredData::portfolioProject($toronto);
        $encoded = SeoStructuredData::toJsonLd($graph);
        $this->assertStringNotContainsString('contentLocation', $encoded);
        $this->assertStringNotContainsString('Carleton Place', $encoded);
    }

    public function test_old_fence_slugs_301_in_one_hop_and_titles_are_unique(): void
    {
        $this->seedFenceProjects();
        $this->makePublicProject([
            'title' => 'Backyard privacy fence',
            'slug' => 'backyard-privacy-fence-carleton-place',
            'description' => 'Existing Carleton Place privacy fence page.',
            'completed_on' => '2026-06-23',
        ]);
        $this->makePublicProject([
            'title' => 'Side yard fence and gates',
            'slug' => 'side-yard-fence-gates-carleton-place',
            'description' => 'Existing Carleton Place side-yard fence page.',
            'completed_on' => '2026-07-01',
        ]);

        SeoBatch4PortfolioUpgrade::apply();
        SeoBatch4PortfolioUpgrade::apply();

        $expected = SeoBatch4PortfolioUpgrade::FENCE_RENAMES;
        $titles = [];
        $metaTitles = [];
        $metaDescriptions = [];

        foreach ($expected as $oldSlug => $meta) {
            $this->assertNull(
                Project::query()->where('slug', $oldSlug)->first(),
                'Old slug '.$oldSlug.' should no longer be a live page.'
            );

            $project = Project::query()->where('slug', $meta['slug'])->first();
            $this->assertNotNull($project);
            $this->assertSame($meta['title'], $project->title);
            $this->assertSame($meta['meta_title'], $project->meta_title);
            $this->assertSame($meta['meta_description'], $project->meta_description);
            $this->assertLessThanOrEqual(155, mb_strlen((string) $project->meta_description));
            $this->assertSame($expected[$oldSlug]['description'], $project->description);

            $blob = strtolower($meta['title'].' '.$meta['meta_title'].' '.$meta['meta_description'].' '.$meta['description']);
            $this->assertStringNotContainsString('licensed', $blob);
            $this->assertStringNotContainsString('one-person', $blob);
            $this->assertStringNotContainsString('wsib', $blob);

            $titles[] = $project->title;
            $metaTitles[] = $project->seoTitle();
            $metaDescriptions[] = $project->meta_description;

            $this->get('/portfolio/'.$oldSlug)
                ->assertStatus(301)
                ->assertRedirect(url('/portfolio/'.$meta['slug']));

            $this->get('/portfolio/'.$meta['slug'])
                ->assertOk()
                ->assertSee($meta['title'], false);

            $this->assertTrue(
                ProjectSlugRedirect::query()
                    ->where('project_id', $project->id)
                    ->where('slug', $oldSlug)
                    ->exists()
            );
        }

        $this->assertCount(4, array_unique($titles));
        $this->assertCount(4, array_unique($metaTitles));
        $this->assertCount(4, array_unique($metaDescriptions));
        $this->assertTrue(Project::query()->where('slug', 'backyard-privacy-fence-carleton-place')->exists());
        $this->assertTrue(Project::query()->where('slug', 'side-yard-fence-gates-carleton-place')->exists());

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach ($expected as $oldSlug => $meta) {
            $this->assertStringContainsString('/portfolio/'.$meta['slug'], $sitemap);
            $this->assertStringNotContainsString('/portfolio/'.$oldSlug.'</loc>', $sitemap);
        }
    }

    public function test_data_migration_sets_towns_moves_gate_photo_and_kitchen_scope(): void
    {
        $this->seedFenceProjects();

        $kitchen = $this->makePublicProject([
            'title' => 'Kitchen renovations',
            'slug' => 'kitchen-renovations',
            'description' => 'Existing kitchen copy about cabinet doors.',
        ]);
        $kanata = $this->makePublicProject([
            'title' => 'Floating deck with gravel around',
            'slug' => 'floating-deck-with-gravel-around',
            'description' => 'Ground-level deck with steps.',
        ]);
        $toronto = $this->makePublicProject([
            'title' => 'Small deck for shed',
            'slug' => 'small-deck-for-shed',
            'description' => 'A small platform beside the shed.',
        ]);

        $fence4 = Project::query()->where('slug', 'fence-4')->first();
        $fence2 = Project::query()->where('slug', 'fence-2')->first();
        $this->assertNotNull($fence4);
        $this->assertNotNull($fence2);

        $photo = ProjectPhoto::query()->create([
            'project_id' => $fence4->id,
            'path' => 'project-photos/10/'.SeoBatch4PortfolioUpgrade::GATE_WITH_CHILD_FILENAME,
            'sort_order' => 5,
        ]);

        SeoBatch4PortfolioUpgrade::apply();
        SeoBatch4PortfolioUpgrade::apply();

        $this->assertSame('Kanata', $kanata->fresh()->town);
        $this->assertSame('Toronto', $toronto->fresh()->town);
        $this->assertSame('Nepean', $kitchen->fresh()->town);
        $this->assertSame('Carleton Place', Project::query()->where('slug', 'privacy-gate-fence-carleton-place')->value('town'));
        $this->assertSame('Barrhaven', Project::query()->where('slug', 'backyard-privacy-gate-fence-barrhaven')->value('town'));
        $this->assertSame('Stittsville', Project::query()->where('slug', 'backyard-gate-fence-stittsville')->value('town'));
        $this->assertSame('Carleton Place', Project::query()->where('slug', 'backyard-privacy-gate-fence-carleton-place')->value('town'));

        $photo->refresh();
        $this->assertSame($fence2->id, (int) $photo->project_id);
        $this->assertSame(
            1,
            ProjectPhoto::query()->where('path', $photo->path)->count()
        );

        $kitchenText = (string) $kitchen->fresh()->description;
        $this->assertStringContainsString(SeoBatch4PortfolioUpgrade::KITCHEN_UNIT_SCOPE, $kitchenText);
        $this->assertSame(
            1,
            substr_count($kitchenText, 'unit renovation')
        );
        $this->assertStringContainsString('Existing kitchen copy about cabinet doors.', $kitchenText);

        $this->get('/portfolio/kitchen-renovations')
            ->assertOk()
            ->assertSee('unit renovation', false)
            ->assertSee('bathroom vanity', false)
            ->assertSee('Nepean', false);
    }

    public function test_admin_project_editor_saves_town(): void
    {
        $category = ProjectCategory::query()->create([
            'name' => 'Decks',
            'slug' => 'decks-batch4',
        ]);
        $project = $this->makePublicProject([
            'title' => 'Catio',
            'slug' => 'catio',
            'description' => 'Outdoor cat enclosure.',
        ]);
        $project->categories()->attach($category->id);

        $this->actingAsAdmin();

        $this->get(route('admin.portfolio.edit', $project))
            ->assertOk()
            ->assertSee('Town', false)
            ->assertSee('name="town"', false)
            ->assertSee('South Keys', false);

        $this->put(route('admin.portfolio.update', $project), [
            'title' => 'Catio',
            'description' => 'Outdoor cat enclosure.',
            'completed_on' => '2026-05-09',
            'project_category_ids' => [$category->id],
            'slug' => 'catio',
            'town' => 'South Keys',
        ])->assertRedirect(route('admin.portfolio.edit', $project));

        $this->assertSame('South Keys', $project->fresh()->town);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublicProject(array $overrides = []): Project
    {
        return Project::query()->create(array_merge([
            'title' => 'Sample project',
            'description' => 'Sample description.',
            'completed_on' => '2026-06-01',
        ], $overrides));
    }

    private function seedFenceProjects(): void
    {
        $this->makePublicProject([
            'title' => 'Fence',
            'slug' => 'fence',
            'description' => 'Pressure treated wood fence.',
            'completed_on' => '2024-05-30',
        ]);
        $this->makePublicProject([
            'title' => 'Fence',
            'slug' => 'fence-2',
            'description' => 'Pressure treated wood fence.',
            'completed_on' => '2024-07-01',
        ]);
        $this->makePublicProject([
            'title' => 'Fence',
            'slug' => 'fence-3',
            'description' => 'Pressure treated wood fence.',
            'completed_on' => '2025-08-15',
        ]);
        $this->makePublicProject([
            'title' => 'Fence',
            'slug' => 'fence-4',
            'description' => "Pressure treated wood fence 6' high.",
            'completed_on' => '2024-10-02',
        ]);
    }
}
