<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class QuotationHelpTipBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8037;

    public function test_paving_and_stairs_help_opens_from_info_icons_at_1280_and_390(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-help-tip-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/quotation-help-tip-browser.js');
        $out = storage_path('app/qh-help-tip-browser.json');
        $shots = storage_path('app/qh-help-tip-shots');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'QH_HELP_EMAIL' => $user->email,
            'QH_HELP_PASSWORD' => 'password',
            'QH_HELP_BASE' => $base,
            'QH_HELP_OUT' => $out,
            'QH_HELP_SHOTS' => $shots,
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Help-tip puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach (['paving', 'stairs'] as $type) {
            foreach (['1280', '390'] as $width) {
                $row = $report[$type][$width] ?? [];
                $closed = is_array($row['closed'] ?? null) ? $row['closed'] : [];
                $opened = is_array($row['opened'] ?? null) ? $row['opened'] : [];
                $this->assertTrue((bool) ($closed['hasTip'] ?? false), "{$type} @{$width} missing info tip");
                $this->assertFalse((bool) ($closed['expanded'] ?? true), "{$type} @{$width} tip started open");
                $this->assertFalse((bool) ($closed['openClass'] ?? true), "{$type} @{$width} wrap started open");
                $this->assertTrue((bool) ($opened['expanded'] ?? false), "{$type} @{$width} tip did not open");
                $this->assertTrue((bool) ($opened['openClass'] ?? false), "{$type} @{$width} wrap did not get is-open");
                $this->assertNotSame('', (string) ($opened['bubbleText'] ?? ''));
                $this->assertTrue((bool) ($closed['hasFooter'] ?? false), "{$type} @{$width} missing sticky footer");
                $this->assertTrue((bool) ($opened['hasFooter'] ?? false), "{$type} @{$width} lost sticky footer when open");
                $this->assertTrue((bool) ($closed['footerPinned'] ?? false), "{$type} @{$width} footer not pinned closed");
                $this->assertTrue((bool) ($opened['footerPinned'] ?? false), "{$type} @{$width} footer not pinned open");
                $this->assertFileExists((string) ($closed['shot'] ?? ''));
                $this->assertFileExists((string) ($opened['shot'] ?? ''));
                $this->writeArtifact((string) $closed['shot'], $type.'-help-closed-'.$width.'.png');
                $this->writeArtifact((string) $opened['shot'], $type.'-help-open-'.$width.'.png');
                foreach (array_merge($closed['leftoverHints'] ?? [], $opened['leftoverHints'] ?? []) as $hint) {
                    $this->assertStringNotContainsString('Extra stone quantity', (string) $hint);
                    $this->assertStringNotContainsString('Required for a published quotation', (string) $hint);
                }
            }
        }
        $this->writeArtifactString('qh-help-tip-browser.json', $raw);
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
        $router = storage_path('app/qh-help-tip-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-help-tip-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-help-tip-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for help-tip browser');
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
        $this->fail('Help-tip browser server did not become ready on '.$base);
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

    protected function tearDown(): void
    {
        $this->stopServer();
        User::query()->where('email', 'qh-help-tip-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
