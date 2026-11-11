<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\FenceQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class DashboardLayoutBrowserTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_rendered_blade_dashboard_screenshots_at_1280_and_390(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $this->actingAsAdmin();

        $overdueProject = ClientProject::factory()->create([
            'name' => 'Overdue Kitchen Reno',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'total_amount' => 8000,
            'end_date' => now()->subDays(3)->toDateString(),
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $overdueProject->id,
            'invoice_number' => 'INV-OVERDUE-001',
            'due_on' => now()->subDays(5)->toDateString(),
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
        ]);

        ClientProject::factory()->create([
            'name' => 'Chase Deck Quote',
            'client_name' => 'Pat Followup',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'total_amount' => 4000,
        ]);

        ClientProject::factory()->create([
            'name' => 'Soon Start Patio',
            'status' => ClientProject::STATUS_SCHEDULED,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
        ]);

        FenceQuotation::query()->create([
            'quote_number' => 'FQ-CHASE-1',
            'client_name' => 'Fence Lead',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => []],
            'grand_total' => 3210.50,
        ]);

        $response = $this->get(route('admin.dashboard', ['refresh' => 1]));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('dash-page', $html);
        $this->assertStringContainsString('dash-compact-row', $html);
        $this->assertStringContainsString('What needs action', $html);

        $htmlPath = storage_path('app/dashboard-blade-render.html');
        $out = storage_path('app/dashboard-layout-browser.json');
        $shots = storage_path('app/qa-screenshots');
        @mkdir(dirname($htmlPath), 0777, true);
        @mkdir($shots, 0777, true);
        file_put_contents($htmlPath, $html);
        @unlink($out);

        $output = [];
        $env = [
            'DASH_HTML' => $htmlPath,
            'DASH_OUT' => $out,
            'DASH_SHOTS' => $shots,
            'DASH_APP_URL' => rtrim((string) config('app.url'), '/'),
        ];
        $chrome = (string) getenv('PUPPETEER_EXECUTABLE_PATH');
        if ($chrome === '' && is_file('/usr/bin/google-chrome')) {
            $chrome = '/usr/bin/google-chrome';
        }
        if ($chrome !== '') {
            $env['PUPPETEER_EXECUTABLE_PATH'] = $chrome;
        }
        $code = $this->runNode(base_path('tests/Feature/Admin/dashboard-layout-browser.js'), $env, $output);

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Dashboard layout puppeteer failed:\n".implode("\n", $output)."\n".$raw);

        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach ([
            'w1280_light' => 'admin-dashboard-action-1280-light.png',
            'w1280_dark' => 'admin-dashboard-action-1280-dark.png',
            'w390_light' => 'admin-dashboard-action-390-light.png',
            'w390_dark' => 'admin-dashboard-action-390-dark.png',
        ] as $key => $name) {
            $shot = (string) ($report[$key]['shot'] ?? '');
            $this->assertFileExists($shot, "Missing {$key} screenshot");
            $this->writeArtifact($shot, $name);
        }

        $this->writeArtifactString('dashboard-layout-browser.json', $raw);
    }

    /**
     * @param  array<string, string>  $env
     * @param  list<string>  $output
     */
    private function runNode(string $script, array $env, &$output): int
    {
        $procEnv = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $procEnv[$key] = (string) $value;
            }
        }
        foreach ($env as $key => $value) {
            $procEnv[$key] = (string) $value;
        }
        $pipes = [];
        $proc = proc_open(
            'node '.escapeshellarg($script),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $procEnv
        );
        if (! is_resource($proc)) {
            $output = ['Could not start node'];

            return 1;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $output = array_values(array_filter(preg_split("/\r\n|\n|\r/", (string) $stdout."\n".$stderr) ?: []));

        return (int) $code;
    }

    private function writeArtifact(string $source, string $name): void
    {
        foreach (['/opt/cursor/artifacts', storage_path('app/qa-screenshots')] as $destDir) {
            if (! is_dir($destDir) && ! @mkdir($destDir, 0777, true)) {
                continue;
            }
            @copy($source, $destDir.'/'.$name);
        }
    }

    private function writeArtifactString(string $name, string $contents): void
    {
        foreach (['/opt/cursor/artifacts', storage_path('app/qa-screenshots')] as $destDir) {
            if (! is_dir($destDir) && ! @mkdir($destDir, 0777, true)) {
                continue;
            }
            @file_put_contents($destDir.'/'.$name, $contents);
        }
    }
}
