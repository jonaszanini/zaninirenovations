<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\StandardQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class QuotationEditorFlashBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8057;

    public function test_standard_and_basement_toast_and_compact_status_at_1280_and_390(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-flash-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $standard = StandardQuotation::query()->create([
            'quote_number' => 'SQ-2026-0407',
            'client_name' => 'Flash Standard Client',
            'is_draft' => true,
            'display_title' => 'Kitchen work',
            'materials' => [],
            'version' => 1,
        ]);
        $basement = BasementQuotation::query()->create([
            'quote_number' => 'BR-FLASH-1',
            'client_name' => 'Flash Basement Client',
            'is_draft' => true,
            'inputs' => ['area_sqft' => 850, 'ceiling_height_ft' => 8, 'room_count' => 3],
            'calculation' => [],
            'final_quote_total' => 0,
            'version' => 1,
        ]);

        $cases = [
            [
                'key' => 'standard',
                'path' => route('admin.quotations.standard.edit', $standard, false),
                'message' => 'Standard quotation SQ-2026-0407 saved and linked to this project.',
                'compact' => 'Saved and linked to this project',
            ],
            [
                'key' => 'basement',
                'path' => route('admin.quotations.basement.edit', $basement, false),
                'message' => 'Basement quotation BR-FLASH-1 saved and linked to this project.',
                'compact' => 'Saved and linked to this project',
            ],
        ];

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/quotation-editor-flash-browser.js');
        $out = storage_path('app/qh-flash-browser.json');
        $shots = storage_path('app/qa-screenshots');
        @unlink($out);
        if (! is_dir($shots)) {
            mkdir($shots, 0777, true);
        }

        $output = [];
        $code = $this->runNode($script, [
            'QH_FLASH_EMAIL' => $user->email,
            'QH_FLASH_PASSWORD' => 'password',
            'QH_FLASH_BASE' => $base,
            'QH_FLASH_OUT' => $out,
            'QH_FLASH_SHOTS' => $shots,
            'QH_FLASH_CASES' => json_encode($cases),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Flash puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertTrue((bool) ($report['ok'] ?? false), $raw);

        foreach ($cases as $spec) {
            $row = $report['cases'][$spec['key']] ?? [];
            foreach (['1280', '390'] as $width) {
                $metrics = $row['widths'][$width] ?? [];
                $this->assertTrue((bool) ($metrics['toastVisible'] ?? false), $spec['key'].' @'.$width.' toast');
                $this->assertStringContainsString($spec['message'], (string) ($metrics['toastText'] ?? ''));
                $this->assertStringContainsString($spec['compact'], (string) ($metrics['statusText'] ?? ''));
                $this->assertFalse((bool) ($metrics['hasBanner'] ?? true), $spec['key'].' @'.$width.' old banner');
                $this->assertSame('polite', (string) ($metrics['ariaLive'] ?? ''));
                $this->assertTrue((bool) ($metrics['hasClose'] ?? false), $spec['key'].' @'.$width.' close');
                $this->assertLessThan(80, (int) ($metrics['toastTop'] ?? 99), $spec['key'].' @'.$width.' toast sits at the top');
                $this->assertFalse((bool) ($metrics['coversFooter'] ?? true), $spec['key'].' @'.$width.' toast covers sticky bar');
                if ($width === '390') {
                    $this->assertTrue((bool) ($metrics['menuVisible'] ?? false), $spec['key'].' 390 hamburger is visible');
                    $this->assertFalse((bool) ($metrics['coversMenu'] ?? true), $spec['key'].' 390 toast overlaps the hamburger');
                    $this->assertGreaterThan(
                        (int) ($metrics['menuRight'] ?? 0),
                        (int) ($metrics['toastLeft'] ?? 0),
                        $spec['key'].' 390 toast stays to the right of the hamburger'
                    );
                    $this->assertLessThanOrEqual(320, (int) ($metrics['toastWidth'] ?? 999), $spec['key'].' 390 toast max width');
                    $this->assertLessThanOrEqual(
                        (int) ($metrics['vw'] ?? 0) - 8,
                        (int) ($metrics['toastRight'] ?? 0),
                        $spec['key'].' 390 toast right margin'
                    );
                    $this->assertGreaterThanOrEqual(40, (int) ($metrics['closeWidth'] ?? 0), $spec['key'].' 390 close width');
                    $this->assertGreaterThanOrEqual(40, (int) ($metrics['closeHeight'] ?? 0), $spec['key'].' 390 close height');
                }
                $shot = (string) ($row['shots'][$width] ?? '');
                $this->assertFileExists($shot, $spec['key'].' @'.$width.' screenshot');
                $this->assertGreaterThan(2000, (int) filesize($shot), $spec['key'].' @'.$width.' screenshot size');
            }
        }
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
        $router = storage_path('app/qh-flash-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-flash-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-flash-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for flash browser');
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
        $this->fail('Flash browser server did not become ready on '.$base);
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
        User::query()->where('email', 'qh-flash-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
