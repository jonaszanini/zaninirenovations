<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use Illuminate\Support\Str;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PortfolioDescriptionHistoryTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_each_new_project_form_gets_its_own_description_history_key(): void
    {
        $this->actingAsAdmin();

        $first = $this->historyKey($this->get(route('admin.portfolio.create'))->assertOk()->getContent());
        $second = $this->historyKey($this->get(route('admin.portfolio.create'))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('/^portfolio-desc:draft-[0-9a-f-]{36}$/', $first);
        $this->assertMatchesRegularExpression('/^portfolio-desc:draft-[0-9a-f-]{36}$/', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_edit_pages_keep_description_history_on_that_project(): void
    {
        $this->actingAsAdmin();
        $first = $this->makeProject();
        $second = $this->makeProject();

        $firstPage = $this->get(route('admin.portfolio.edit', $first))->assertOk()->getContent();
        $secondPage = $this->get(route('admin.portfolio.edit', $second))->assertOk()->getContent();

        $this->assertSame('portfolio-desc:'.$first->id, $this->historyKey($firstPage));
        $this->assertSame('portfolio-desc:'.$second->id, $this->historyKey($secondPage));
    }

    public function test_a_failed_save_keeps_the_new_project_history_key(): void
    {
        $this->actingAsAdmin();
        $key = 'portfolio-desc:draft-'.Str::uuid();

        $this->from(route('admin.portfolio.create'))
            ->post(route('admin.portfolio.store'), [
                'portfolio_ai_history_key' => $key,
                'title' => 'Floating deck',
            ])
            ->assertRedirect(route('admin.portfolio.create'));

        $page = $this->get(route('admin.portfolio.create'))->assertOk()->getContent();

        $this->assertSame($key, $this->historyKey($page));
    }

    public function test_a_shared_history_key_is_not_reused_on_a_new_project(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.portfolio.create'))
            ->post(route('admin.portfolio.store'), [
                'portfolio_ai_history_key' => 'portfolio-desc:description',
                'title' => 'Floating deck',
            ])
            ->assertRedirect(route('admin.portfolio.create'));

        $key = $this->historyKey($this->get(route('admin.portfolio.create'))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('/^portfolio-desc:draft-[0-9a-f-]{36}$/', $key);
        $this->assertNotSame('portfolio-desc:description', $key);
    }

    private function historyKey(string $html): string
    {
        $this->assertSame(1, preg_match('/historyKey:\s*"([^"]+)"/', $html, $matches));

        return $matches[1];
    }

    private function makeProject(): Project
    {
        return Project::query()->create([
            'title' => 'History '.uniqid(),
            'description' => 'Saved project used by the description history test.',
            'completed_on' => '2026-06-09',
        ]);
    }
}
