<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class BasementHeaderLfBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8056;

    public function test_framing_header_lf_label_and_info_tip_at_1280_and_390(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'br-header-lf-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-header-lf-browser.js');
        $shots = storage_path('app/qa-screenshots');
        if (! is_dir($shots)) {
            mkdir($shots, 0777, true);
        }

        $output = [];
        $code = $this->runNode($script, [
            'BR_HEADER_EMAIL' => $user->email,
            'BR_HEADER_PASSWORD' => 'password',
            'BR_HEADER_BASE' => $base,
            'BR_HEADER_SHOTS' => $shots,
            'BR_HEADER_CREATE_PATH' => route('admin.quotations.basement.create', [], false),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = implode("\n", $output);
        $this->assertSame(0, $code, "Header lf puppeteer failed:\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertTrue((bool) ($report['ok'] ?? false), json_encode($report));

        $this->assertFileExists($shots.'/basement-header-lf-framing-1280.png');
        $this->assertFileExists($shots.'/basement-header-lf-framing-390.png');
        $this->assertStringContainsString('4', (string) ($report['labor']['hours'] ?? ''));
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
        $router = storage_path('app/br-header-lf-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/br-header-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/br-header-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for header lf browser');
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
        User::query()->where('email', 'br-header-lf-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
