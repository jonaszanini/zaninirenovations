<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class QuotationEditorFooterBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8031;

    public function test_footer_stays_pinned_updates_from_shared_total_and_proxies_save(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-footer-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/quotation-editor-footer-browser.js');
        $out = storage_path('app/qh-footer-browser.json');
        $shots = storage_path('app/qh-footer-shots');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'QH_FOOTER_EMAIL' => $user->email,
            'QH_FOOTER_PASSWORD' => 'password',
            'QH_FOOTER_BASE' => $base,
            'QH_FOOTER_OUT' => $out,
            'QH_FOOTER_SHOTS' => $shots,
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Footer puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach (['basement', 'millwork', 'standard', 'fence'] as $type) {
            foreach (['1280', '390'] as $width) {
                $row = $report[$type][$width] ?? [];
                $this->assertTrue((bool) ($row['hasFooter'] ?? false), "{$type} @{$width} missing footer");
                $this->assertSame(1, (int) ($row['footerCount'] ?? 0), "{$type} @{$width} stacked footers");
                $this->assertTrue((bool) ($row['hasSave'] ?? false), "{$type} @{$width} missing footer Save");
                $this->assertTrue((bool) ($row['hasAmount'] ?? false), "{$type} @{$width} missing footer amount");
                $this->assertTrue((bool) ($row['hasHeaderSave'] ?? false), "{$type} @{$width} missing header Save");
                $this->assertTrue((bool) ($row['hasHeaderDraft'] ?? false), "{$type} @{$width} missing Save as draft");
                $this->assertTrue((bool) ($row['footerJs'] ?? false), "{$type} @{$width} missing footer JS");
                $this->assertTrue((bool) ($row['pinned'] ?? false), "{$type} @{$width} footer is not viewport-fixed");
                $this->assertTrue((bool) ($row['saveInView'] ?? false), "{$type} @{$width} Save not in view");
                $this->assertTrue((bool) ($row['amountInView'] ?? false), "{$type} @{$width} amount not in view");
                $this->assertSame('CA$1,234.56', (string) ($row['liveAfter'] ?? ''), "{$type} @{$width} did not mirror quote:grand-total");
                $this->assertSame(1, (int) ($row['headerClicks'] ?? 0), "{$type} @{$width} footer Save did not click header Save quotation");
                $this->assertSame(1, (int) ($row['draftClicks'] ?? 0), "{$type} @{$width} Save as draft must fire its own control");
                $this->assertSame('publish', (string) ($row['headerSaveIntent'] ?? ''), "{$type} @{$width} header Save intent");
                $this->assertSame('draft', (string) ($row['headerDraftIntent'] ?? ''), "{$type} @{$width} header draft intent");
                $this->assertSame('publish', (string) ($row['footerSaveIntent'] ?? ''), "{$type} @{$width} footer Save intent");
                $this->assertSame('Save quotation', (string) ($row['footerAria'] ?? ''), "{$type} @{$width} footer aria");
                $this->assertSame('Save quotation', (string) ($row['footerFull'] ?? ''), "{$type} @{$width} footer full label");
                $this->assertSame('Publish', (string) ($row['footerShort'] ?? ''), "{$type} @{$width} footer short must be Publish, not Save");
                $this->assertStringContainsString('Save quotation', (string) ($row['headerSaveLabel'] ?? ''));
                $this->assertSame('Save as draft', (string) ($row['headerDraftLabel'] ?? ''), "{$type} @{$width} draft label");
                if ((int) $width === 390) {
                    $this->assertTrue((bool) ($row['footerShortVisible'] ?? false), "{$type} @{$width} compact footer must show Publish");
                    $this->assertFalse((bool) ($row['footerFullVisible'] ?? true), "{$type} @{$width} must not show a second Save quotation in the footer");
                } else {
                    $this->assertTrue((bool) ($row['footerFullVisible'] ?? false), "{$type} @{$width} desktop footer shows Save quotation");
                    $this->assertFalse((bool) ($row['footerShortVisible'] ?? true), "{$type} @{$width} desktop footer must not use the compact Publish label");
                }
                $this->assertFileExists((string) ($row['shot'] ?? ''));
                $this->assertStringContainsString('-full.png', (string) ($row['shot'] ?? ''));
                if ($type === 'basement') {
                    $this->assertSame('1 of 14', (string) ($row['stepStatus'] ?? ''), "basement @{$width} footer must match section 1 of 14 after JS");
                    $this->assertSame('1 of 14', (string) ($row['overviewNum'] ?? ''), "basement @{$width} overview num");
                    $this->assertSame('14 of 14', (string) ($row['scopeNum'] ?? ''), "basement @{$width} client-scope num");
                    $this->assertNotSame('1 of 13', (string) ($row['stepStatus'] ?? ''));
                    $after = $row['afterNext'] ?? [];
                    $this->assertSame('2 of 14', (string) ($after['stepStatus'] ?? ''), "basement @{$width} Next must advance 1 of 14 → 2 of 14");
                    $this->assertSame('demolition', (string) ($after['activeStep'] ?? ''));
                    $this->assertSame('→ Next', (string) ($after['nextLabel'] ?? ''));
                }
            }
        }

        foreach (['1280', '390'] as $width) {
            $row = $report['painting'][$width] ?? [];
            $this->assertFalse((bool) ($row['hasFooter'] ?? true), "Painting @{$width} must not get the sticky footer");
            $this->assertFalse((bool) ($row['footerJs'] ?? true), "Painting @{$width} must not load footer JS");
            $this->assertTrue((bool) ($row['hasHeaderSave'] ?? false), "Painting @{$width} keeps header Save");
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
        $router = storage_path('app/qh-footer-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-footer-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-footer-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for footer browser');
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
        $this->fail('Footer browser server did not become ready on '.$base);
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
        User::query()->where('email', 'qh-footer-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
