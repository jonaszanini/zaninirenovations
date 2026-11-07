<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\PavingMarbleType;
use App\Models\PavingQuotation;
use App\Models\User;
use App\Services\PavingQuotationCalculator;
use App\Support\QuotationSharedLabourRates;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ExtractsPdfText;

class PavingMarbleBrowserTest extends IntegrationTestCase
{
    use ExtractsPdfText;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8034;

    public function test_ordinary_paving_without_marble_is_not_blocked_and_enabled_marble_keeps_bounds(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'pq-marble-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $project = ClientProject::factory()->create([
            'name' => 'Paving marble browser project',
            'client_name' => 'Ordinary Paving Browser',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $expected = $this->expectedPavingMoney();
        $this->assertNotEquals(
            $expected['offMoney'],
            $expected['defaultMoney'],
            '80 sf marble-off must differ from the create-page default area total'
        );
        $this->assertNotEquals(
            $expected['offMoney'],
            $expected['onMoney'],
            'marble-on 50 sf must change the 80 sf marble-off total'
        );

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/paving-marble-constraint-browser.js');
        $out = storage_path('app/pq-marble-browser.json');
        $shots = storage_path('app/pq-marble-shots');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'PQ_MARBLE_EMAIL' => $user->email,
            'PQ_MARBLE_PASSWORD' => 'password',
            'PQ_MARBLE_BASE' => $base,
            'PQ_MARBLE_OUT' => $out,
            'PQ_MARBLE_SHOTS' => $shots,
            'PQ_MARBLE_EXPECT' => json_encode([
                'offMoney' => $expected['offMoney'],
                'onMoney' => $expected['onMoney'],
                'defaultMoney' => $expected['defaultMoney'],
                'createPath' => route('admin.quotations.paving.create', ['project' => $project->id], false),
                'customScope' => 'CUSTOM PAVING SCOPE COPY',
            ]),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Paving marble puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        $off = $report['off'] ?? [];
        $this->assertFalse((bool) ($off['marbleChecked'] ?? true));
        $this->assertTrue((bool) ($off['fieldsHidden'] ?? false), 'marble fields must stay hidden when marble is off');
        $this->assertSame('none', (string) ($off['fieldsDisplay'] ?? ''));
        $this->assertTrue((bool) ($off['areaDisabled'] ?? false), 'marble area must be disabled when marble is off');
        $this->assertSame('', (string) ($off['areaMin'] ?? '0.1'));
        $this->assertTrue((bool) ($off['areaValid'] ?? false), 'disabled marble area 0 must not fail HTML5 min');
        $this->assertTrue((bool) ($off['formValid'] ?? false), 'ordinary paving without marble must be savable: '.json_encode($off['firstInvalid'] ?? null));

        $enabledZero = $report['enabledZero'] ?? [];
        $this->assertTrue((bool) ($enabledZero['marbleChecked'] ?? false));
        $this->assertFalse((bool) ($enabledZero['fieldsHidden'] ?? true));
        $this->assertNotSame('none', (string) ($enabledZero['fieldsDisplay'] ?? 'none'));
        $this->assertFalse((bool) ($enabledZero['areaDisabled'] ?? true));
        $this->assertSame('0.1', (string) ($enabledZero['areaMin'] ?? ''));
        $this->assertFalse((bool) ($enabledZero['areaValid'] ?? true), 'enabled marble area 0 must fail min 0.1');

        $tooSmall = $report['enabledTooSmall'] ?? [];
        $this->assertFalse((bool) ($tooSmall['areaValid'] ?? true), 'enabled marble 0.05 must fail min 0.1');

        $blockedZero = $report['blockedPublishZero'] ?? [];
        $this->assertTrue((bool) ($blockedZero['urlUnchanged'] ?? false), 'publish must not navigate when enabled marble area is 0');
        $blockedSmall = $report['blockedPublishTooSmall'] ?? [];
        $this->assertTrue((bool) ($blockedSmall['urlUnchanged'] ?? false), 'publish must not navigate when enabled marble area is 0.05');

        $valid = $report['enabledValid'] ?? [];
        $this->assertTrue((bool) ($valid['areaValid'] ?? false), 'enabled marble 50 sq ft must pass');
        $this->assertTrue((bool) ($valid['formValid'] ?? false), 'enabled marble with type and area must be savable: '.json_encode($valid['firstInvalid'] ?? null));

        $offAfter = $report['offAfterToggle'] ?? [];
        $this->assertFalse((bool) ($offAfter['marbleChecked'] ?? true));
        $this->assertTrue((bool) ($offAfter['areaDisabled'] ?? false));
        $this->assertTrue((bool) ($offAfter['formValid'] ?? false), 'turning marble off again must unblock save: '.json_encode($offAfter['firstInvalid'] ?? null));

        foreach (['off-1280', 'off-390', 'on-1280', 'on-390', 'offAfter-390'] as $key) {
            $row = $report['overflow'][$key] ?? [];
            $inner = (float) ($row['innerWidth'] ?? 0);
            $scroll = (float) ($row['scrollWidth'] ?? 9999);
            $right = (float) ($row['marbleRight'] ?? 9999);
            $this->assertGreaterThan(0, $inner, $key.' overflow innerWidth');
            $this->assertLessThanOrEqual($inner + 2, $scroll, $key.' horizontal overflow scrollWidth='.$scroll);
            $this->assertLessThanOrEqual($inner + 2, $right, $key.' marble section overflows right='.$right);
        }

        $this->assertStringContainsString('/admin/quotations/paving/', (string) ($report['afterSaveUrl'] ?? ''));
        $this->assertSame('0', (string) ($report['afterSaveIsDraft'] ?? '1'), 'ordinary marble-off save must be publish, not draft');
        $this->assertFalse((bool) ($report['afterSaveMarbleChecked'] ?? true));
        $this->assertTrue((bool) ($report['afterSaveAreaDisabled'] ?? false));

        $this->assertSettledMoney($report['offCreate1280'] ?? [], $expected['offMoney'], 'off create 1280');
        $this->assertSettledMoney($report['offCreate390'] ?? [], $expected['offMoney'], 'off create 390');
        $this->assertSettledMoney($report['onCreate1280'] ?? [], $expected['onMoney'], 'on create 1280');
        $this->assertSettledMoney($report['onCreate390'] ?? [], $expected['onMoney'], 'on create 390');
        $this->assertSettledMoney($report['afterSave1280'] ?? [], $expected['offMoney'], 'reopen 1280');
        $this->assertSettledMoney($report['afterSave390'] ?? [], $expected['offMoney'], 'reopen 390');
        $this->assertSame(
            $expected['offMoney'],
            (string) (($report['offAfterSettled'] ?? [])['footer'] ?? ''),
            'turning marble off must restore the marble-off total'
        );

        $quote = PavingQuotation::query()->where('client_name', 'Ordinary Paving Browser')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertFalse((bool) (($quote->inputs ?? [])['marble_enabled'] ?? true));
        $this->assertNull(($quote->calculation ?? [])['marble'] ?? null);
        $this->assertEqualsWithDelta($expected['offTotal'], (float) $quote->final_quote_total, 0.01);
        $this->assertStringContainsString('CUSTOM PAVING SCOPE COPY', (string) $quote->work_description);

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_PAVING)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);
        $this->assertEqualsWithDelta($expected['offTotal'], $cpq->pdfGrandTotal(), 0.01);
        $this->assertStringContainsString('CUSTOM PAVING SCOPE COPY', (string) $cpq->work_description);

        $pdfPath = (string) (($report['pdf'] ?? [])['path'] ?? '');
        $this->assertTrue((bool) (($report['pdf'] ?? [])['pdfMagic'] ?? false), 'browser PDF magic');
        $this->assertGreaterThan(1000, (int) (($report['pdf'] ?? [])['bytes'] ?? 0));
        $this->assertFileExists($pdfPath);
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString($expected['offMoney'], $pdfText);
        $this->assertStringContainsString('CUSTOM PAVING SCOPE COPY', $pdfText);
        $this->assertStringNotContainsString($expected['onMoney'], $pdfText);
        $this->writeArtifact($pdfPath, 'paving-ordinary-no-marble.pdf');
        $this->writeArtifactString('paving-ordinary-no-marble.txt', $pdfText);

        foreach (['off-1280', 'off-390', 'on-1280', 'on-390', 'reopen-1280', 'reopen-390'] as $key) {
            $shot = (string) ($report['shots'][$key] ?? '');
            $this->assertFileExists($shot, $key.' screenshot missing');
            $this->writeArtifact($shot, basename($shot));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertSettledMoney(array $row, string $expectedMoney, string $label): void
    {
        $totals = is_array($row['totals'] ?? null) ? $row['totals'] : (is_array($row['settled'] ?? null) ? $row['settled'] : []);
        $footer = (string) ($totals['footer'] ?? ($row['settled']['footer'] ?? ''));
        $this->assertSame($expectedMoney, $footer, $label.' footer');
        if (isset($totals['totalQuote']) && $totals['totalQuote'] !== '') {
            $this->assertSame(
                $this->moneyCents($expectedMoney),
                $this->moneyCents((string) $totals['totalQuote']),
                $label.' live Total quote'
            );
        }
        if (isset($totals['footerCents'], $totals['totalCents'])) {
            $this->assertSame((int) $totals['footerCents'], (int) $totals['totalCents'], $label.' footer cents vs summary');
        }
    }

    /**
     * @return array{defaultMoney: string, offMoney: string, onMoney: string, defaultTotal: float, offTotal: float, onTotal: float}
     */
    private function expectedPavingMoney(): array
    {
        $defaults = PavingQuotationCalculator::defaultInputs();
        $defaults['owner_hourly_rate'] = QuotationSharedLabourRates::ownerHourlyForNewQuotation();
        $calculator = new PavingQuotationCalculator();
        $defaultCalc = $calculator->calculate($defaults);
        $offCalc = $calculator->calculate(array_merge($defaults, [
            'total_area_sq_ft' => 80,
            'marble_enabled' => false,
        ]));
        $marbleId = PavingMarbleType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($marbleId);
        $onCalc = $calculator->calculate(array_merge($defaults, [
            'total_area_sq_ft' => 80,
            'marble_enabled' => true,
            'marble_type_id' => $marbleId,
            'marble_area_sq_ft' => 50,
            'marble_depth_in' => 2,
            'marble_include_geotextile' => true,
        ]));

        return [
            'defaultMoney' => ClientProject::formatMoney((float) ($defaultCalc['summary']['final_quote_total'] ?? 0)),
            'offMoney' => ClientProject::formatMoney((float) ($offCalc['summary']['final_quote_total'] ?? 0)),
            'onMoney' => ClientProject::formatMoney((float) ($onCalc['summary']['final_quote_total'] ?? 0)),
            'defaultTotal' => (float) ($defaultCalc['summary']['final_quote_total'] ?? 0),
            'offTotal' => (float) ($offCalc['summary']['final_quote_total'] ?? 0),
            'onTotal' => (float) ($onCalc['summary']['final_quote_total'] ?? 0),
        ];
    }

    private function moneyCents(string $text): int
    {
        return (int) round(((float) preg_replace('/[^0-9.]/', '', $text)) * 100);
    }

    private function writeArtifact(string $source, string $name): void
    {
        $destDir = '/opt/cursor/artifacts';
        if (! is_dir($destDir) && ! @mkdir($destDir, 0777, true)) {
            return;
        }
        @copy($source, $destDir.'/'.$name);
    }

    private function writeArtifactString(string $name, string $contents): void
    {
        $destDir = '/opt/cursor/artifacts';
        if (! is_dir($destDir) && ! @mkdir($destDir, 0777, true)) {
            return;
        }
        @file_put_contents($destDir.'/'.$name, $contents);
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
        $router = storage_path('app/pq-marble-browser-router.php');
        $forced = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
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
            1 => ['file', sys_get_temp_dir().'/pq-marble-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/pq-marble-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for paving marble browser');
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
        $this->fail('Paving marble browser server did not become ready on '.$base);
    }

    private function stopServer(): void
    {
        $port = $this->serverPort;
        if (is_resource($this->serverProcess)) {
            $status = @proc_get_status($this->serverProcess);
            $pid = (int) ($status['pid'] ?? 0);
            if ($pid > 0) {
                @exec('kill -TERM '.$pid.' 2>/dev/null');
                @exec('pkill -TERM -P '.$pid.' 2>/dev/null');
            }
            @proc_terminate($this->serverProcess, 9);
        }
        @exec('pkill -f '.escapeshellarg('127.0.0.1:'.$port).' 2>/dev/null');
        $this->serverProcess = null;
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        User::query()->where('email', 'pq-marble-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
