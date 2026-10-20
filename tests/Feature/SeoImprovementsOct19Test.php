<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Tests\IntegrationTestCase;

class SeoImprovementsOct19Test extends IntegrationTestCase
{
    public function test_town_badge_omitted_when_project_title_contains_town_name(): void
    {
        $projectWithTownInTitle = new Project([
            'title' => 'Privacy fence with gate in Carleton Place',
            'slug' => 'privacy-fence-gate-carleton-place',
            'town' => 'Carleton Place',
        ]);

        $projectWithoutTownInTitle = new Project([
            'title' => 'Custom Cedar Deck',
            'slug' => 'custom-cedar-deck-carleton-place',
            'town' => 'Carleton Place',
        ]);

        $rendered = view('site.partials.service-area-projects', [
            'projects' => collect([$projectWithTownInTitle, $projectWithoutTownInTitle]),
        ])->render();

        // The project with town in title must not output the redundant town badge
        $this->assertStringContainsString('Privacy fence with gate in Carleton Place', $rendered);
        $this->assertStringNotContainsString('Privacy fence with gate in Carleton Place</span>'."\n".'              <span class="sa-project__town">Carleton Place</span>', $rendered);
        $this->assertStringNotContainsString('Privacy fence with gate in Carleton Place Carleton Place', strip_tags($rendered));

        // The project without town in title MUST output the town badge
        $this->assertStringContainsString('Custom Cedar Deck', $rendered);
        $this->assertStringContainsString('<span class="sa-project__town">Carleton Place</span>', $rendered);
    }

    public function test_engineered_hardwood_flooring_ashton_portfolio_item_title_and_slug(): void
    {
        $project = Project::query()->create([
            'title' => 'Engineering hard-wood flooring installation',
            'slug' => 'engineering-hard-wood-flooring-installation',
            'town' => 'Ashton',
            'description' => 'Engineered hardwood flooring in Ashton.',
            'completed_on' => '2026-06-01',
        ]);

        // Run the migration logic / update
        DB::table('projects')
            ->where('slug', 'engineering-hard-wood-flooring-installation')
            ->orWhere('title', 'like', '%Engineering hard-wood%')
            ->update([
                'title' => 'Engineered Hardwood Flooring Installation in Ashton',
                'updated_at' => now(),
            ]);

        $project->refresh();
        $this->assertSame('Engineered Hardwood Flooring Installation in Ashton', $project->title);
        $this->assertSame('engineering-hard-wood-flooring-installation', $project->slug);

        // Portfolio URL is accessible with 200 OK
        $response = $this->get('/portfolio/engineering-hard-wood-flooring-installation');
        $response->assertOk();
        $this->assertStringContainsString('Engineered Hardwood Flooring Installation in Ashton', $response->getContent());
    }

    public function test_homepage_has_single_h1_and_no_carousel_cloning(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Exactly one single <h1> element on the entire homepage
        $this->assertSame(1, preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Matches));
        $this->assertSame(
            'Carpenter & Renovation Contractor in Carleton Place & Ottawa',
            trim(html_entity_decode(strip_tags($h1Matches[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        );

        // Hero slider, team carousel, and testimonials must not have owl-carousel or duplicate cards
        $this->assertStringNotContainsString('owl-carousel', $html);
        $this->assertStringNotContainsString('owl-item', $html);
        $this->assertSame(1, substr_count($html, 'Jonas Zanini</h3>'));
        $this->assertSame(1, substr_count($html, 'Mayara Zanini</h3>'));
        $this->assertSame(1, substr_count($html, 'Alana Hamilton</h4>'));
        $this->assertSame(1, substr_count($html, 'Kawana Bazzo</h4>'));
        $this->assertSame(1, substr_count($html, 'Mike Dobson</h4>'));
    }
}
