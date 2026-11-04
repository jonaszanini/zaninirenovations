<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAreaEngineFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_default_engine_is_openplan3d_and_renders_openplan3d_view(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.utilities.room-area'));

        $response->assertOk();
        $response->assertSee('data-room-area-openplan3d', false);
        $response->assertSee('vendor/openplan3d-h1/editor/index.html', false);
        $response->assertSee('openPlan3D', false);
        $response->assertDontSee('konva.min.js', false);
        $response->assertDontSee('admin-utilities-room-area.js', false);
    }

    public function test_client_project_room_area_renders_openplan3d_view(): void
    {
        $project = ClientProject::create([
            'name' => 'Basement Reno',
            'client_name' => 'Sarah Connor',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.client-projects.utilities.room-area', [
                'client_project' => $project,
            ]));

        $response->assertOk();
        $response->assertSee('data-room-area-openplan3d', false);
        $response->assertSee('vendor/openplan3d-h1/editor/index.html', false);
        $response->assertSee('Basement Reno', false);
        $response->assertSee('Back to project', false);
        $response->assertDontSee('konva.min.js', false);
    }

    public function test_hosted_openplan3d_h1_assets_exist_on_disk(): void
    {
        $vendorDir = public_path('vendor/openplan3d-h1');

        $this->assertDirectoryExists($vendorDir);
        $this->assertFileExists($vendorDir . '/editor/index.html');
        $manifest = json_decode(file_get_contents(base_path('scripts/openplan3d-h1.json')), true);
        $this->assertSame('1VjVcrjm-h2.js', $manifest['storeChunk'] ?? null);
        $this->assertFileExists($vendorDir . '/_app/immutable/chunks/' . $manifest['storeChunk']);
    }

    public function test_vendored_openplan3d_assets_exist_on_disk(): void
    {
        $vendorDir = public_path('vendor/openplan3d');

        $this->assertDirectoryExists($vendorDir);
        $this->assertFileExists($vendorDir . '/index.html');
        $this->assertFileExists($vendorDir . '/editor/index.html');
        $this->assertFileExists($vendorDir . '/LICENSE');
        $this->assertDirectoryExists($vendorDir . '/models');
        $this->assertDirectoryExists($vendorDir . '/textures');
        $this->assertDirectoryExists($vendorDir . '/_app');

        $licenseContent = file_get_contents($vendorDir . '/LICENSE');
        $this->assertStringContainsString('MIT License', $licenseContent);
    }

    public function test_vendored_openplan3d_has_zero_external_cloud_endpoints(): void
    {
        $vendorDir = public_path('vendor/openplan3d');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($vendorDir));

        $forbiddenHosts = [
            'api.openai.com',
            'generativelanguage.googleapis.com',
            'openplan3d.firebasestorage.app',
        ];

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }
            $ext = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
            if (!in_array($ext, ['js', 'html', 'json'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            foreach ($forbiddenHosts as $host) {
                $this->assertStringNotContainsString(
                    $host,
                    $content,
                    "Vendored file [{$file->getFilename()}] contains forbidden external endpoint: {$host}"
                );
            }
        }
    }
}
