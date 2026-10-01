<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PortfolioPageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_saved_project_has_a_public_page_and_sitemap_entry(): void
    {
        $category = ProjectCategory::query()->create([
            'name' => 'Portfolio page deck',
            'slug' => 'portfolio-page-deck',
        ]);

        $project = Project::query()->create([
            'title' => 'Deck for Gazebo',
            'description' => "A cedar deck built beside the gazebo.\n\nRails match the house trim.",
            'completed_on' => '2026-06-09',
            'meta_title' => 'Deck for Gazebo in Ottawa',
            'meta_description' => 'Cedar deck and rails built beside a backyard gazebo.',
        ]);
        $project->categories()->attach($category->id);

        $this->assertSame('deck-for-gazebo', $project->slug);

        $this->get('/portfolio/deck-for-gazebo')
            ->assertOk()
            ->assertSee('Deck for Gazebo in Ottawa', false)
            ->assertSee('Cedar deck and rails built beside a backyard gazebo', false)
            ->assertSee('A cedar deck built beside the gazebo.', false)
            ->assertSee('Rails match the house trim.', false)
            ->assertSee(url('/portfolio/deck-for-gazebo'), false);

        $this->get('/portfolio')
            ->assertOk()
            ->assertSee('Deck for Gazebo', false)
            ->assertSee(url('/portfolio/deck-for-gazebo'), false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/portfolio/deck-for-gazebo'), false)
            ->assertSee(url('/portfolio').'</loc>', false);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('/portfolio/deck-for-gazebo', false);
    }

    public function test_duplicate_titles_get_a_numbered_slug(): void
    {
        $first = Project::query()->create([
            'title' => 'Lattice fence',
            'description' => 'First lattice fence.',
            'completed_on' => '2026-05-01',
        ]);
        $second = Project::query()->create([
            'title' => 'Lattice fence',
            'description' => 'Second lattice fence.',
            'completed_on' => '2026-05-02',
        ]);

        $this->assertSame('lattice-fence', $first->slug);
        $this->assertSame('lattice-fence-2', $second->slug);
        $this->get('/portfolio/lattice-fence-2')->assertOk()->assertSee('Second lattice fence.', false);
    }

    public function test_old_slug_redirects_after_the_address_changes(): void
    {
        $category = ProjectCategory::query()->create([
            'name' => 'Portfolio page fencing',
            'slug' => 'portfolio-page-fencing',
        ]);
        $project = Project::query()->create([
            'title' => 'Three fences at once',
            'description' => 'Three cedar fences completed in one week.',
            'completed_on' => '2026-06-09',
        ]);
        $project->categories()->attach($category->id);

        $this->actingAsAdmin();

        $this->put(route('admin.portfolio.update', $project), [
            'title' => 'Three fences at once',
            'description' => 'Three cedar fences completed in one week.',
            'completed_on' => '2026-06-09',
            'project_category_ids' => [$category->id],
            'slug' => 'three-cedar-fences',
        ])->assertRedirect(route('admin.portfolio.edit', $project));

        $project->refresh();
        $this->assertSame('three-cedar-fences', $project->slug);

        $this->get('/portfolio/three-fences-at-once')
            ->assertStatus(301)
            ->assertRedirect(url('/portfolio/three-cedar-fences'));

        $this->get('/portfolio/three-cedar-fences')
            ->assertOk()
            ->assertSee('Three fences at once', false);
    }
}
