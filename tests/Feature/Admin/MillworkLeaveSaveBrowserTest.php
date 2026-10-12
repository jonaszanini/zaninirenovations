<?php

namespace Tests\Feature\Admin;

use App\Models\MillworkQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class MillworkLeaveSaveBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private $serverPort = 8017;

    public function test_dirty_form_warns_on_leave_and_ctrl_s_still_saves(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'mw-leave-save@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);
        $this->get(route('admin.quotations.millwork.create'))->assertOk();

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/millwork-leave-save-browser.js');
        $out = storage_path('app/millwork-leave-save-browser.json');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'MW_EMAIL' => $user->email,
            'MW_PASSWORD' => 'password',
            'MW_BASE' => $base,
            'MW_OUT' => $out,
        ], $output);
        $this->stopServer();
        $this->assertSame(0, $code, "Millwork leave/save puppeteer failed:\n".implode("\n", $output)."\n".@file_get_contents($out));

        $report = json_decode((string) file_get_contents($out), true);
        $this->assertIsArray($report);
        $this->assertSame(0, (int) ($report['cleanDialogs'] ?? -1));
        $this->assertGreaterThanOrEqual(1, (int) ($report['dirtyDialogs'] ?? 0));
        $this->assertTrue(! empty($report['ctrlSClicked']));
        $this->assertMatchesRegularExpression('/millwork\/\d+|millwork\/[^\/]+\/edit/', (string) ($report['afterUrl'] ?? ''));
        $this->assertSame(0, (int) ($report['afterSaveDialogs'] ?? -1));
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
        $port = (int) $this->serverPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/millwork-leave-save-router.php');
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
        $cmd = escapeshellarg(PHP_BINARY)
            .' -S 127.0.0.1:'.$port
            .' -t '.escapeshellarg(public_path())
            .' '.escapeshellarg($router);
        if (PHP_OS_FAMILY !== 'Windows') {
            $cmd = 'exec '.$cmd;
        }
        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        $env['APP_ENV'] = 'testing';
        $env['SESSION_DRIVER'] = 'file';
        $env['CACHE_DRIVER'] = 'file';
        $env['DB_CONNECTION'] = (string) config('database.default');
        $env['DB_HOST'] = (string) config('database.connections.mysql.host');
        $env['DB_PORT'] = (string) config('database.connections.mysql.port');
        $env['DB_DATABASE'] = (string) config('database.connections.mysql.database');
        $env['DB_USERNAME'] = (string) config('database.connections.mysql.username');
        $env['DB_PASSWORD'] = (string) config('database.connections.mysql.password');
        $env['APP_KEY'] = (string) config('app.key');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir().'/millwork-leave-save-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/millwork-leave-save-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for millwork leave/save');
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
        $this->fail('Millwork leave/save server did not become ready on '.$base);
    }

    private function stopServer(): void
    {
        $port = (int) $this->serverPort;
        if (is_resource($this->serverProcess)) {
            $status = @proc_get_status($this->serverProcess);
            $pid = (int) ($status['pid'] ?? 0);
            if ($pid > 0) {
                if (PHP_OS_FAMILY === 'Windows') {
                    @exec('taskkill /F /T /PID '.$pid.' 2>NUL');
                } else {
                    @exec('kill -TERM '.$pid.' 2>/dev/null');
                    @exec('pkill -TERM -P '.$pid.' 2>/dev/null');
                }
            }
            @proc_terminate($this->serverProcess, 9);
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            @exec('pkill -f '.escapeshellarg('127.0.0.1:'.$port).' 2>/dev/null');
        }
        $this->serverProcess = null;
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        MillworkQuotation::query()->where('client_name', 'Ctrl S check')->delete();
        User::query()->where('email', 'mw-leave-save@zanini.ca')->delete();
        parent::tearDown();
    }
}
