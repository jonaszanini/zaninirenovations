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
 * Browser lifecycle at 1280 and 390 for every non-painting type.
 *
 * Shed: real project-context draft → header Publish (auto-link via ?project=).
 * Other 10: server-created and linked through store+project, then browser
 * edit/reopen/PDF. Does not claim UI-from-blank creation for those 10.
 *
 * Workflows (click/type/select on visible enabled controls) run at both
 * widths. Viewport shots are evidence; 390 is not declared PASS from
 * screenshots alone. Painting is out of scope.
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

        [$cases, $quotes] = $this->seedBrowserCases();

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

        foreach ($cases as $spec) {
            $type = $spec['type'];
            $width = (string) $spec['viewport'];
            $key = $type.'@'.$width;
            $quote = $quotes[$key];
            $quote->refresh();
            $expected = $this->lifecycleFixtureExpected($type);
            $row = $report['types'][$key] ?? [];
            $this->assertIsArray($row, $key.' missing from browser report — workflow must run at this viewport, not reuse 1280 shots');
            $shot = $row['widths'][$width] ?? [];
            $this->assertFileExists((string) ($shot['viewportShot'] ?? ''), $key.' viewport shot');
            $this->assertFileExists((string) ($shot['fullShot'] ?? ''), $key.' full-page supplementary shot');
            $this->assertFileExists((string) ($shot['reopenViewportShot'] ?? ''), $key.' reopen viewport shot');
            $this->assertFileExists((string) ($shot['reopenFullShot'] ?? ''), $key.' reopen full-page supplementary shot');
            $this->assertSame('Publish', (string) ($shot['headerPublish'] ?? ''), $key.' header Publish');
            $this->assertSame('Save as draft', (string) ($shot['headerDraft'] ?? ''), $key.' Save as draft');
            $this->assertNotSame(
                (string) ($shot['headerPublish'] ?? ''),
                (string) ($shot['headerDraft'] ?? ''),
                $key.' Publish and Save as draft must stay distinct'
            );

            $this->assertTrue((bool) ($row['savedViaFooter'] ?? false), $key.' must save via footer Publish click');
            if ($type === 'shed') {
                $this->assertTrue((bool) ($row['publishedFromDraft'] ?? false), $key.' shed header Publish must leave the draft');
                $this->assertSame('project-draft-publish', (string) ($row['seed'] ?? ''));
            } else {
                $this->assertSame('server-created-linked-then-browser-edit', (string) ($row['seed'] ?? ''));
            }

            $this->assertFalse((bool) $quote->is_draft, $key.' must be published after the browser save');
            $this->assertEquals(
                $expected['browserEditQty'],
                $this->storedLifecycleQty($type, $quote),
                $key.' stored qty must match the fixture edit qty'
            );
            $this->assertEquals(
                $expected['browserEditQty'],
                (float) ($row['reopen']['fieldValue'] ?? 0),
                $key.' reopen field persisted via UI type'
            );
            $stored = $this->storedQuoteTotal($quote);
            $this->assertEqualsWithDelta(
                $expected['browserEditTotal'],
                $stored,
                0.02,
                $key.' stored total must match independent fixture expected '.$expected['browserEditTotal']
            );
            $money = ClientProject::formatMoney($expected['browserEditTotal']);
            $this->assertSame(
                $this->normalizeMoney($money),
                $this->normalizeMoney((string) ($row['reopen']['footerAmount'] ?? $shot['reopenFooterAmount'] ?? '')),
                $key.' reopen footer must show the independent expected total'
            );

            $pdfHref = (string) ($row['reopen']['pdfHref'] ?? $row['pdfUrl'] ?? '');
            $this->assertMatchesRegularExpression(
                '#/admin/client-projects/\d+/quotations/\d+/pdf#',
                $pdfHref,
                $key.' View PDF must be the linked client-project quotation route. href='.$pdfHref
            );
            $this->assertGreaterThan(100, (int) ($row['pdfBytes'] ?? 0), $key.' View PDF returned bytes');
            $this->assertSame(200, (int) ($row['pdfStatus'] ?? 0), $key.' View PDF HTTP 200');
            $this->assertPdfFor($type, $quote, $expected['browserEditTotal'], $expected['scopeWord'], $width);
        }
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, object>}
     */
    private function seedBrowserCases(): array
    {
        $quotes = [];
        $cases = [];
        $linked = [
            'flooring' => [
                'fields' => [['selector' => '#fl-sqft', 'value' => '80']],
                'revealClicks' => [],
            ],
            'trimming' => [
                'fields' => [['selector' => '#tm-floor-lf', 'value' => '40']],
                'revealClicks' => [],
            ],
            'standard' => [
                'fields' => [['selector' => '#sq-w0-labour-fixed-amount', 'value' => '500']],
                'revealClicks' => [],
            ],
            'basement' => [
                'fields' => [['selector' => '#br-area', 'value' => '400']],
                'revealClicks' => ['[data-br-goto="overview"]'],
                'openDetails' => 'details[data-br-step="overview"]',
            ],
            'millwork' => [
                'fields' => [['selector' => 'input[name="pieces[0][sets][0][qty]"]', 'value' => '3']],
                'revealClicks' => ['[data-mw-goto="pieces"]'],
                'openDetails' => 'details[data-br-step="pieces"]',
            ],
            'tile' => [
                'fields' => [['selector' => '#tl-total-area', 'value' => '20']],
                'revealClicks' => [],
            ],
            'fence' => [
                'fields' => [['selector' => '#fq-side-0', 'value' => '48']],
                'revealClicks' => [],
            ],
            'paving' => [
                'fields' => [['selector' => 'input[name="stone_entries[0][area_sq_ft]"]', 'value' => '40']],
                'revealClicks' => [],
            ],
            'stairs' => [
                'fields' => [
                    ['selector' => '#sq-total-steps', 'value' => '8'],
                    ['selector' => 'input[name="segments[0][qty]"]', 'value' => '8'],
                    ['selector' => '#sq-material-price', 'value' => '40'],
                ],
                'revealClicks' => [],
            ],
            'low-deck' => [
                'fields' => [['selector' => '#ld-length', 'value' => '10']],
                'revealClicks' => [],
            ],
        ];

        foreach ([1280, 390] as $viewport) {
            [$shed, $shedProject] = $this->storeDraft('shed');
            $key = 'shed@'.$viewport;
            $quotes[$key] = $shed;
            $cases[] = [
                'type' => 'shed',
                'viewport' => $viewport,
                'seed' => 'project-draft-publish',
                'editHref' => '/admin/quotations/shed/'.$shed->id.'/edit?project='.$shedProject->id,
                'expectDraft' => true,
                'revealClicks' => ['[data-sh-goto="size"]'],
                'openDetails' => 'details[data-br-step="size"]',
                'fields' => [['selector' => '#sh-length', 'value' => '8']],
            ];

            foreach ($linked as $type => $cfg) {
                [$quote, $cpq] = $this->publishLinkedQuote($type);
                $expected = $this->lifecycleFixtureExpected($type);
                $this->assertEqualsWithDelta(
                    $expected['publishTotal'],
                    $this->storedQuoteTotal($quote),
                    0.02,
                    $type.'@'.$viewport.' server-created fixture must match independent publish total'
                );
                $this->assertNotNull($cpq, $type.' must be linked through store+project, not a later test-only POST');
                $caseKey = $type.'@'.$viewport;
                $quotes[$caseKey] = $quote;
                $projectId = (int) ($quote->client_project_id ?: $cpq->client_project_id);
                $cases[] = [
                    'type' => $type,
                    'viewport' => $viewport,
                    'seed' => 'server-created-linked-then-browser-edit',
                    'editHref' => '/admin/quotations/'.$type.'/'.$quote->id.'/edit?project='.$projectId,
                    'expectDraft' => false,
                    'revealClicks' => $cfg['revealClicks'],
                    'openDetails' => $cfg['openDetails'] ?? null,
                    'fields' => $cfg['fields'],
                ];
            }
        }

        return [$cases, $quotes];
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

    private function assertPdfFor(string $type, object $quote, float $expectedTotal, string $scopeWord, string $width): void
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', $this->quoteSourceType($type))
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull(
            $cpq,
            $type.'@'.$width.' must already have a linked client quotation from project context (no test-only link POST)'
        );
        $this->assertEqualsWithDelta($expectedTotal, $cpq->pdfGrandTotal(), 0.02, $type.'@'.$width.' PDF total vs independent expected');

        $project = $cpq->project;
        $this->assertNotNull($project, $type.' CPQ project relation');
        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/quote-lifecycle-browser-'.$type.'-'.$width.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $text = $this->extractPdfText($pdfPath);
        $money = ClientProject::formatMoney($expectedTotal);
        $this->assertTrue(
            str_contains($text, $money) || str_contains($this->pdfPlainText($text), $this->pdfPlainText($money)),
            $type.'@'.$width.' PDF text must include independent expected total '.$money
        );
        $this->assertStringContainsString((string) $quote->client_name, $text);
        $this->assertStringContainsStringIgnoringCase($scopeWord, $text, $type.'@'.$width.' PDF must include fixture scope "'.$scopeWord.'"');
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
