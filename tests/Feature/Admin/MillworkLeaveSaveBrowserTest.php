<?php

namespace Tests\Feature\Admin;

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

        $cmd = 'cd '.escapeshellarg(base_path())
            .' && MW_EMAIL='.escapeshellarg($user->email)
            .' MW_PASSWORD='.escapeshellarg('password')
            .' MW_BASE='.escapeshellarg($base)
            .' MW_OUT='.escapeshellarg($out)
            .' node '.escapeshellarg($script);
        $output = [];
        $code = 0;
        exec($cmd.' 2>&1', $output, $code);
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

    private function startServer(): string
    {
        $port = (int) $this->serverPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/millwork-leave-save-router.php');
        file_put_contents($router, '<?php
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
        parent::tearDown();
    }
}
