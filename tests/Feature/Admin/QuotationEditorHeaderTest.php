<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationEditorHeaderTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $layoutServerProcess = null;

    private int $layoutServerPort = 8012;

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4: string|null}>
     */
    public function quotationTypes(): array
    {
        return [
            'standard' => ['standard', 'admin.quotations.standard.create', 'Standard', true, 'sq-btn-save'],
            'basement' => ['basement', 'admin.quotations.basement.create', 'Basement', true, 'br-btn-draft'],
            'millwork' => ['millwork', 'admin.quotations.millwork.create', 'Millwork', true, 'mw-btn-draft'],
            'flooring' => ['flooring', 'admin.quotations.flooring.create', 'Flooring', true, 'fl-btn-draft'],
            'trimming' => ['trimming', 'admin.quotations.trimming.create', 'Trim', true, 'tm-btn-draft'],
            'painting' => ['painting', 'admin.quotations.painting.create', 'Painting', true, 'pt-btn-draft'],
            'shed' => ['shed', 'admin.quotations.shed.create', 'Shed', true, 'sh-btn-draft'],
            'tile' => ['tile', 'admin.quotations.tile.create', 'Tile', true, 'tl-btn-draft'],
            'fence' => ['fence', 'admin.quotations.fence.create', 'Fence', true, 'fq-btn-save'],
            'paving' => ['paving', 'admin.quotations.paving.create', 'Paving', true, 'pq-btn-draft'],
            'stairs' => ['stairs', 'admin.quotations.stairs.create', 'Stairs', true, 'sq-btn-draft'],
            'low_deck' => ['low-deck', 'admin.quotations.low-deck.create', 'Low deck', true, 'ld-btn-save'],
        ];
    }

    /**
     * @dataProvider quotationTypes
     */
    public function test_unlinked_quote_skips_project_crumb_and_orders_actions(
        string $type,
        string $createRoute,
        string $typeLabel,
        bool $hasDraft,
        string $hotkeyId
    ): void {
        $this->actingAsAdmin();

        $html = $this->get(route($createRoute))
            ->assertOk()
            ->assertSee('data-qh-header', false)
            ->assertSee('qh-crumb__quotations', false)
            ->assertSee('>'.$typeLabel.'<', false)
            ->assertDontSee('Back to project')
            ->assertDontSee('Back to list')
            ->getContent();

        $this->assertStringNotContainsString('qh-crumb__project', $html);
        $this->assertStringContainsString('Quotations', $html);
        $this->assertTrue(
            str_contains($html, route('admin.quotations.index'))
                || str_contains($html, route($this->typeIndexRoute($type))),
            "Unlinked {$type} must link Quotations to the hub or type list"
        );

        $this->assertActionOrder($html, $hasDraft);
        $this->assertMatchesRegularExpression(
            '/<(button)[^>]*class="[^"]*qh-actions__cancel[^"]*"[^>]*data-qh-cancel=/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="'.preg_quote($hotkeyId, '/').'"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="'.preg_quote($hotkeyId, '/').'"/',
            $html
        );
        if (in_array($type, ['millwork', 'shed'], true)) {
            $this->assertStringContainsString('← Back', $html);
            $this->assertStringContainsString('→ Next', $html);
            $this->assertTrue(
                strpos($html, 'qh-actions') < strpos($html, 'qh-wizard'),
                "{$type} wizard nav must sit below the action row"
            );
        }
        if ($type === 'basement') {
            $this->assertStringContainsString('id="br-step-nav"', $html);
            $this->assertStringContainsString('id="br-next"', $html);
            $this->assertTrue(
                strpos($html, 'id="br-sticky-total"') > strpos($html, 'data-qh-header'),
                'Basement Back/Next must stay in the sticky bottom bar'
            );
        }
    }

    /**
     * @dataProvider quotationTypes
     */
    public function test_linked_project_crumb_points_at_the_project(
        string $type,
        string $createRoute,
        string $typeLabel,
        bool $hasDraft,
        string $hotkeyId
    ): void {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Header '.$typeLabel.' Job',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $html = $this->get(route($createRoute, ['project' => $project->id]))
            ->assertOk()
            ->assertSee('data-qh-header', false)
            ->assertSee('qh-crumb__project', false)
            ->assertSee($project->name, false)
            ->assertSee(route('admin.client-projects.show', $project).'#quotation', false)
            ->assertSee('>'.$typeLabel.'<', false)
            ->assertDontSee('Back to project')
            ->getContent();

        $this->assertActionOrder($html, $hasDraft);
        $this->assertStringContainsString('id="'.$hotkeyId.'"', $html);
    }

    public function test_unlinked_quote_with_directory_client_skips_project_and_links_client(): void
    {
        $this->actingAsAdmin();

        $client = Client::query()->create([
            'name' => 'Standalone Header Client',
            'email' => 'standalone-header@example.com',
        ]);

        $html = view('admin.partials.quotation_editor_header', [
            'title' => 'New standard quotation',
            'typeLabel' => 'Standard',
            'project' => null,
            'client' => $client,
            'clientName' => $client->name,
            'listUrl' => route('admin.quotations.standard.index'),
            'formId' => 'sq-standard-form',
            'saveId' => 'sq-btn-save',
            'showDraft' => false,
        ])->render();

        $this->assertStringNotContainsString('qh-crumb__project', $html);
        $this->assertStringContainsString('qh-crumb__client', $html);
        $this->assertStringContainsString($client->name, $html);
        $this->assertStringContainsString(route('admin.clients.edit', $client), $html);
        preg_match('/<a class="qh-crumb__link qh-crumb__quotations"[^>]*href="([^"]+)"/', $html, $m);
        $this->assertSame(route('admin.quotations.index'), html_entity_decode($m[1] ?? ''));
    }

    public function test_client_crumb_uses_client_id_not_the_first_matching_name(): void
    {
        $this->actingAsAdmin();

        Client::query()->create(['name' => 'Maria Rossi', 'email' => 'maria-one@example.com']);
        $second = Client::query()->create(['name' => 'Maria Rossi', 'email' => 'maria-two@example.com']);

        $html = $this->get(route('admin.quotations.fence.create', ['client' => $second->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.clients.edit', $second), $html);
        preg_match('/<a class="qh-crumb__link qh-crumb__quotations"[^>]*href="([^"]+)"/', $html, $m);
        $this->assertSame(route('admin.quotations.index'), html_entity_decode($m[1] ?? ''));
    }

    public function test_header_geometry_at_phone_tablet_and_desktop_in_both_themes(): void
    {
        $user = User::factory()->create([
            'email' => 'qh-header@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed — geometry cannot silently skip');
        $probe = [];
        $probeCode = 0;
        exec('cd '.escapeshellarg(base_path()).' && node -e "require(\'puppeteer\'); console.log(\'ok\')"', $probe, $probeCode);
        $this->assertSame(0, $probeCode, 'Puppeteer must be require()-able: '.implode("\n", $probe));

        $project = ClientProject::factory()->create([
            'name' => 'Geometry Header Job',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $base = $this->startLayoutServer();
        $script = storage_path('app/qh-header-geometry.js');
        file_put_contents($script, <<<'JS'
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');
const email = process.env.QH_GEO_EMAIL;
const password = process.env.QH_GEO_PASSWORD;
const base = process.env.QH_GEO_BASE;
const projectId = process.env.QH_GEO_PROJECT;
const outDir = process.env.QH_GEO_OUT || path.join(process.cwd(), 'storage/app');
const pages = [
  '/admin/quotations/basement/create?project=' + projectId,
  '/admin/quotations/standard/create?project=' + projectId,
  '/admin/quotations/fence/create?project=' + projectId,
];
(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    protocolTimeout: 30000,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(20000);
  page.setDefaultTimeout(20000);
  async function go(url) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('body');
  }
  async function openQuote(url) {
    await go(url);
    await page.waitForSelector('[data-qh-header]');
  }
  await go(base + '/admin/login');
  if (page.url().includes('/admin/login')) {
    await page.waitForSelector('input[name="email"]');
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
  }
  const widths = [390, 1024, 1280];
  const themes = ['light', 'dark'];
  const out = {};
  for (const theme of themes) {
    for (const w of widths) {
      await page.setViewport({ width: w, height: 900 });
      for (const href of pages) {
        await page.evaluate((mode) => {
          try { localStorage.setItem('zanini-admin-theme', mode === 'dark' ? 'dark' : 'light'); } catch (e) {}
        }, theme);
        await openQuote(base + href);
        await page.evaluate((mode) => {
          document.documentElement.classList.toggle('admin-dark', mode === 'dark');
          if (document.body) document.body.classList.toggle('admin-dark', mode === 'dark');
        }, theme);
        const key = theme + '_' + w + '_' + href.replace(/[^a-z]+/g, '_');
        out[key] = await page.evaluate((width) => {
          const header = document.querySelector('[data-qh-header]');
          const actions = header && header.querySelector('.qh-actions');
          const wizard = header && header.querySelector('.qh-wizard');
          const save = header && header.querySelector('.qh-actions__save');
          const cancel = header && header.querySelector('.qh-actions__cancel');
          const crumb = header && header.querySelector('.qh-crumb');
          const crumbLinks = [...(header ? header.querySelectorAll('.qh-crumb__link') : [])].map((a) => {
            const r = a.getBoundingClientRect();
            return Math.round(r.height);
          });
          const box = header ? header.getBoundingClientRect() : null;
          const buttons = [...(header ? header.querySelectorAll('.qh-actions .admin-btn, .qh-wizard .admin-btn') : [])].map((b) => {
            const r = b.getBoundingClientRect();
            return { h: Math.round(r.height), w: Math.round(r.width), label: (b.textContent || '').trim(), top: Math.round(r.top) };
          });
          const overflow = !!(box && (box.right > width + 1 || box.left < -1));
          const actionTop = actions ? actions.getBoundingClientRect().top : 0;
          const wizardTop = wizard ? wizard.getBoundingClientRect().top : 0;
          const saveStyle = save ? getComputedStyle(save) : null;
          const actionTops = buttons.filter((b) => ['Save quotation', 'Save as draft', 'Cancel'].includes(b.label)).map((b) => b.top);
          return {
            hasHeader: !!header,
            hasCrumb: !!crumb,
            hasSave: !!save,
            cancelIsButton: !!(cancel && cancel.tagName === 'BUTTON'),
            overflow,
            headerW: box ? Math.round(box.width) : 0,
            headerPast: box ? Math.round(box.right - width) : 0,
            buttons,
            shortBtn: buttons.filter((b) => b.h < 44 && b.w > 0).map((b) => b.label),
            shortCrumb: crumbLinks.filter((h) => h < 44),
            actionWrap: actionTops.length > 1 && (Math.max.apply(null, actionTops) - Math.min.apply(null, actionTops) > 12),
            wizardBelow: !wizard || wizardTop >= actionTop - 1,
            savePrimary: !!(save && save.classList.contains('admin-btn--primary')),
            dark: document.documentElement.classList.contains('admin-dark'),
            saveBg: saveStyle ? saveStyle.backgroundColor : '',
          };
        }, w);
      }
    }
  }
  const posts = [];
  page.on('request', (req) => {
    if (['POST', 'PUT', 'PATCH'].includes(req.method())) posts.push(req.url());
  });
  await page.setViewport({ width: 1024, height: 900 });
  await openQuote(base + '/admin/quotations/fence/create');
  posts.length = 0;
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => {}),
    page.click('[data-qh-cancel]'),
  ]);
  out.cancelClean = { posts: posts.slice(), url: page.url() };
  await openQuote(base + '/admin/quotations/fence/create');
  const name = await page.$('[name="client_name"]');
  if (name) await name.type('Dirty Cancel Client');
  posts.length = 0;
  await page.click('[data-qh-cancel]');
  await page.waitForSelector('#admin-dialog:not([hidden]), .admin-dialog-actions', { timeout: 3000 }).catch(() => {});
  const stay = await page.$('[data-admin-dialog-dismiss], .admin-dialog-actions button:last-child');
  if (stay) await stay.click();
  out.cancelDirty = { posts: posts.slice(), url: page.url(), dialog: await page.$eval('#admin-dialog-message, .admin-dialog-message', (el) => el.textContent).catch(() => '') };
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(path.join(outDir, 'qh-header-geometry.json'), JSON.stringify(out, null, 2));
  await browser.close();
  process.stdout.write(JSON.stringify(out));
})().catch((err) => { console.error(err); process.exit(2); });
JS
        );

        $cmd = 'cd '.escapeshellarg(base_path())
            .' && QH_GEO_EMAIL='.escapeshellarg($user->email)
            .' QH_GEO_PASSWORD='.escapeshellarg('password')
            .' QH_GEO_BASE='.escapeshellarg($base)
            .' QH_GEO_PROJECT='.escapeshellarg((string) $project->id)
            .' QH_GEO_OUT='.escapeshellarg(storage_path('app'))
            .' timeout -k 5s 90s node '.escapeshellarg($script);
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        @unlink($script);
        $this->stopLayoutServer();

        $this->assertSame(0, $code, "Header geometry puppeteer failed:\n".implode("\n", $output));
        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json, "Geometry JSON missing:\n".implode("\n", $output));
        $this->assertNotEmpty($json);

        $this->assertSame([], $json['cancelClean']['posts'] ?? ['fail'], 'Clean Cancel must not POST');
        $this->assertSame([], $json['cancelDirty']['posts'] ?? ['fail'], 'Dirty Cancel must not POST');
        $this->assertStringContainsString('/fence/', (string) ($json['cancelDirty']['url'] ?? ''));

        foreach ($json as $key => $row) {
            if (in_array($key, ['cancelClean', 'cancelDirty'], true)) {
                continue;
            }
            $this->assertTrue($row['hasHeader'] ?? false, "Missing header at {$key}");
            $this->assertTrue($row['hasCrumb'] ?? false, "Missing breadcrumb at {$key}");
            $this->assertTrue($row['hasSave'] ?? false, "Missing Save at {$key}");
            $this->assertTrue($row['cancelIsButton'] ?? false, "Cancel must be a button at {$key}");
            $this->assertTrue($row['savePrimary'] ?? false, "Save must stay primary at {$key}");
            $this->assertTrue($row['wizardBelow'] ?? false, "Wizard row must sit below actions at {$key}");
            $this->assertSame([], $row['shortBtn'] ?? ['fail'], "Buttons shorter than 44px at {$key}");
            $this->assertSame([], $row['shortCrumb'] ?? ['fail'], "Crumb links shorter than 44px at {$key}");
            if (str_contains((string) $key, '_390_')) {
                $this->assertFalse($row['actionWrap'] ?? true, "Cancel wrapped onto a new row at {$key}");
            }
            $this->assertFalse($row['overflow'] ?? true, "Header overflows the viewport at {$key}");
            $this->assertLessThanOrEqual(1, (int) ($row['headerPast'] ?? 99), "Header wider than the viewport at {$key}");
            if (str_starts_with((string) $key, 'dark_')) {
                $this->assertTrue($row['dark'] ?? false, "Dark class missing at {$key}");
            }
        }
    }

    private function assertActionOrder(string $html, bool $hasDraft): void
    {
        $this->assertMatchesRegularExpression('/<div class="qh-actions"[^>]*>/s', $html);
        preg_match('/<div class="qh-actions"[^>]*>(.*?)<\/div>/s', $html, $match);
        $this->assertNotEmpty($match[1] ?? null, 'Action row missing');
        $row = $match[1];
        $save = strpos($row, 'qh-actions__save');
        $draft = strpos($row, 'qh-actions__draft');
        $cancel = strpos($row, 'qh-actions__cancel');
        $this->assertNotFalse($save);
        $this->assertNotFalse($cancel);
        $this->assertLessThan($cancel, $save, 'Save quotation must come before Cancel');
        if ($hasDraft) {
            $this->assertNotFalse($draft);
            $this->assertLessThan($draft, $save, 'Save quotation must come before Save as draft');
            $this->assertLessThan($cancel, $draft, 'Save as draft must come before Cancel');
        } else {
            $this->assertFalse($draft);
        }
        $this->assertStringContainsString('Save quotation', $row);
        $this->assertMatchesRegularExpression('/>\s*Cancel\s*</', $row);
    }

    private function typeIndexRoute(string $type): string
    {
        return match ($type) {
            'low-deck' => 'admin.quotations.low-deck.index',
            default => 'admin.quotations.'.$type.'.index',
        };
    }

    private function startLayoutServer(): string
    {
        $port = $this->layoutServerPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/qh-header-router.php');
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
            1 => ['file', sys_get_temp_dir().'/qh-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/qh-serve.err', 'w'],
        ];
        $this->layoutServerProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->layoutServerProcess), 'Could not start php server for header geometry');
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

        $this->stopLayoutServer();
        $this->fail('Header geometry server did not become ready on '.$base);
    }

    private function stopLayoutServer(): void
    {
        $port = (int) $this->layoutServerPort;
        if (is_resource($this->layoutServerProcess)) {
            $status = @proc_get_status($this->layoutServerProcess);
            $pid = (int) ($status['pid'] ?? 0);
            if ($pid > 0) {
                @exec('kill -TERM '.$pid.' 2>/dev/null');
                @exec('pkill -TERM -P '.$pid.' 2>/dev/null');
            }
            @proc_terminate($this->layoutServerProcess, 9);
        }
        @exec('pkill -f '.escapeshellarg('127.0.0.1:'.$port).' 2>/dev/null');
        $this->layoutServerProcess = null;
    }

    protected function tearDown(): void
    {
        $this->stopLayoutServer();
        parent::tearDown();
    }
}
