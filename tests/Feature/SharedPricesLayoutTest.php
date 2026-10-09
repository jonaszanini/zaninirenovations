<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\SharedWork;
use App\Models\User;
use App\Services\SharedWorkCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class SharedPricesLayoutTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    public function test_shared_prices_layout_geometry_and_precision(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@zanini.ca',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $work = SharedWork::query()->where('key', 'window_trim')->first()
            ?? SharedWork::query()->whereNotIn('unit', ['lf', 'sf'])->first();
        $this->assertNotNull($work, 'A non-area shared work must exist');

        $work->rate = 24.8235;
        $work->flat_rate = 99999.9999;
        $work->material_unit = 'opening';
        $work->material_unit_price = 99999.9999;
        $work->material_name = 'Picture-frame window casing kit extra long';
        $work->consumers = [
            'custom_very_long_unknown_consumer_key',
            'trimming',
            'basement',
        ];
        $work->save();

        $wideSell = SharedWork::query()->where('key', 'window_stool_apron')->first()
            ?? SharedWork::query()->where('id', '!=', $work->id)->whereNotIn('unit', ['lf', 'sf'])->first();
        if ($wideSell) {
            $wideSell->rate = 12.9000;
            $wideSell->flat_rate = 1234.5678;
            $wideSell->save();
        }
        SharedWorkCatalog::forgetCache();

        Material::query()->create([
            'name' => '8 ft shoe moulding',
            'store_sku' => 'SHOE-8',
            'price_cad' => 12.99,
            'is_active' => true,
            'unit' => 'each',
        ]);
        Material::query()->create([
            'name' => 'MDF casing 8 ft',
            'store_sku' => 'CASE-8',
            'price_cad' => 18.50,
            'is_active' => true,
            'unit' => 'each',
        ]);

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('Shared prices', false)
            ->assertSee('class="sp-col-catalog"', false)
            ->assertSee('class="sp-col-consumers"', false)
            ->assertSee('class="sp-effective"', false)
            ->assertSee('class="sp-th-sell"', false)
            ->assertSee('class="sp-sell-phone-label"', false)
            ->assertSee('cp-money-input', false)
            ->getContent();

        $this->assertStringContainsString('24.8235', $html);
        $this->assertStringContainsString('12.9000', $html);
        $this->assertStringContainsString('99999.9999', $html);
        $this->assertStringContainsString('opening', $html);
        $this->assertStringContainsString('custom_very_long_unknown_consumer_key', $html);
        $this->assertStringContainsString('title="custom_very_long_unknown_consumer_key"', $html);
        $this->assertStringContainsString('data-cp-money-places="4"', $html);
        $this->assertStringContainsString('Used when Sell rate is empty', $html);
        $this->assertStringContainsString('crew rates', $html);
        $this->assertStringContainsString('effective sell', $html);
        $this->assertMatchesRegularExpression('/aria-label="[^"]*crew rates[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/aria-label="[^"]*effective sell[^"]*"/', $html);
        $this->assertStringContainsString('aria-controls="sp-more-'.$work->id.'"', $html);
        $this->assertStringContainsString('aria-describedby="sp-more-'.$work->id.'"', $html);
        $this->assertStringContainsString('aria-label="+2 more:', $html);

        $blade = file_get_contents(resource_path('views/admin/quotations/shared_prices/edit.blade.php'));
        $this->assertTrue(
            str_contains($blade, "'tipText'") || str_contains($blade, '<x-admin.info-tip'),
            'Sell / crew tips must use tipText or x-admin.info-tip'
        );
        $this->assertTrue(
            str_contains($blade, "'tipLabel'") || str_contains($blade, '<x-admin.info-tip'),
            'Sell / crew tips must use tipLabel or x-admin.info-tip'
        );
        $this->assertStringNotContainsString("'html' =>", $blade);
        $this->assertStringContainsString('sp-th-sell', $blade);
        $this->assertStringContainsString('sp-sell-phone-label', $blade);
        $this->assertStringNotContainsString('sp-col-trailing', $html);
        $this->assertStringNotContainsString('class="admin-form-help"', $html);

        $css = $this->pageCss($html);
        $catalog = $this->cssBlock($css, '.sp-table td.sp-col-catalog');
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $catalog);
        $this->assertDoesNotMatchRegularExpression('/align-items:\s*flex-start/', $catalog);
        $this->assertMatchesRegularExpression('/justify-content:\s*center/', $catalog);

        $consumers = $this->cssBlock($css, '.sp-table td.sp-col-consumers');
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $consumers);
        $this->assertDoesNotMatchRegularExpression('/align-items:\s*flex-start/', $consumers);

        $chip = $this->cssBlock($css, '.sp-chip');
        $this->assertMatchesRegularExpression('/display:\s*inline-flex/', $chip);
        $this->assertMatchesRegularExpression('/align-items:\s*center/', $chip);
        $this->assertMatchesRegularExpression('/justify-content:\s*flex-start/', $chip);
        $this->assertDoesNotMatchRegularExpression('/justify-content:\s*center/', $chip);
        $this->assertGreaterThanOrEqual(0.65, $this->cssFontRem($chip));

        $chipLabel = $this->cssBlock($css, '.sp-chip__label');
        $this->assertMatchesRegularExpression('/text-overflow:\s*ellipsis/', $chipLabel);
        $this->assertStringContainsString('sp-chip__label', $html);

        $more = $this->cssBlock($css, '.sp-chip--more');
        $this->assertDoesNotMatchRegularExpression('/\.sp-chip--more:focus\s+\.sp-chip-more__tip/', $css);
        $this->assertMatchesRegularExpression('/min-width:\s*32px/', $more);
        $this->assertMatchesRegularExpression('/justify-content:\s*center/', $more);

        $columns = $this->desktopGridColumns($css);
        $this->assertCount(8, $columns);
        $this->assertGreaterThanOrEqual(7.5, $this->cssMinRem($columns[0]), 'Work item must show ~18–20 characters at 1024');
        $this->assertGreaterThanOrEqual(4.5, $this->cssMinRem($columns[1]), 'Rate must still fit 24.8235');
        $this->assertGreaterThanOrEqual(6.6, $this->cssMinRem($columns[2]), 'Sell must fit 99,999.9999 without a per-row ⓘ');
        $this->assertTrue(
            str_contains($columns[3], '7ch') || $this->cssMinRem($columns[3]) >= 4.6,
            'Unit must be ~7ch so opening does not clip'
        );
        $this->assertGreaterThanOrEqual(5.5, $this->cssMinRem($columns[4]), 'Mat. price must fit 99,999.9999');
        $this->assertGreaterThanOrEqual(6.5, $this->cssMinRem($columns[5]), 'Mat. name keeps a readable min');
        $this->assertLessThanOrEqual(8.25, $this->cssMinRem($columns[5]));
        $this->assertGreaterThanOrEqual(7.0, $this->cssMinRem($columns[7]), 'Used-by must fit Trimming + +N without clipping');

        $this->assertStringContainsString('title="{{ $work->label }}"', $blade);
        $this->assertStringContainsString('grid-area: search', $css);
        $this->assertStringContainsString('@media (min-width: 1024px)', $css);
        $this->assertStringContainsString('@media (max-width: 1023px)', $css);
        $this->assertDoesNotMatchRegularExpression('/\.sp-col-sell:has\(\.admin-info-tip\)\s+\.sp-input\s*\{[^}]*padding-right:\s*1\.85rem/', $css);
        $this->assertMatchesRegularExpression('/\.sp-table\s+\.sp-work\s*\{[^}]*padding:\s*0/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.sp-group \.admin-table-scroll\s*\{[^}]*overflow-x:\s*hidden/', $css);
        $this->assertMatchesRegularExpression('/overflow:\s*(visible|clip)/', $css);
        $this->assertStringContainsString('.sp-effective', $css);
        $this->assertStringContainsString('updateSpEffective', $blade);
        $this->assertStringContainsString("document.addEventListener('keydown'", $blade);

        $this->assertBrowserGeometry($user->email);
    }

    private function pageCss(string $html): string
    {
        $this->assertMatchesRegularExpression('/<style>(.*?)<\/style>/s', $html);
        preg_match('/<style>(.*?)<\/style>/s', $html, $match);

        return $match[1];
    }

    private function cssBlock(string $css, string $selector): string
    {
        $quoted = preg_quote($selector, '/');
        $pattern = '/(?:^|[\s,])'.$quoted.'\s*\{([^}]+)\}/';
        $this->assertMatchesRegularExpression($pattern, $css, "Missing CSS rule {$selector}");
        preg_match($pattern, $css, $match);

        return $match[1];
    }

    /**
     * @return list<string>
     */
    private function desktopGridColumns(string $css): array
    {
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\)\s*\{.*?grid-template-columns:\s*(.*?);/s', $css);
        preg_match('/@media \(min-width: 1024px\)\s*\{.*?grid-template-columns:\s*(.*?);/s', $css, $match);
        $raw = trim(preg_replace('/\s+/', ' ', $match[1]));
        preg_match_all('/minmax\([^)]+\)|[^\s]+/', $raw, $parts);

        return array_values($parts[0]);
    }

    private function cssMinRem(string $token): float
    {
        if (preg_match('/minmax\(\s*([0-9.]+)rem/', $token, $match)) {
            return (float) $match[1];
        }
        if (preg_match('/^([0-9.]+)rem$/', $token, $match)) {
            return (float) $match[1];
        }

        $this->fail("Could not parse rem min from {$token}");
    }

    private function cssFontRem(string $block): float
    {
        $this->assertMatchesRegularExpression('/font-size:\s*([0-9.]+)rem/', $block);
        preg_match('/font-size:\s*([0-9.]+)rem/', $block, $match);

        return (float) $match[1];
    }

    private function assertBrowserGeometry(string $email): void
    {
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed — geometry cannot silently skip');

        $base = $this->startLayoutServer();
        $script = storage_path('app/sp-layout-geometry.js');
        file_put_contents($script, <<<'JS'
const puppeteer = require('puppeteer');
const email = process.env.SP_GEO_EMAIL;
const password = process.env.SP_GEO_PASSWORD;
const base = process.env.SP_GEO_BASE;
(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  async function login() {
    await page.goto(base + '/admin/login', { waitUntil: 'networkidle0' });
    if (page.url().includes('/admin/login')) {
      await page.type('input[name="email"]', email);
      await page.type('input[name="password"]', password);
      await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
    }
  }
  async function openPage() {
    await page.goto(base + '/admin/quotations/shared-prices', { waitUntil: 'networkidle0' });
    await page.evaluate(() => document.querySelectorAll('.sp-group').forEach((g) => { g.open = true; }));
  }
  await login();
  const widths = [320, 390, 768, 901, 960, 1024, 1280];
  const out = {};
  for (const w of widths) {
    await page.setViewport({ width: w, height: 900 });
    await openPage();
    out[String(w)] = await page.evaluate((width) => {
      const card = document.querySelector('details.sp-group.admin-card');
      const table = document.querySelector('.sp-table');
      const thead = document.querySelector('.sp-table thead');
      const row = document.querySelector('.sp-table tbody tr');
      const rowStyle = row ? getComputedStyle(row) : null;
      const stacked = !rowStyle || rowStyle.gridTemplateColumns.split(' ').filter(Boolean).length <= 2
        || (thead && getComputedStyle(thead).display === 'none');
      const chips = [...document.querySelectorAll('.sp-chip:not(.sp-chip--more)')];
      const clippedShort = chips.filter((c) => {
        const label = c.querySelector('.sp-chip__label') || c;
        const text = (label.textContent || '').trim();
        if (text.length > 12 || width < 390) return false;
        return label.scrollWidth > label.clientWidth + 2;
      }).map((c) => (c.textContent || '').trim());
      const longChip = chips.map((c) => {
        const label = c.querySelector('.sp-chip__label') || c;
        return { text: (label.textContent || '').trim(), title: c.getAttribute('title') || '', scroll: label.scrollWidth, client: label.clientWidth };
      }).find((c) => c.text.length > 12);
      const offCenter = chips.filter((c) => {
        const label = c.querySelector('.sp-chip__label') || c;
        const text = (label.textContent || '').trim();
        if (text.length > 12) return false;
        const cBox = c.getBoundingClientRect();
        const lBox = label.getBoundingClientRect();
        return Math.abs((cBox.top + cBox.height / 2) - (lBox.top + lBox.height / 2)) > 1;
      }).map((c) => (c.textContent || '').trim());
      const more = document.querySelector('.sp-chip--more');
      const moreBox = more ? more.getBoundingClientRect() : null;
      const sellInputs = [...document.querySelectorAll('.sp-col-sell input')].map((el) => ({
        value: el.value,
        scroll: el.scrollWidth,
        client: el.clientWidth,
        clipped: el.scrollWidth > el.clientWidth + 1,
      }));
      const work = document.querySelector('.sp-col-label');
      const name = document.querySelector('.sp-col-name');
      const chipStyle = chips[0] ? getComputedStyle(chips[0]) : null;
      return {
        docW: document.documentElement.scrollWidth,
        viewW: window.innerWidth,
        cardW: card ? Math.round(card.getBoundingClientRect().width) : 0,
        tableW: table ? Math.round(table.getBoundingClientRect().width) : 0,
        hasTable: !!table,
        url: location.pathname,
        overflowEls: [...document.querySelectorAll('.admin-content, .admin-content *')].filter((el) => {
          const r = el.getBoundingClientRect();
          const s = getComputedStyle(el);
          if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
          if (r.height < 2 || r.width < 2) return false;
          return r.right > window.innerWidth + 2;
        }).slice(0, 8).map((el) => (el.className && String(el.className).slice(0, 48)) || el.tagName),
        stacked,
        colCount: rowStyle ? rowStyle.gridTemplateColumns.split(' ').filter(Boolean).length : 0,
        clippedShort,
        offCenter,
        chipJustify: chipStyle ? chipStyle.justifyContent : '',
        moreVisible: !!(moreBox && moreBox.width > 8 && moreBox.right <= window.innerWidth + 1 && moreBox.bottom > 0),
        moreHit: more ? Math.max(
          moreBox ? Math.round(Math.min(moreBox.width, moreBox.height)) : 0,
          parseInt(getComputedStyle(more, '::after').width, 10) || 0
        ) : 0,
        sellClipped: sellInputs.filter((s) => /99999\.9999|1234\.5678/.test(s.value) && s.clipped),
        sellMin: sellInputs.length ? Math.min(...sellInputs.map((s) => s.client)) : 0,
        workW: work ? Math.round(work.getBoundingClientRect().width) : 0,
        nameW: name ? Math.round(name.getBoundingClientRect().width) : 0,
        longChipOk: !longChip || (longChip.title === longChip.text && longChip.scroll >= longChip.client),
        zLow: [...document.querySelectorAll('.sp-table tbody tr')].some((tr) => {
          const z = parseInt(getComputedStyle(tr).zIndex, 10);
          return tr.querySelector('.sp-chip--more') && z === 1;
        }),
      };
    }, w);

    if (w <= 414) {
      await page.evaluate(() => {
        const more = document.querySelector('.sp-chip--more');
        if (more) more.click();
      });
      await new Promise((r) => setTimeout(r, 150));
      out[String(w)].tip = await page.evaluate(() => {
        const tip = document.querySelector('.sp-chip--more[aria-expanded="true"] .sp-chip-more__tip');
        if (!tip) return null;
        const r = tip.getBoundingClientRect();
        return { left: Math.round(r.left), right: Math.round(r.right), off: r.left < -1 || r.right > window.innerWidth + 1 };
      });
      await page.keyboard.press('Escape');
      out[String(w)].escClosed = await page.evaluate(() => !document.querySelector('.sp-chip--more[aria-expanded="true"]'));
    }

    if (w === 320 || w === 390) {
      await page.setViewport({ width: w, height: 844 });
      await openPage();
      await page.evaluate(() => {
        const row = document.querySelector('.sp-table tbody tr');
        const btn = row && row.querySelector('.sp-catalog-btn');
        if (btn) btn.click();
      });
      await new Promise((r) => setTimeout(r, 150));
      const input = await page.$('.sp-search-wrap:focus-within .admin-search-select__input, .sp-table tbody tr .admin-search-select__input');
      if (input) {
        await input.click({ clickCount: 3 });
        await input.type('a', { delay: 20 });
      }
      await new Promise((r) => setTimeout(r, 300));
      out[String(w)].search = await page.evaluate(() => {
        const list = document.querySelector('.admin-search-select__list:not([hidden])');
        if (!list) return { present: false };
        const r = list.getBoundingClientRect();
        return {
          present: true,
          left: Math.round(r.left),
          right: Math.round(r.right),
          off: r.left < -1 || r.right > window.innerWidth + 1,
        };
      });
    }
  }

  await browser.close();
  process.stdout.write(JSON.stringify(out));
})().catch((err) => { console.error(err); process.exit(2); });
JS
        );

        $cmd = 'cd '.escapeshellarg(base_path())
            .' && SP_GEO_EMAIL='.escapeshellarg($email)
            .' SP_GEO_PASSWORD='.escapeshellarg('password')
            .' SP_GEO_BASE='.escapeshellarg($base)
            .' node '.escapeshellarg($script);
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        @unlink($script);
        $this->stopLayoutServer();

        $this->assertSame(0, $code, "Geometry puppeteer failed:\n".implode("\n", $output));
        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json, "Geometry JSON missing:\n".implode("\n", $output));

        foreach ([320, 390, 768, 901, 960, 1024, 1280] as $w) {
            $row = $json[(string) $w] ?? null;
            $this->assertIsArray($row, "Missing geometry for {$w}");
            $this->assertTrue($row['hasTable'] ?? false, "Shared prices table missing at {$w} ({$row['url']})");
            $this->assertSame(
                [],
                $row['overflowEls'] ?? ['overflow'],
                "Content overflows the viewport at {$w}: ".json_encode($row['overflowEls'] ?? [])
            );
            $this->assertSame([], $row['clippedShort'] ?? ['fail'], "Short chips clipped at {$w}");
            $this->assertSame([], $row['offCenter'] ?? ['fail'], "Chip labels not vertically centred at {$w}");
            $this->assertSame('flex-start', $row['chipJustify'] ?? '', "Chip justify-content at {$w}");
            $this->assertTrue($row['moreVisible'] ?? false, "+N must stay on-screen at {$w}");
            $this->assertSame([], $row['sellClipped'] ?? ['fail'], "Sell input clipped at {$w}");
            $this->assertTrue($row['longChipOk'] ?? false, "Long consumer chip must ellipsize with a title at {$w}");
            $this->assertFalse($row['zLow'] ?? true, "Open +N row must not stay at z-index 1 at {$w}");

            if ($w <= 1023) {
                $this->assertTrue($row['stacked'] ?? false, "Must use stacked cards at {$w}");
            } else {
                $this->assertFalse($row['stacked'] ?? true, "Must use the 8-column grid at {$w}");
                $this->assertGreaterThanOrEqual(115, (int) ($row['workW'] ?? 0), "Work item too narrow at {$w}");
                $this->assertGreaterThanOrEqual(100, (int) ($row['sellMin'] ?? 0), "Sell column too narrow at {$w}");
                $this->assertGreaterThanOrEqual(44, (int) ($row['moreHit'] ?? 0), "+N hit area must be ≥44px at {$w}");
            }
        }

        foreach (['320', '390'] as $w) {
            $this->assertFalse($json[$w]['tip']['off'] ?? true, "+N tooltip must stay in the viewport at {$w}");
            $this->assertTrue($json[$w]['escClosed'] ?? false, 'Escape must close +N from document level');
            $this->assertFalse($json[$w]['search']['off'] ?? true, "Catalog list must stay on-screen at {$w}");
        }
    }

    private $layoutServerProcess = null;

    private function startLayoutServer(): string
    {
        $port = 8011;
        $base = 'http://127.0.0.1:'.$port;
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan')).' serve --host=127.0.0.1 --port='.$port;
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
            1 => ['file', sys_get_temp_dir().'/sp-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/sp-serve.err', 'w'],
        ];
        $this->layoutServerProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->layoutServerProcess), 'Could not start php artisan serve for geometry');
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
        $this->fail('Layout server did not become ready on '.$base);
    }

    private function stopLayoutServer(): void
    {
        if (is_resource($this->layoutServerProcess)) {
            proc_terminate($this->layoutServerProcess);
            proc_close($this->layoutServerProcess);
            $this->layoutServerProcess = null;
        }
    }

    protected function tearDown(): void
    {
        $this->stopLayoutServer();
        parent::tearDown();
    }
}
