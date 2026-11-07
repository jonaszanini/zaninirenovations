<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\User;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class BasementCeilingLegacyBrowserTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8036;

    public function test_legacy_blank_and_zero_hang_save_reopen_and_pdf_keep_original_totals(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-ceiling-legacy-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        $blank = $this->seedLegacyQuote('blank hang', [
            'drywall_ceiling_sf' => '',
        ]);
        $zero = $this->seedLegacyQuote('zero hang', [
            'drywall_ceiling_sf' => 0,
        ]);

        $cases = [
            $this->browserCase('legacy-blank-hang', $blank),
            $this->browserCase('legacy-zero-hang', $zero),
        ];

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/basement-ceiling-legacy-browser.js');
        $out = storage_path('app/qh-ceiling-legacy-browser.json');
        $shots = storage_path('app/qa-screenshots');
        @unlink($out);
        if (! is_dir($shots)) {
            mkdir($shots, 0777, true);
        }

        $output = [];
        $code = $this->runNode($script, [
            'QH_CEILING_EMAIL' => $user->email,
            'QH_CEILING_PASSWORD' => 'password',
            'QH_CEILING_BASE' => $base,
            'QH_CEILING_OUT' => $out,
            'QH_CEILING_SHOTS' => $shots,
            'QH_CEILING_CASES' => json_encode($cases),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Ceiling legacy puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);

        foreach ($cases as $spec) {
            $row = $report['cases'][$spec['key']] ?? [];
            $this->assertSame($spec['expectedMoney'], (string) ($row['before']['settled'] ?? ''), $spec['key'].' before footer');
            $this->assertSame($spec['expectedMoney'], (string) ($row['after']['settled'] ?? ''), $spec['key'].' after footer');
            $this->assertSame($spec['expectedMoney'], (string) ($row['widths']['1280']['footer'] ?? ''), $spec['key'].' 1280 footer');
            $this->assertSame($spec['expectedMoney'], (string) ($row['widths']['390']['footer'] ?? ''), $spec['key'].' 390 footer');
            $this->assertSame('1', (string) ($row['before']['split'] ?? ''), $spec['key'].' split before');
            $this->assertSame('0', (string) ($row['before']['flag'] ?? ''), $spec['key'].' flag before');
            $this->assertTrue((bool) ($row['before']['hasNotice'] ?? false), $spec['key'].' notice');
            $this->assertFalse((bool) ($row['widths']['390']['overflow'] ?? true), $spec['key'].' 390 overflow');
            $this->assertFileExists((string) ($row['widths']['1280']['shot'] ?? ''));
            $this->assertFileExists((string) ($row['widths']['390']['shot'] ?? ''));
            $this->assertTrue((bool) ($row['pdf']['pdfMagic'] ?? false), $spec['key'].' PDF magic');
            $this->assertStringContainsString('pdf', strtolower((string) ($row['pdf']['contentType'] ?? '')));

            if ($spec['key'] === 'legacy-blank-hang') {
                $this->assertStringContainsString('1,300', (string) ($row['before']['hang'] ?? ''));
                $this->assertStringContainsString('1,200', (string) ($row['before']['paint'] ?? ''));
                $this->assertStringContainsString('1,300', (string) ($row['after']['hang'] ?? ''));
            }
            if ($spec['key'] === 'legacy-zero-hang') {
                $this->assertStringContainsString('1,200', (string) ($row['before']['paint'] ?? ''));
                $this->assertStringContainsString('1,200', (string) ($row['after']['paint'] ?? ''));
            }
        }

        foreach ([$blank, $zero] as $seeded) {
            $quote = $seeded['quote']->fresh();
            $this->assertEqualsWithDelta(
                (float) $seeded['total'],
                (float) $quote->final_quote_total,
                0.01
            );
            $this->assertArrayNotHasKey('ceiling_hang_unified', $quote->inputs ?? []);
            $pdfHtml = $quote->scopeDescriptionForPdf();
            $this->assertStringContainsString('Paint ceiling', $pdfHtml);
            $cpq = $seeded['cpq']->fresh();
            $this->assertEqualsWithDelta((float) $seeded['total'], (float) $cpq->pdfGrandTotal(), 0.05);
        }

        $this->copyShots($shots, [
            'legacy-blank-hang-1280.png',
            'legacy-blank-hang-390.png',
            'legacy-zero-hang-1280.png',
            'legacy-zero-hang-390.png',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{quote: BasementQuotation, project: ClientProject, cpq: ClientProjectQuotation, total: float, money: string}
     */
    private function seedLegacyQuote(string $name, array $overrides): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 1200,
            'ceiling_height_ft' => 8,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
            'flooring_type' => 'none',
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
        ], $overrides);
        unset($inputs['ceiling_hang_unified'], $inputs['paint_ceiling_sf']);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $total = (float) $calc['summary']['final_quote_total'];

        $project = ClientProject::factory()->create([
            'name' => 'Ceiling legacy '.$name,
            'client_name' => 'Legacy Ceiling '.$name,
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => $project->client_name,
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'version' => 1,
            'payment_installments' => 3,
            'client_project_id' => $project->id,
        ]);
        $cpq = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Basement quotation',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-LEG-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'source_type' => ClientProjectQuotation::SOURCE_BASEMENT,
            'source_id' => $quote->id,
            'source_snapshot' => ['quote_number' => $quote->quote_number],
            'items' => [
                [
                    'description' => 'Basement renovation (labour)',
                    'amount' => (float) $calc['summary']['labor_pre_hst'],
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Basement renovation materials',
                    'amount' => (float) $calc['summary']['materials_pre_hst'],
                    'apply_hst' => true,
                ],
            ],
        ]);

        return [
            'quote' => $quote,
            'project' => $project,
            'cpq' => $cpq,
            'total' => $total,
            'money' => ClientProject::formatMoney($total),
        ];
    }

    /**
     * @param  array{quote: BasementQuotation, project: ClientProject, cpq: ClientProjectQuotation, total: float, money: string}  $seeded
     * @return array<string, mixed>
     */
    private function browserCase(string $key, array $seeded): array
    {
        return [
            'key' => $key,
            'editPath' => route('admin.quotations.basement.edit', $seeded['quote'], false),
            'pdfPath' => route('admin.client-projects.quotation.pdf', [$seeded['project'], $seeded['cpq']], false),
            'expectedMoney' => $seeded['money'],
            'expectedTotal' => $seeded['total'],
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
        $router = storage_path('app/qh-ceiling-legacy-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-ceiling-legacy-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-ceiling-legacy-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for ceiling browser');
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
        $this->fail('Ceiling browser server did not become ready on '.$base);
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
        User::query()->where('email', 'qh-ceiling-legacy-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
