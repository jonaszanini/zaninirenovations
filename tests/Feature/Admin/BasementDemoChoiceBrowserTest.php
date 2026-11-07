<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\User;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class BasementDemoChoiceBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8044;

    public function test_create_unselected_and_saved_yes_no_keep_totals_at_1280_and_390(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-demo-choice-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $no = $this->seedQuote('no', false);
        $yes = $this->seedQuote('yes', true);

        $cases = [
            [
                'key' => 'demo-create-unselected',
                'path' => route('admin.quotations.basement.create', [], false),
                'expectedMoney' => null,
                'expectYes' => false,
                'expectNo' => false,
            ],
            [
                'key' => 'demo-edit-no',
                'path' => route('admin.quotations.basement.edit', $no['quote'], false),
                'expectedMoney' => $no['money'],
                'expectYes' => false,
                'expectNo' => true,
            ],
            [
                'key' => 'demo-edit-yes',
                'path' => route('admin.quotations.basement.edit', $yes['quote'], false),
                'expectedMoney' => $yes['money'],
                'expectYes' => true,
                'expectNo' => false,
            ],
        ];

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-demo-choice-browser.js');
        $out = storage_path('app/qh-demo-choice-browser.json');
        $shots = storage_path('app/qa-screenshots');
        @unlink($out);
        if (! is_dir($shots)) {
            mkdir($shots, 0777, true);
        }

        $output = [];
        $code = $this->runNode($script, [
            'QH_DEMO_EMAIL' => $user->email,
            'QH_DEMO_PASSWORD' => 'password',
            'QH_DEMO_BASE' => $base,
            'QH_DEMO_OUT' => $out,
            'QH_DEMO_SHOTS' => $shots,
            'QH_DEMO_CASES' => json_encode($cases),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Demo choice puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach ($cases as $spec) {
            $row = $report['cases'][$spec['key']] ?? [];
            $editor = $row['editor'] ?? [];
            $this->assertSame($spec['expectYes'], (bool) ($editor['yesChecked'] ?? null), $spec['key'].' yes');
            $this->assertSame($spec['expectNo'], (bool) ($editor['noChecked'] ?? null), $spec['key'].' no');
            if ($spec['expectedMoney']) {
                $want = preg_replace('/\s+/', '', (string) $spec['expectedMoney']);
                $this->assertSame($want, preg_replace('/\s+/', '', (string) ($row['settled'] ?? '')), $spec['key'].' settled');
                $this->assertSame($want, preg_replace('/\s+/', '', (string) ($row['widths']['1280']['footer'] ?? '')), $spec['key'].' 1280 footer');
                $this->assertSame($want, preg_replace('/\s+/', '', (string) ($row['widths']['390']['footer'] ?? '')), $spec['key'].' 390 footer');
            } else {
                $this->assertFalse((bool) ($editor['yesChecked'] ?? true), 'create yes must be off');
                $this->assertFalse((bool) ($editor['noChecked'] ?? true), 'create no must be off');
                $this->assertTrue((bool) ($editor['fieldsHidden'] ?? false), 'create demo fields hidden');
            }
            $this->assertFalse((bool) ($row['widths']['390']['overflow'] ?? true), $spec['key'].' 390 overflow');
            $this->assertFileExists((string) ($row['widths']['1280']['shot'] ?? ''));
            $this->assertFileExists((string) ($row['widths']['390']['shot'] ?? ''));
        }

        $this->assertGreaterThan((float) $no['total'], (float) $yes['total']);

        $this->copyShots($shots, [
            'demo-create-unselected-1280.png',
            'demo-create-unselected-390.png',
            'demo-edit-no-1280.png',
            'demo-edit-no-390.png',
            'demo-edit-yes-1280.png',
            'demo-edit-yes-390.png',
        ]);
    }

    /**
     * @return array{quote: BasementQuotation, total: float, money: string}
     */
    private function seedQuote(string $name, bool $includeDemo): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'include_demolition' => $includeDemo,
            'demo_include_flooring' => true,
            'demo_flooring_sf' => 400,
            'demo_bin_qty' => 1,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'insulate_walls' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $total = (float) $calc['summary']['final_quote_total'];
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Demo browser '.$name,
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        return [
            'quote' => $quote,
            'total' => $total,
            'money' => ClientProject::formatMoney($total),
        ];
    }

    /**
     * @param  list<string>  $files
     */
    private function copyShots(string $shots, array $files): void
    {
        $destDir = '/opt/cursor/artifacts';
        if (! is_dir($destDir)) {
            @mkdir($destDir, 0777, true);
        }
        if (! is_dir($destDir)) {
            return;
        }
        foreach ($files as $file) {
            $src = $shots.'/'.$file;
            if (is_file($src)) {
                @copy($src, $destDir.'/'.$file);
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
        $router = storage_path('app/qh-demo-choice-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-demo-choice-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-demo-choice-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for demo browser');
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
        $this->fail('Demo browser server did not become ready on '.$base);
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
        User::query()->where('email', 'qh-demo-choice-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
