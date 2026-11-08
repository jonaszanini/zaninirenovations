<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;
use Tests\Support\PublishesLinkedQuotations;

/**
 * Genuine browser gaps the HTTP lifecycle test cannot see: sticky footer after
 * JS, footer Publish click, 1280/390 layout, View PDF navigation.
 *
 * Bounded to flooring / trimming / shed (simple quantity fields on edit).
 * Wizard/JS-generated editors (basement, millwork, fence, paving, stairs,
 * tile, low-deck, standard works) stay HTTP-only until that expansion is
 * approved. Painting is out of scope.
 */
class QuotationLifecycleBrowserTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;
    use PublishesLinkedQuotations;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $serverProcess = null;

    private int $serverPort = 8041;

    public function test_browser_draft_publish_qty_edit_reopen_and_pdf(): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $user = User::factory()->create([
            'email' => 'qh-lifecycle-browser@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);

        [$shed, $shedProject] = $this->storeDraft('shed');
        [$flooring] = $this->publishLinkedQuote('flooring');
        [$trimming] = $this->publishLinkedQuote('trimming');

        $flooringT1 = $this->quoteCalculatorTotal('flooring', $flooring);
        $trimmingT1 = $this->quoteCalculatorTotal('trimming', $trimming);
        $this->assertGreaterThan(0, $flooringT1);
        $this->assertGreaterThan(0, $trimmingT1);

        $cases = [
            [
                'type' => 'shed',
                'editHref' => '/admin/quotations/shed/'.$shed->id.'/edit',
                'expectDraft' => true,
                'fieldSelector' => '#sh-length',
                'newValue' => '8',
            ],
            [
                'type' => 'flooring',
                'editHref' => '/admin/quotations/flooring/'.$flooring->id.'/edit',
                'expectDraft' => false,
                'fieldSelector' => '#fl-sqft',
                'newValue' => '80',
            ],
            [
                'type' => 'trimming',
                'editHref' => '/admin/quotations/trimming/'.$trimming->id.'/edit',
                'expectDraft' => false,
                'fieldSelector' => '#tm-floor-lf',
                'newValue' => '40',
            ],
        ];

        $base = $this->startServer();
        $script = base_path('tests/Feature/Admin/quotation-lifecycle-browser.js');
        $out = storage_path('app/qh-lifecycle-browser.json');
        $shots = storage_path('app/qh-lifecycle-shots');
        @unlink($out);

        $sha = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD'));
        $output = [];
        $code = $this->runNode($script, [
            'QH_LC_EMAIL' => $user->email,
            'QH_LC_PASSWORD' => 'password',
            'QH_LC_BASE' => $base,
            'QH_LC_OUT' => $out,
            'QH_LC_SHOTS' => $shots,
            'QH_LC_SHA' => $sha,
            'QH_LC_CASES' => json_encode($cases),
            'PUPPETEER_EXECUTABLE_PATH' => (string) (getenv('PUPPETEER_EXECUTABLE_PATH') ?: '/usr/bin/google-chrome'),
        ], $output);
        $this->stopServer();

        $raw = (string) @file_get_contents($out);
        $this->assertSame(0, $code, "Lifecycle puppeteer failed:\n".implode("\n", $output)."\n".$raw);
        $report = json_decode($raw, true);
        $this->assertIsArray($report);
        $this->assertSame($sha, (string) ($report['sha'] ?? ''), 'browser report SHA must match committed HEAD');
        $this->assertArrayNotHasKey('error', $report);

        $shed->refresh();
        $flooring->refresh();
        $trimming->refresh();
        $this->assertFalse((bool) $shed->is_draft, 'shed draft must publish from the header Publish click');
        $shedT = $this->quoteCalculatorTotal('shed', $shed);
        $flooringT2 = $this->quoteCalculatorTotal('flooring', $flooring);
        $trimmingT2 = $this->quoteCalculatorTotal('trimming', $trimming);
        $this->assertGreaterThan(0, $shedT);
        $this->assertNotEquals(round($flooringT1, 2), round($flooringT2, 2), 'flooring browser qty edit must persist a new total');
        $this->assertNotEquals(round($trimmingT1, 2), round($trimmingT2, 2), 'trimming browser qty edit must persist a new total');

        foreach (['shed', 'flooring', 'trimming'] as $type) {
            $row = $report['types'][$type] ?? [];
            $this->assertIsArray($row, $type.' missing from browser report');
            foreach (['1280', '390'] as $width) {
                $shot = $row['widths'][$width] ?? [];
                $this->assertFileExists((string) ($shot['shot'] ?? ''), $type.' '.$width.' full shot');
                $this->assertFileExists((string) ($shot['viewportShot'] ?? ''), $type.' '.$width.' viewport shot');
                $this->assertFileExists((string) ($shot['reopenShot'] ?? ''), $type.' '.$width.' reopen shot');
                $this->assertSame('Publish', (string) ($shot['headerPublish'] ?? ''), $type.' '.$width.' header Publish');
                $this->assertSame('Save as draft', (string) ($shot['headerDraft'] ?? ''), $type.' '.$width.' Save as draft');
            }
            $this->assertNotSame(
                (string) (($row['widths']['1280']['headerPublish'] ?? '')),
                (string) (($row['widths']['1280']['headerDraft'] ?? '')),
                $type.' Publish and Save as draft must stay distinct'
            );
        }

        $this->assertEquals(8.0, (float) ($report['types']['shed']['reopen']['fieldValue'] ?? 0), 'shed length persisted');
        $this->assertEquals(80.0, (float) ($report['types']['flooring']['reopen']['fieldValue'] ?? 0), 'flooring sqft persisted');
        $this->assertEquals(40.0, (float) ($report['types']['trimming']['reopen']['fieldValue'] ?? 0), 'trimming lf persisted');

        $this->assertSame(ClientProject::formatMoney($shedT), $this->normalizeMoney((string) ($report['types']['shed']['reopen']['footerAmount'] ?? '')));
        $this->assertSame(ClientProject::formatMoney($flooringT2), $this->normalizeMoney((string) ($report['types']['flooring']['reopen']['footerAmount'] ?? '')));
        $this->assertSame(ClientProject::formatMoney($trimmingT2), $this->normalizeMoney((string) ($report['types']['trimming']['reopen']['footerAmount'] ?? '')));

        $shed->refresh();
        $this->assertFalse((bool) $shed->is_draft, 'shed must stay published before link');
        $this->assertFalse($shed->isLinked(), 'shed browser save does not auto-link without ?project=');
        $this->from(route('admin.quotations.shed.edit', $shed))
            ->post(route('admin.quotations.shed.link', $shed), [
                'client_project_id' => $shedProject->id,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('success', 'Linked to project "'.$shedProject->name.'".');
        $shed->refresh();
        $this->assertTrue($shed->isLinked(), 'shed must be linked after link POST');
        $this->assertPdfFor('shed', $shed, $shedT);
        $this->assertPdfFor('flooring', $flooring, $flooringT2);
        $this->assertPdfFor('trimming', $trimming, $trimmingT2);

        $this->assertTrue((bool) ($report['types']['shed']['publishedFromDraft'] ?? false), 'shed header Publish must leave the draft');
        foreach (['flooring', 'trimming'] as $type) {
            $this->assertGreaterThan(100, (int) ($report['types'][$type]['pdfBytes'] ?? 0), $type.' View PDF returned bytes');
            $this->assertSame(200, (int) ($report['types'][$type]['pdfStatus'] ?? 0), $type.' View PDF HTTP 200');
        }
    }

    /**
     * @return array{0: object, 1: ClientProject}
     */
    private function storeDraft(string $type): array
    {
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $payload = array_merge($this->publishPayload($type, $project->client_name), [
            'save_intent' => 'draft',
        ]);
        $this->post(route('admin.quotations.'.$type.'.store', ['project' => $project->id]), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
        $quote = $this->quoteModelClass($type)::query()
            ->where('client_name', $project->client_name)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue((bool) $quote->is_draft);

        return [$quote, $project];
    }

    private function assertPdfFor(string $type, object $quote, float $total): void
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', $this->quoteSourceType($type))
            ->where('source_id', $quote->id)
            ->first();
        if ($cpq === null) {
            $project = ClientProject::factory()->create([
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'billing_type' => ClientProject::BILLING_FIXED,
                'client_name' => $quote->client_name,
            ]);
            $this->post(route('admin.quotations.'.$type.'.link', $quote), [
                'client_project_id' => $project->id,
            ])->assertSessionDoesntHaveErrors();
            $quote->refresh();
            $cpq = ClientProjectQuotation::query()
                ->where('source_type', $this->quoteSourceType($type))
                ->where('source_id', $quote->id)
                ->first();
        }
        $this->assertNotNull($cpq, $type.' CPQ after browser save');
        $this->assertEqualsWithDelta($total, $cpq->pdfGrandTotal(), 0.02, $type.' PDF total vs stored');

        $project = $cpq->project;
        $this->assertNotNull($project, $type.' CPQ project relation');
        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/quote-lifecycle-browser-'.$type.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $text = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString(ClientProject::formatMoney($total), $text);
        $this->assertStringContainsString((string) $quote->client_name, $text);
    }

    private function normalizeMoney(string $value): string
    {
        return preg_replace('/\s+/', '', $value) ?? $value;
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
        $router = storage_path('app/qh-lifecycle-browser-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-lifecycle-browser-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-lifecycle-browser-serve.err', 'w'],
        ];
        $this->serverProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->serverProcess), 'Could not start PHP server for lifecycle browser');
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
        $this->fail('Lifecycle browser server did not become ready on '.$base);
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
        User::query()->where('email', 'qh-lifecycle-browser@zanini.ca')->delete();
        parent::tearDown();
    }
}
