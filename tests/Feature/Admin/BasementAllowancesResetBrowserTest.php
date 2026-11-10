<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\User;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementAllowancesResetBrowserTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8058;

    public function test_reset_restores_missing_permit_line_and_clears_stale_banner(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'br-allowances-reset@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $quote = $this->seedQuoteMissingPermitLine();
        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-allowances-reset-browser.js');
        $out = storage_path('app/br-allowances-reset-browser.json');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'BR_ALLOW_RESET_EMAIL' => $user->email,
            'BR_ALLOW_RESET_PASSWORD' => 'password',
            'BR_ALLOW_RESET_BASE' => $base,
            'BR_ALLOW_RESET_OUT' => $out,
            'BR_ALLOW_RESET_EDIT_PATH' => route('admin.quotations.basement.edit', $quote, false),
            'BR_ALLOW_RESET_PERMIT_SNIPPET' => 'CA$600.00',
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Allowances reset puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertTrue((bool) ($report['ok'] ?? false), json_encode($report));
    }

    private function seedQuoteMissingPermitLine(): BasementQuotation
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 500,
            'include_building_permit' => true,
            'building_permit_amount' => 600,
            'electrical_lines' => [
                ['description' => 'Electrician', 'amount' => 7000, 'hst_included' => false],
            ],
            'client_scope_allowances' => 'Electrical work allowance CA$7,910.00 (HST included in the trade line).',
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        return BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Allowances reset browser',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['grand_total'],
            'version' => 1,
            'payment_installments' => 3,
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
        $port = $this->serverPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/br-allowances-reset-router.php');
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
            $forcePhp .= 'putenv('.var_export($key.'='.$value', true).");\n";
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
            1 => ['file', sys_get_temp_dir().'/br-allowances-reset-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/br-allowances-reset-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server');
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
        User::query()->where('email', 'br-allowances-reset@zanini.ca')->delete();
        parent::tearDown();
    }
}
