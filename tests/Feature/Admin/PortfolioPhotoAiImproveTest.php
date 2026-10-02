<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Services\PortfolioPhotoAiImprovementService;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PortfolioPhotoAiImproveTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_edit_page_offers_improve_on_saved_photos(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');
        $photo = $this->makePhoto('project-photos/edit-page-'.uniqid().'.webp', 'original');

        $this->get(route('admin.portfolio.edit', $photo->project))
            ->assertOk()
            ->assertSee('js-portfolio-ai-existing', false)
            ->assertSee('Check <strong>Improve</strong>', false)
            ->assertSee('value="'.$photo->id.'"', false);
    }

    public function test_apply_replaces_a_checked_saved_photo(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');
        $relative = 'project-photos/ai-improve-'.uniqid().'.webp';
        Storage::disk('public')->put($relative, 'original-bytes');
        $photo = $this->makePhoto($relative, 'original-bytes');

        $improved = 'improved-webp-bytes';
        $this->mock(PortfolioPhotoAiImprovementService::class, function ($mock) use ($improved) {
            $mock->shouldReceive('improveLocalFile')
                ->once()
                ->andReturn([
                    'filename' => 'kitchen-ai.webp',
                    'content_base64' => base64_encode($improved),
                    'size_bytes' => strlen($improved),
                    'label' => 'kitchen.webp',
                ]);
        });

        $mirror = public_path('storage/'.$relative);

        try {
            $response = $this->postJson(route('admin.portfolio.ai-improve-photos'), [
                'existing_photo_id' => $photo->id,
            ]);

            $response->assertOk();
            $response->assertJsonPath('files.0.replaced', true);
            $response->assertJsonPath('files.0.photo_id', $photo->id);
            $response->assertJsonPath('summary', '1 saved photo replaced with the AI-improved version.');
            $this->assertSame($improved, Storage::disk('public')->get($relative));
            $this->assertStringContainsString('?v=', (string) $response->json('files.0.url'));
        } finally {
            if (is_file($mirror)) {
                @unlink($mirror);
            }
        }
    }

    public function test_apply_without_a_photo_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.portfolio.ai-improve-photos'), [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Choose at least one photo (upload or checked Google Photos).');
    }

    private function makePhoto(string $relative, string $bytes): ProjectPhoto
    {
        if (! Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->put($relative, $bytes);
        }

        $project = Project::query()->create([
            'title' => 'Kitchen AI '.uniqid(),
            'description' => 'A kitchen renovation used to test photo improvement.',
            'completed_on' => '2026-06-09',
        ]);

        return $project->photos()->create([
            'path' => $relative,
            'sort_order' => 0,
        ]);
    }
}
