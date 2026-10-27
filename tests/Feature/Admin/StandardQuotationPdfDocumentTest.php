<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class StandardQuotationPdfDocumentTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $layoutServerProcess = null;

    private int $layoutServerPort = 8024;

    public function test_new_standard_quotation_create_has_no_pdf_defaults_selected(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->assertSee('id="sq-pdf-document-section"', false)
            ->assertSee('id="sq-pdf-setup-banner"', false)
            ->assertSee('sq-pdf-field-error', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/name="document_type"[^>]*checked/',
            $html,
            'Document type radios must start unselected on create'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="pdf_detail_mode"[^>]*checked/',
            $html,
            'PDF line item radios must start unselected on create'
        );
        $this->assertStringContainsString('name="includes_material" value="1"', $html);
        $this->assertStringNotContainsString(
            'name="includes_material" value="1" checked',
            preg_replace('/\s+/', ' ', $html) ?: $html
        );
    }

    public function test_store_without_pdf_document_fields_fails_with_visible_field_errors(): void
    {
        $this->actingAsAdmin();

        $payload = $this->minimalStorePayload([
            'includes_material' => '0',
            'includes_labour' => '0',
        ]);
        unset($payload['document_type'], $payload['pdf_detail_mode']);

        $this->from(route('admin.quotations.standard.create'))
            ->post(route('admin.quotations.standard.store'), $payload)
            ->assertRedirect(route('admin.quotations.standard.create'))
            ->assertSessionHasErrors(['document_type', 'pdf_detail_mode']);

        $errors = session('errors');
        $this->assertSame(
            'Pick whether this is a quotation or an estimate.',
            $errors->first('document_type')
        );
        $this->assertSame(
            'Choose how material and labour appear on the client PDF.',
            $errors->first('pdf_detail_mode')
        );

        $this->from(route('admin.quotations.standard.create'))
            ->post(route('admin.quotations.standard.store'), $this->minimalStorePayload([
                'includes_material' => '0',
                'includes_labour' => '0',
            ]))
            ->assertRedirect(route('admin.quotations.standard.create'))
            ->assertSessionHasErrors(['includes_material']);

        $this->assertSame(
            'Select Material and/or Labour for the PDF intro.',
            session('errors')->first('includes_material')
        );
    }

    public function test_store_with_pdf_document_fields_succeeds(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.standard.create'))
            ->post(route('admin.quotations.standard.store'), $this->minimalStorePayload())
            ->assertRedirect();

        $quote = StandardQuotation::query()->where('client_name', 'PDF Doc Client')->first();
        $this->assertNotNull($quote);
        $this->assertSame(ClientProjectQuotation::TYPE_QUOTATION, $quote->document_type);
        $this->assertSame(StandardQuotation::PDF_DETAIL_SUMMARY, $quote->pdf_detail_mode);
        $this->assertTrue((bool) $quote->includes_material);
        $this->assertTrue((bool) $quote->includes_labour);
    }

    public function test_edit_preserves_saved_pdf_document_values(): void
    {
        $this->actingAsAdmin();

        $quote = StandardQuotation::query()->create([
            'quote_number' => 'SQ-PDF-'.random_int(1000, 9999),
            'client_name' => 'Existing PDF Client',
            'display_title' => 'Deck railing',
            'document_type' => ClientProjectQuotation::TYPE_ESTIMATE,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
            'includes_material' => false,
            'includes_labour' => true,
            'materials' => [],
            'works' => [[
                'title' => 'Job',
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 100,
            ]],
        ]);

        $html = $this->get(route('admin.quotations.standard.edit', $quote))
            ->assertOk()
            ->assertDontSee('id="sq-pdf-setup-banner"', false)
            ->getContent();

        $this->assertStringContainsString(
            'value="'.ClientProjectQuotation::TYPE_ESTIMATE.'" checked',
            preg_replace('/\s+/', ' ', $html) ?: $html
        );
        $this->assertStringContainsString(
            'value="'.StandardQuotation::PDF_DETAIL_DETAILED.'" checked',
            preg_replace('/\s+/', ' ', $html) ?: $html
        );
        $this->assertStringContainsString('name="includes_labour" value="1" checked', preg_replace('/\s+/', ' ', $html) ?: $html);
    }

    public function test_client_js_declares_pdf_document_validation(): void
    {
        $js = (string) file_get_contents(public_path('js/admin-standard-quotation.js'));
        $this->assertStringContainsString('function validatePdfDocumentOptions', $js);
        $this->assertStringContainsString('sq-error-document_type', $js);
        $this->assertStringContainsString('data-qh-view-pdf', $js);
        $this->assertStringContainsString('Select Material and/or Labour for the PDF intro.', $js);
    }

    public function test_pdf_document_section_layout_at_390px(): void
    {
        User::factory()->create([
            'email' => 'sq-pdf-390@zanini.ca',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');

        $base = $this->startLayoutServer();
        $script = storage_path('app/sq-pdf-doc-390.js');
        file_put_contents($script, <<<'JS'
const puppeteer = require('puppeteer');
const base = process.env.SQ_PDF_BASE;
const email = process.env.SQ_PDF_EMAIL;
const password = process.env.SQ_PDF_PASSWORD;
(async () => {
  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 390, height: 900 });
  await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
  if (page.url().includes('/admin/login')) {
    await page.type('input[name="email"]', email);
    await page.type('input[name="password"]', password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
  }
  await page.goto(base + '/admin/quotations/standard/create', { waitUntil: 'domcontentloaded' });
  if (page.url().includes('/admin/login')) {
    console.error('still on login', page.url());
    process.exit(1);
  }
  const html = await page.content();
  if (!html.includes('sq-pdf-document-section')) {
    console.error('missing section', page.url(), html.slice(0, 500));
    process.exit(1);
  }
  await page.waitForSelector('#sq-pdf-document-section', { timeout: 5000 });
  const metrics = await page.evaluate(() => {
    const section = document.getElementById('sq-pdf-document-section');
    const banner = document.getElementById('sq-pdf-setup-banner');
    const row = document.querySelector('.sq-intro-choice-row--pdf-detail');
    const box = section ? section.getBoundingClientRect() : null;
    const rowBox = row ? row.getBoundingClientRect() : null;
    const labels = [...(section ? section.querySelectorAll('.cp-checkbox-row') : [])].map((el) => {
      const r = el.getBoundingClientRect();
      return { h: Math.round(r.height), w: Math.round(r.width) };
    });
    return {
      sectionW: box ? Math.round(box.width) : 0,
      sectionPast: box ? Math.round(box.right - 390) : 0,
      rowH: rowBox ? Math.round(rowBox.height) : 0,
      hasBanner: !!banner && !banner.hidden,
      shortTouch: labels.filter((l) => l.h > 0 && l.h < 44).length,
      overflow: box ? box.right > 391 : true,
    };
  });
  console.log(JSON.stringify(metrics));
  await browser.close();
  if (!metrics.hasBanner) { console.error('warning banner missing'); process.exit(1); }
  if (metrics.overflow || metrics.sectionPast > 2) { console.error('section overflow', metrics); process.exit(1); }
  if (metrics.shortTouch > 0) { console.error('touch targets too short', metrics); process.exit(1); }
})();
JS
        );

        $env = [
            'SQ_PDF_BASE' => $base,
            'SQ_PDF_EMAIL' => 'sq-pdf-390@zanini.ca',
            'SQ_PDF_PASSWORD' => 'password',
        ];
        $cmd = 'cd '.escapeshellarg(base_path()).' && ';
        foreach ($env as $k => $v) {
            $cmd .= $k.'='.escapeshellarg((string) $v).' ';
        }
        $cmd .= 'node '.escapeshellarg($script).' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        @unlink($script);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalStorePayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'draft',
            'client_name' => 'PDF Doc Client',
            'display_title' => 'Kitchen work',
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'includes_material' => '1',
            'includes_labour' => '1',
            'payment_installments' => 1,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'works' => [[
                'title' => 'Job',
                'labour_use_fixed' => '1',
                'labour_fixed_amount' => '100',
            ]],
        ], $overrides);
    }

    private function startLayoutServer(): string
    {
        $port = $this->layoutServerPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/sq-pdf-doc-router.php');
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
            1 => ['file', sys_get_temp_dir().'/sq-pdf-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/sq-pdf-serve.err', 'w'],
        ];
        $this->layoutServerProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->layoutServerProcess), 'Could not start php server for PDF section geometry');
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $deadline = time() + 15;
        while (time() < $deadline) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp) {
                fclose($fp);

                return $base;
            }
            usleep(100000);
        }

        $this->fail('PHP layout server did not become ready');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->layoutServerProcess)) {
            proc_terminate($this->layoutServerProcess);
            proc_close($this->layoutServerProcess);
            $this->layoutServerProcess = null;
        }

        parent::tearDown();
    }
}
