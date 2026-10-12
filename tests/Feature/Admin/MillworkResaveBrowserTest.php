<?php

namespace Tests\Feature\Admin;

use App\Models\MillworkQuotation;
use App\Models\User;
use App\Services\MillworkQuotationCalculator;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class MillworkResaveBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private $serverPort = 8024;

    public function test_untouched_v1_v2_and_v3_quotes_keep_total_and_stored_fields(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'mw-resave-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $quotes = [
            $this->storeQuote('Resave v1', 1, [
                'type' => 'shelf',
                'qty' => 1,
                'unit' => 'in',
                'length' => 48,
                'stock_length_ft' => 10,
                'columns' => 4,
                'rows' => 3,
                'profile' => 'crown',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]),
            $this->storeQuote('Resave v2', 2, [
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'qty' => 1,
                'length' => 20,
                'linear_ft' => '',
                'stock_length_ft' => 8,
                'columns' => 4,
                'rows' => 5,
                'edge_banding' => false,
            ]),
            $this->storeQuote('Resave v3', 3, [
                'type' => 'shelf',
                'qty' => 1,
                'unit' => 'in',
                'height' => 30,
                'length' => 40,
                'stock_length_ft' => 9,
                'columns' => 2,
                'rows' => 4,
                'shelf_count' => 1,
                'profile' => 'chair_rail',
                'include_back' => true,
                'edge_banding' => false,
                '_calc_version' => 3,
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]),
        ];
        $expected = [];
        foreach ($quotes as $quote) {
            $this->assertGreaterThan(0, (float) $quote->final_quote_total, $quote->client_name);
            $expected[$quote->id] = [
                'total' => (float) $quote->final_quote_total,
                'version' => (int) $quote->cut_plan_version,
                'piece' => $quote->inputs['pieces'][0],
                'name' => $quote->client_name,
            ];
        }

        $base = $this->startServer();
        $jobs = array_map(function (MillworkQuotation $quote) {
            return [
                'id' => $quote->id,
                'path' => route('admin.quotations.millwork.edit', $quote, false),
            ];
        }, $quotes);
        $output = [];
        $code = $this->runNode(base_path('tests/Feature/Admin/millwork-resave-browser.js'), [
            'MW_EMAIL' => $user->email,
            'MW_PASSWORD' => 'password',
            'MW_BASE' => $base,
            'MW_JOBS' => json_encode($jobs),
        ], $output);
        $this->stopServer();
        $raw = trim(implode("\n", $output));
        $this->assertSame(0, $code, "Millwork resave puppeteer failed:\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach ($quotes as $index => $quote) {
            $before = $expected[$quote->id];
            $saved = $report['saved'][$index] ?? [];
            $this->assertSame([], $saved['disabled'] ?? ['missing'], $before['name'].' disabled piece inputs');
            $this->assertSame('', (string) ($saved['error'] ?? 'missing'), $before['name'].' save error');
            $fresh = MillworkQuotation::query()->findOrFail($quote->id);
            $this->assertEqualsWithDelta(
                $before['total'],
                (float) $fresh->final_quote_total,
                0.02,
                $before['name'].' total changed'
            );
            $this->assertSame($before['version'], (int) $fresh->cut_plan_version, $before['name']);
            $piece = $fresh->inputs['pieces'][0];
            foreach (['length', 'stock_length_ft', 'columns', 'rows', 'profile'] as $key) {
                $this->assertEquals($before['piece'][$key], $piece[$key] ?? null, $before['name'].' '.$key);
            }
            if ($before['version'] === 3) {
                $this->assertSame(3, (int) ($piece['_calc_version'] ?? 0));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function storeQuote(string $name, int $version, array $piece): MillworkQuotation
    {
        $inputs = [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'sheet_id' => '',
            '_calc_version' => $version,
            '_cut_plan_version' => $version,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, null, $version);
        $total = round((float) ($calc['summary']['final_quote_total'] ?? $calc['summary']['grand_total'] ?? 0), 2);

        return MillworkQuotation::query()->create([
            'quote_number' => 'MW-RESAVE-'.$version,
            'client_name' => $name,
            'is_draft' => true,
            'cut_plan_version' => $version,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
        ]);
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
        $router = storage_path('app/millwork-resave-browser-router.php');
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
        foreach ($forced as $key => $value) {
            $env[$key] = $value;
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', sys_get_temp_dir().'/millwork-resave-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/millwork-resave-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for millwork resave browser');
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
        $this->fail('Millwork resave browser server did not become ready on '.$base);
    }

    private function stopServer(): void
    {
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
        $this->serverProcess = null;
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        MillworkQuotation::query()->whereIn('client_name', [
            'Resave v1',
            'Resave v2',
            'Resave v3',
        ])->delete();
        User::query()->where('email', 'mw-resave-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
