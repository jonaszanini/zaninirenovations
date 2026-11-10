<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\User;
use App\Models\BasementSettings;
use Tests\Support\ActsAsAdmin;
use App\Services\BasementQuotationCalculator;
use App\Services\BasementRoomAreaImportMapper;
use App\Support\QuotationSharedLabourRates;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class BasementWizardSubtotalBrowserTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * Hand-computed from Browser paint fill import (area 240) + CI seeded rates.
     * Wall 501.52 sf × 2 coats, ceiling 229.61 sf (20×12 ft, 10 cm walls interior), trim ~62.7 lf.
     */
    // Was 3758.85 at 512 wall / 240 ceiling centerline fill; interior measured fill → 3705.30 pre-HST painting.
    private const LITERAL_PAINTING_AFTER_IMPORT_PRE_HST = 3705.3;

    /**
     * Hand-computed: post-import fields + area 800, flooring_sf 800, LVP waste 7%, partition 25 lf.
     */
    private const LITERAL_FLOORING_AT_800_PRE_HST = 7651.0;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8055;

    public function test_pinned_wizard_browser_subtotal_literals_match_calculator(): void
    {
        $this->actingAsAdmin();
        $import = $this->seedPaintLayoutRoom();
        $hand = $this->handComputedSectionLiterals($import['proposed']);

        $this->assertEqualsWithDelta(
            self::LITERAL_PAINTING_AFTER_IMPORT_PRE_HST,
            $hand['painting_section_pre_hst'],
            0.01,
            'Update LITERAL_PAINTING_AFTER_IMPORT_PRE_HST when paint pricing changes'
        );
        $this->assertEqualsWithDelta(
            self::LITERAL_FLOORING_AT_800_PRE_HST,
            $hand['flooring_section_pre_hst'],
            0.01,
            'Update LITERAL_FLOORING_AT_800_PRE_HST when flooring pricing changes'
        );
    }

    public function test_section_subtotals_live_update_and_paint_import_screenshots(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'br-subtotal-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $import = $this->seedPaintLayoutRoom();
        $literals = $this->browserSubtotalLiterals($import);

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-wizard-subtotal-browser.js');
        $out = storage_path('app/br-wizard-subtotal-browser.json');
        $shots = storage_path('app/qa-screenshots');
        @unlink($out);
        if (! is_dir($shots)) {
            mkdir($shots, 0777, true);
        }

        $output = [];
        $code = $this->runNode($script, [
            'BR_SUBTOTAL_EMAIL' => $user->email,
            'BR_SUBTOTAL_PASSWORD' => 'password',
            'BR_SUBTOTAL_BASE' => $base,
            'BR_SUBTOTAL_OUT' => $out,
            'BR_SUBTOTAL_SHOTS' => $shots,
            'BR_SUBTOTAL_CREATE_PATH' => route('admin.quotations.basement.create', [], false),
            'BR_SUBTOTAL_IMPORT_LAYOUT_Q' => $import['query'],
            'BR_SUBTOTAL_LAYOUTS_PATH' => route('admin.utilities.room-area.layouts', [], false),
            'BR_LITERAL_WALL_SF' => (string) $literals['wall_sf'],
            'BR_LITERAL_DRYWALL_WALL_SF' => (string) $literals['drywall_wall_sf'],
            'BR_LITERAL_CEILING_SF' => (string) $literals['ceiling_sf'],
            'BR_LITERAL_PAINT_CEILING_SF' => (string) $literals['paint_ceiling_sf'],
            'BR_EXPECT_PAINTING_SECTION' => sprintf('%.2f', $literals['painting_section_pre_hst']),
            'BR_EXPECT_FLOORING_SECTION' => sprintf('%.2f', $literals['flooring_section_pre_hst']),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Subtotal puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertTrue((bool) ($report['ok'] ?? false), json_encode($report));
        $this->assertGreaterThan(
            (float) ($report['create']['framingBefore'] ?? 0),
            (float) ($report['create']['framingAfter'] ?? 0)
        );

        $subdir = $shots;
        $this->assertFileExists($subdir.'/basement-section-subtotal-flooring-1280.png');
        $this->assertFileExists($subdir.'/basement-section-subtotal-flooring-390.png');
        $this->assertFileExists($subdir.'/basement-painting-after-import-1280.png');
        $this->assertFileExists($subdir.'/basement-painting-after-import-390.png');
        $this->assertFileExists($subdir.'/basement-reconciliation-1280.png');
        $this->assertFileExists($subdir.'/basement-reconciliation-mobile-390.png');
        $this->assertTrue((bool) ($report['importPaint']['wallSfOk'] ?? false), json_encode($report['importPaint'] ?? []));
        $this->assertEqualsWithDelta(
            (float) $literals['painting_section_pre_hst'],
            (float) ($report['importPaint']['paintingSection']['amount'] ?? 0),
            0.01,
            json_encode($report['importPaint'] ?? [])
        );
        $this->assertTrue((bool) ($report['reconciliation']['grandMatchesToolbar'] ?? false), json_encode($report['reconciliation'] ?? []));
        $this->assertEqualsWithDelta(
            (float) $literals['flooring_section_pre_hst'],
            (float) ($report['create']['flooringSection']['amount'] ?? 0),
            0.01,
            json_encode($report['create']['flooringSection'] ?? [])
        );

        $artifactDir = '/opt/cursor/artifacts';
        if (! is_dir($artifactDir)) {
            mkdir($artifactDir, 0777, true);
        }
        foreach (glob($subdir.'/basement-*.png') ?: [] as $file) {
            @copy($file, $artifactDir.'/'.basename($file));
        }
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @return array{painting_section_pre_hst: float, flooring_section_pre_hst: float}
     */
    private function handComputedSectionLiterals(array $proposed): array
    {
        $settings = BasementSettings::current();
        $proposedFiltered = array_filter($proposed, fn ($v) => $v !== null);
        $shared = [
            'corner_bead_by_height' => true,
            'drywall_boxing_v2' => true,
            'trim_material_lf_v2' => true,
            'owner_hourly_rate' => round(QuotationSharedLabourRates::ownerHourlyForNewQuotation(), 4),
            'helper_hourly_rate' => round((float) ($settings->helper_hourly_rate ?? 40), 4),
        ];
        $calc = new BasementQuotationCalculator;

        $afterImport = $calc->calculate(array_merge(
            BasementQuotationCalculator::defaultInputs(),
            $proposedFiltered,
            $shared,
            [
                'client_name' => 'Subtotal Browser Client',
                'area_sqft' => 229.61,
                'ceiling_height_ft' => 8.0,
            ]
        ), $settings);

        $flooringStep = $calc->calculate(array_merge(
            BasementQuotationCalculator::defaultInputs(),
            $proposedFiltered,
            $shared,
            [
                'client_name' => 'Subtotal Browser Client',
                'area_sqft' => 800.0,
                'partition_lf' => 25.0,
                'ceiling_height_ft' => 8.0,
                'flooring_type' => 'lvp',
                'flooring_sf' => 800.0,
                'flooring_waste_percent' => BasementQuotationCalculator::flooringWasteDefault('lvp'),
            ]
        ), $settings);

        return [
            'painting_section_pre_hst' => round((float) ($afterImport['summary']['section_subtotals_pre_hst']['painting'] ?? 0), 2),
            'flooring_section_pre_hst' => round((float) ($flooringStep['summary']['section_subtotals_pre_hst']['flooring'] ?? 0), 2),
        ];
    }

    /**
     * @param  array{query: string, proposed: array<string, mixed>}  $import
     * @return array{wall_sf: float, drywall_wall_sf: float, ceiling_sf: float, paint_ceiling_sf: float, flooring_section_pre_hst: float, painting_section_pre_hst: float}
     */
    private function browserSubtotalLiterals(array $import): array
    {
        $proposed = $import['proposed'];
        $hand = $this->handComputedSectionLiterals($proposed);
        $this->assertEqualsWithDelta(self::LITERAL_PAINTING_AFTER_IMPORT_PRE_HST, $hand['painting_section_pre_hst'], 0.01);
        $this->assertEqualsWithDelta(self::LITERAL_FLOORING_AT_800_PRE_HST, $hand['flooring_section_pre_hst'], 0.01);

        return [
            'wall_sf' => 501.52,
            'drywall_wall_sf' => (float) ($proposed['drywall_wall_sf'] ?? 501.52),
            'ceiling_sf' => 229.61,
            'paint_ceiling_sf' => (float) ($proposed['paint_ceiling_sf'] ?? 229.61),
            'painting_section_pre_hst' => self::LITERAL_PAINTING_AFTER_IMPORT_PRE_HST,
            'flooring_section_pre_hst' => self::LITERAL_FLOORING_AT_800_PRE_HST,
        ];
    }

    /**
     * @return array{query: string, proposed: array<string, mixed>}
     */
    private function seedPaintLayoutRoom(): array
    {
        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Browser paint fill',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Browser paint fill room',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 229.61,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 20 * $cm, 'y' => 0], 'end' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 20 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Browser paint fill room',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                    ]],
                ],
                'zanini' => [
                    'wallFinishes' => [
                        'w1' => ['drywall' => true],
                        'w2' => ['drywall' => true],
                        'w3' => ['drywall' => true],
                        'w4' => ['drywall' => true],
                    ],
                ],
            ],
        ]);

        $opt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Browser paint fill room']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$room->id);
        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt ?? [], 8, 'drywall');
        $this->assertEqualsWithDelta(501.52, (float) ($proposed['drywall_wall_sf'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(501.52, (float) ($proposed['paint_wall_sf'] ?? 0), 0.01);

        return ['query' => 'Browser paint fill room', 'proposed' => $proposed];
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

    private function startServer(): string
    {
        $port = $this->serverPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/br-wizard-subtotal-browser-router.php');
        $forced = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_URL' => $base,
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'SESSION_DRIVER' => 'file',
            'CACHE_DRIVER' => 'file',
        ];
        $forcePhp = '';
        foreach ($forced as $key => $value) {
            $forcePhp .= '$_ENV['.var_export($key, true).'] = $_SERVER['.var_export($key, true).'] = '.var_export($value, true).";\n";
            $forcePhp .= 'putenv('.var_export($key.'='.$value, true).");\n";
        }
        file_put_contents($router, '<?php
'.$forcePhp.'
$public = '.var_export(public_path(), true).';
$uri = urldecode((string) parse_url($_SERVER[\'REQUEST_URI\'] ?? \'/\', PHP_URL_PATH));
$path = $public.$uri;
if ($uri !== \'/\' && $uri !== \'\' && is_file($path)) {
    return false;
}
require $public.\'/index.php\';
');
        $cmd = 'exec '.escapeshellarg(PHP_BINARY)
            .' -S 127.0.0.1:'.$port
            .' -t '.escapeshellarg(public_path())
            .' '.escapeshellarg($router);
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        foreach ($forced as $key => $value) {
            $env[$key] = $value;
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir().'/br-subtotal-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/br-subtotal-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for subtotal browser');
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        for ($i = 0; $i < 50; $i++) {
            usleep(150000);
            $ctx = stream_context_create(['http' => ['timeout' => 0.4, 'ignore_errors' => true]]);
            $body = @file_get_contents($base.'/admin/login', false, $ctx);
            if (is_string($body) && $body !== '') {
                return $base;
            }
        }
        $this->stopServer();
        $this->fail('Browser server did not become ready on '.$base);
    }

    private function stopServer(): void
    {
        $port = $this->serverPort;
        if (is_resource($this->serverProcess)) {
            $status = @proc_get_status($this->serverProcess);
            $pid = (int) ($status['pid'] ?? 0);
            if ($pid > 0) {
                @exec('kill -TERM '.$pid.' 2>/dev/null');
            }
            @proc_terminate($this->serverProcess, 9);
        }
        @exec('pkill -f '.escapeshellarg('127.0.0.1:'.$port).' 2>/dev/null');
        $this->serverProcess = null;
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        User::query()->where('email', 'br-subtotal-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
