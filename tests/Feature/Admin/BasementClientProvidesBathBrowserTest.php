<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\User;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementClientProvidesBathBrowserTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8057;

    public function test_bathroom_client_provides_items_enabled_and_tick_lowers_total(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'br-bath-provides-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $quote = $this->seedBathroomQuote();
        $payload = $this->previewPayload($quote);
        $baseGrand = (float) $this->postJson(route('admin.quotations.basement.calculate-existing', $quote), $payload)
            ->assertOk()
            ->json('summary.grand_total');

        $itemKeys = ['vanity', 'toilet', 'shower_frame', 'tub'];
        $expectDrops = [];
        foreach ($itemKeys as $item) {
            $withGrand = (float) $this->postJson(
                route('admin.quotations.basement.calculate-existing', $quote),
                array_merge($payload, ['client_provides_bath_items' => [$item]])
            )->assertOk()->json('summary.grand_total');
            $expectDrops[$item] = round($baseGrand - $withGrand, 2);
            $this->assertGreaterThan(50.0, $expectDrops[$item], 'Expected meaningful drop for '.$item);
        }

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-client-provides-bath-browser.js');
        $out = storage_path('app/br-bath-provides-browser.json');
        @unlink($out);

        $output = [];
        $code = $this->runNode($script, [
            'BR_BATH_PROVIDES_EMAIL' => $user->email,
            'BR_BATH_PROVIDES_PASSWORD' => 'password',
            'BR_BATH_PROVIDES_BASE' => $base,
            'BR_BATH_PROVIDES_OUT' => $out,
            'BR_BATH_PROVIDES_EDIT_PATH' => route('admin.quotations.basement.edit', $quote, false),
            'BR_BATH_EXPECT_DROPS' => json_encode($expectDrops),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Bath client provides puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertTrue((bool) ($report['ok'] ?? false), json_encode($report));
        $this->assertTrue((bool) ($report['allItemsEnabled'] ?? false), json_encode($report));
        foreach ($itemKeys as $item) {
            $this->assertTrue((bool) ($report['items'][$item]['stillChecked'] ?? false), $item);
            $this->assertEqualsWithDelta(
                $expectDrops[$item],
                (float) ($report['items'][$item]['drop'] ?? 0),
                3.0,
                $item
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function previewPayload(BasementQuotation $quote): array
    {
        return array_merge(is_array($quote->inputs) ? $quote->inputs : [], [
            'client_name' => $quote->client_name,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'payment_installments' => 3,
        ]);
    }

    private function seedBathroomQuote(): BasementQuotation
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 500,
            'include_demolition' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'contractor_fee_percent' => 10,
            'insulate_walls' => false,
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
            'include_bathroom' => true,
            'bath_type' => 'full',
            'include_vanity' => true,
            'include_toilet' => true,
            'include_shower' => true,
            'include_tub' => true,
            'include_exhaust_fan' => false,
            'materials_override' => [
                'vanity' => ['unit_price' => 360],
                'toilet' => ['unit_price' => 280],
                'shower_kit' => ['unit_price' => 800],
                'tub_kit' => ['unit_price' => 750],
            ],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        return BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Bath client provides browser',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['final_quote_total'],
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
        $router = storage_path('app/br-bath-provides-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/br-bath-provides-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/br-bath-provides-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for bath provides browser');
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
        User::query()->where('email', 'br-bath-provides-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
