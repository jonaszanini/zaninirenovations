<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectFiltersAccordionTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_projects_index_renders_collapsed_filters_accordion(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.client-projects.index'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-admin-filters-accordion', $html);
        $this->assertStringContainsString('id="admin-filters-client-projects"', $html);
        $this->assertStringContainsString('id="admin-filters-client-projects-panel"', $html);
        $this->assertStringContainsString('aria-controls="admin-filters-client-projects-panel"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="admin-filters-accordion__summary[^"]*"[^>]*aria-expanded/i',
            $html
        );
        $this->assertStringContainsString('admin-filters-accordion__title', $html);
        $this->assertStringContainsString('Filters', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]*id="admin-filters-client-projects"[^>]*\sopen/i',
            $html
        );
        $this->assertStringNotContainsString('admin-filters-accordion__badge', $html);

        $response->assertSee('Ongoing pipeline');
        $response->assertSee('All projects');
        $response->assertSee('Quote Pending');
        $response->assertSee('By default Completed and Rejected are hidden');
    }

    public function test_projects_index_shows_active_count_badge_and_opens_when_filters_are_in_query(): void
    {
        $this->actingAsAdmin();

        ClientProject::factory()->create([
            'name' => 'Kitchen Reno',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $response = $this->get(route('admin.client-projects.index', [
            'q' => 'Kitchen',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-admin-filters-accordion', $html);
        $this->assertStringContainsString('data-active-count="2"', $html);
        $this->assertStringContainsString('admin-filters-accordion__badge', $html);
        $this->assertStringContainsString('2 active', $html);
        $this->assertMatchesRegularExpression(
            '/<details[^>]*id="admin-filters-client-projects"[^>]*\sopen/i',
            $html
        );
        $response->assertSee('Kitchen Reno');
    }

    public function test_projects_index_does_not_count_per_page_as_an_active_filter(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.client-projects.index', [
            'per_page' => 50,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-active-count="0"', $html);
        $this->assertStringNotContainsString('admin-filters-accordion__badge', $html);
    }

    public function test_projects_index_counts_from_and_to_as_one_active_filter(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.client-projects.index', [
            'from' => '2026-01-01',
            'to' => '2026-02-01',
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-active-count="1"', $html);
        $this->assertStringContainsString('1 active', $html);
    }
}
