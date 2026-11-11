<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\FlooringSettings;
use App\Models\FlooringType;
use App\Models\PavingQuotation;
use App\Models\User;
use App\Support\CadMoney;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class AdminMoneyDirectEntryTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    /** @var resource|null */
    private $layoutServerProcess = null;

    private $layoutServerPort = 8013;

    public function test_source_leaves_no_right_fill_price_inputs(): void
    {
        $missing = [];
        $roots = [resource_path('views/admin'), public_path('js')];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (! str_ends_with($path, '.blade.php') && ! str_ends_with($path, '.js')) {
                    continue;
                }
                $text = file_get_contents($path);
                if ($text === false || ! str_contains($text, 'cp-money-input')) {
                    continue;
                }
                foreach ($this->moneyInputTags($text) as $tag) {
                    if (! str_contains($tag, 'data-cp-money-entry="direct"')) {
                        $missing[] = str_replace(base_path().'/', '', $path).': '.substr(trim(preg_replace('/\s+/', ' ', $tag)), 0, 160);
                    }
                }
            }
        }
        $this->assertSame([], $missing, "Money inputs still missing data-cp-money-entry=\"direct\":\n".implode("\n", $missing));
        $this->assertSame(4.75, CadMoney::parse('4.75'));
        $this->assertNotEquals(4.75 / 100, CadMoney::parse('4.75'));
        $this->assertSame(4.75, CadMoney::parse('4,75'));
        $this->assertNull(CadMoney::parse('1.234,56'));
    }

    public function test_money_mask_js_default_is_direct_and_has_no_cents_mode(): void
    {
        $js = file_get_contents(public_path('js/admin-client-projects.js'));
        $this->assertIsString($js);
        $this->assertDoesNotMatchRegularExpression('/function\s+wireCentsMoneyEntry\b/', $js);
        $this->assertStringNotContainsString('parseHybridCadMoney', $js);
        $this->assertStringNotContainsString('data-cp-money-hybrid', $js);
        $this->assertDoesNotMatchRegularExpression("/data-cp-money-entry['\"]\\s*===?\\s*['\"]cents['\"]/", $js);
        $this->assertMatchesRegularExpression(
            '/function\s+wireMoneyInput\s*\(\s*input\s*\)\s*\{\s*if\s*\(\s*!input\s*\)\s*return;\s*wireDirectMoneyEntry\s*\(\s*input\s*\)/',
            $js
        );
        $this->assertDoesNotMatchRegularExpression(
            '/function\s+wireMoneyInput\s*\([^)]*\)\s*\{[^}]*cents/s',
            $js
        );

        $invoiceEdit = file_get_contents(resource_path('views/admin/client_projects/invoice/edit.blade.php'));
        $this->assertIsString($invoiceEdit);
        $this->assertStringNotContainsString('parseHybridCadMoney', $invoiceEdit);
        $this->assertMatchesRegularExpression('/amtRaw\s*=\s*amtInput\s*\?\s*parseAmount\(\s*amtInput\s*\)/', $invoiceEdit);
        $this->assertStringContainsString('data-cp-money-allow-negative', $invoiceEdit);
        $this->assertStringContainsString('$allowNegativeAmount', $invoiceEdit);
        $this->assertStringContainsString('syncRowMoneySign', $invoiceEdit);
        $this->assertStringContainsString('cpMoneyMask.refresh', $invoiceEdit);
        $this->assertStringContainsString('refresh: function', $js);
        $this->assertStringContainsString('moneyAllowsNegative', $js);
        $this->assertStringContainsString('function roundToPlaces', $js);
        $calculator = file_get_contents(app_path('Services/Invoice/InvoiceTotalsCalculator.php'));
        $this->assertIsString($calculator);
        $this->assertStringContainsString('Materials and gas amounts cannot be negative.', $calculator);
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ClientProjectInvoiceController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString('Invoice total cannot be negative.', $controller);
        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertDoesNotMatchRegularExpression('/total\s*<\s*0\.01/', $controller);
        $this->assertDoesNotMatchRegularExpression(
            '/LINE_KIND_GAS\)\s*&&\s*\$amount\s*<\s*0\)\s*\{\s*continue;/',
            $calculator
        );
        $this->assertDoesNotMatchRegularExpression(
            '/parsed\.negative[\s\S]{0,160}input\.value\s*=\s*[\'"][\'"]/',
            $js
        );
        $this->assertStringNotContainsString("var cur = input.value.replace(/,/g, '');", $js);
        $this->assertStringContainsString("if (remaining.indexOf('.') !== -1)", $js);
        $this->assertDoesNotMatchRegularExpression(
            "/places > 0 && \(ev\.key === '\.' \|\| ev\.key === ','\)/",
            $js
        );
        $harness = file_get_contents(base_path('tests/Feature/Admin/money-direct-entry.js'));
        $this->assertIsString($harness);
        $this->assertStringContainsString("keyboard.type('1.234,56'", $harness);
        $this->assertStringContainsString("keyboard.type('1,234,567.89'", $harness);
        $this->assertStringContainsString("keyboard.type('12,345,678'", $harness);
        $this->assertStringContainsString("keyboard.type('1,2,3'", $harness);
        $this->assertDoesNotMatchRegularExpression(
            "/cp-kp-mixed[\s\S]{0,240}input\.value\s*=\s*['\"]1\.234,56['\"]/",
            $harness
        );

        $paving = file_get_contents(resource_path('views/admin/quotations/paving/create.blade.php'));
        $this->assertIsString($paving);
        $this->assertDoesNotMatchRegularExpression(
            '/function\s+runPreview\s*\(\s*\)\s*\{\s*normalizeLabourRateFields\s*\(/',
            $paving
        );
        $this->assertStringNotContainsString('normalizeLabourRateField', $paving);
        $this->assertStringNotContainsString('labourRateFieldIsFocused', $paving);
        $this->assertDoesNotMatchRegularExpression('/\.replace\(\s*\/,\/g/', $paving);

        $fence = file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));
        $this->assertIsString($fence);
        $this->assertStringContainsString('document.activeElement !== gatesInput', $fence);
        $this->assertStringContainsString('lastClearedGateTicks', $fence);
        $this->assertStringContainsString('clearedGateTickIds', $fence);
        $this->assertStringNotContainsString('clearedGateTickIndexes', $fence);
        $this->assertStringContainsString('data-run-id', $fence);
        $this->assertStringContainsString('function ensureSideRunIds', $fence);
        $this->assertStringContainsString('a missing first-run id cannot collide', $fence);
        $this->assertStringContainsString('Gate goes here', $fence);

        $quoteCreates = glob(resource_path('views/admin/quotations/*/create.blade.php')) ?: [];
        foreach ($quoteCreates as $path) {
            if (basename(dirname($path)) === 'paving') {
                continue;
            }
            $text = file_get_contents($path);
            $this->assertIsString($text);
            $this->assertStringNotContainsString(
                'normalizeLabourRate',
                $text,
                $path.' must not rewrite labour rates during preview'
            );
        }

        $this->assertStringNotContainsString('PREVIEW_SETTLE_MS', $harness);
        $this->assertStringContainsString('PREVIEW_FIRE_MS', $harness);
        $this->assertStringNotContainsString('fenceGateKeypress', $harness);
        $this->assertStringContainsString("halfUp: probe('1.005')", $harness);
        $this->assertStringContainsString("negative: probe('-5')", $harness);
    }

    public function test_flooring_add_type_saves_4_75_and_keeps_existing(): void
    {
        $this->actingAs(User::factory()->create([
            'email' => 'money-http@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]));

        $settings = FlooringSettings::current();
        $types = FlooringType::query()->orderBy('id')->get();
        $this->assertNotEmpty($types);
        $before = $types->mapWithKeys(fn (FlooringType $t) => [$t->id => (float) $t->installation_price_per_sqft])->all();

        $payload = [
            'remove_carpet_dispose_rate' => number_format($settings->removeCarpetRate(), 4, '.', ''),
            'rescrew_subfloor_rate' => number_format((float) $settings->rescrew_subfloor_rate, 2, '.', ''),
            'install_shoe_moulding_rate' => number_format($settings->shoeInstallRate(), 4, '.', ''),
            'caulk_paint_shoe_moulding_rate' => number_format($settings->shoeCaulkPaintRate(), 4, '.', ''),
            'closet_door_reinstall_rate' => number_format((float) $settings->closet_door_reinstall_rate, 2, '.', ''),
            'flooring_types' => [],
            'new_flooring_types' => [
                ['name' => 'Direct entry oak', 'installation_price_per_sqft' => '4.75'],
            ],
        ];
        foreach ($types as $i => $type) {
            $payload['flooring_types'][$i] = [
                'id' => $type->id,
                'name' => $type->name,
                'installation_price_per_sqft' => number_format((float) $type->installation_price_per_sqft, 4, '.', ''),
                'sort_order' => $type->sort_order,
                'is_active' => $type->is_active ? '1' : '0',
            ];
        }

        $this->post(route('admin.quotations.flooring.database.update'), $payload)
            ->assertRedirect(route('admin.quotations.flooring.price-configuration'));

        $created = FlooringType::query()->where('name', 'Direct entry oak')->first();
        $this->assertNotNull($created);
        $this->assertEqualsWithDelta(4.75, (float) $created->installation_price_per_sqft, 0.0001);
        $this->assertNotEquals(4.75 / 100, (float) $created->installation_price_per_sqft);

        foreach ($before as $id => $price) {
            $this->assertEqualsWithDelta(
                $price,
                (float) FlooringType::query()->whereKey($id)->value('installation_price_per_sqft'),
                0.0001,
                'existing flooring type '.$id.' must stay '.$price
            );
        }
    }

    public function test_browser_types_4_75_into_every_swept_money_input(): void
    {
        $user = User::factory()->create([
            'email' => 'money-direct@zanini.ca',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
        $this->actingAs($user);
        ClientProject::factory()->create(['name' => 'Money sweep project']);
        $invoiceUrl = $this->makeInstallmentInvoiceEditUrl();
        [$negStandard, $negStandardUrl] = $this->makeNegativeLineInvoice('standard');
        [$negHourly, $negHourlyUrl] = $this->makeNegativeLineInvoice('hourly');

        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');
        $pages = $this->moneySweepPages();
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
        $this->get($invoiceUrl)->assertOk();
        $this->get($negStandardUrl)->assertOk();
        $this->get($negHourlyUrl)->assertOk();

        $existing = FlooringType::query()->orderBy('id')->get()
            ->mapWithKeys(fn (FlooringType $t) => [$t->id => (float) $t->installation_price_per_sqft])
            ->all();

        $base = $this->startLayoutServer();
        $script = base_path('tests/Feature/Admin/money-direct-entry.js');
        $out = storage_path('app/money-direct-entry.json');
        @unlink($out);

        $cmd = 'cd '.escapeshellarg(base_path())
            .' && MONEY_EMAIL='.escapeshellarg($user->email)
            .' MONEY_PASSWORD='.escapeshellarg('password')
            .' MONEY_BASE='.escapeshellarg($base)
            .' MONEY_PAGES='.escapeshellarg(json_encode($pages))
            .' MONEY_INVOICE_URL='.escapeshellarg($invoiceUrl)
            .' MONEY_NEG_STANDARD_URL='.escapeshellarg($negStandardUrl)
            .' MONEY_NEG_HOURLY_URL='.escapeshellarg($negHourlyUrl)
            .' MONEY_OUT='.escapeshellarg($out)
            .' node '.escapeshellarg($script);
        $output = [];
        $code = 0;
        exec($cmd.' 2>&1', $output, $code);
        $this->stopLayoutServer();
        $this->assertSame(0, $code, "Money direct-entry puppeteer failed:\n".implode("\n", $output)."\n".@file_get_contents($out));

        $report = json_decode((string) file_get_contents($out), true);
        $this->assertIsArray($report);
        $typed = 0;
        foreach ($report['pages'] as $page) {
            $typed += (int) $page['typed'];
            $this->assertSame(0, (int) $page['failed'], $page['url'].' had failed money fields');
            foreach ($page['fields'] as $field) {
                if (! empty($field['skip'])) {
                    continue;
                }
                $this->assertSame('direct', $field['entry'] ?? '', $page['url'].' '.$field['name']);
                if (! empty($field['existing'])) {
                    $this->assertEqualsWithDelta($this->parseMoney($field['before']), (float) $field['afterNum'], 0.0001, $field['name']);
                } else {
                    $this->assertEqualsWithDelta(4.75, (float) $field['afterNum'], 0.0001, $field['name']);
                }
                $this->assertNotEquals(4.75 / 100, (float) $field['afterNum'], $field['name']);
            }
        }
        $this->assertGreaterThanOrEqual(8, $typed);

        $this->assertIsArray($report['invoicePreview'] ?? null);
        $this->assertSame('130.00', $report['invoicePreview']['field'] ?? '');
        $this->assertSame('CA$870.00', $report['invoicePreview']['net'] ?? '');
        $this->assertSame('CA$113.10', $report['invoicePreview']['hst'] ?? '');
        $this->assertSame('CA$983.10', $report['invoicePreview']['grand'] ?? '');
        $this->assertNotSame('CA$998.70', $report['invoicePreview']['net'] ?? '');
        $this->assertNotSame('CA$1.30', $report['invoicePreview']['discount'] ?? '');

        $this->assertIsArray($report['behaviors'] ?? null);
        $this->assertEqualsWithDelta(4.0, (float) ($report['behaviors']['untaggedFour'] ?? 0), 0.0001);
        $this->assertNotEquals(0.04, (float) ($report['behaviors']['untaggedFour'] ?? 0));
        $this->assertEqualsWithDelta(4.75, (float) ($report['behaviors']['commaDecimal'] ?? 0), 0.0001);
        $this->assertTrue((bool) ($report['behaviors']['ambiguousInvalid'] ?? false));
        $this->assertSame('1.234,56', $report['behaviors']['ambiguousShown'] ?? '');
        $this->assertNotSame('1.23', $report['behaviors']['ambiguousShown'] ?? '');
        $this->assertTrue((bool) ($report['behaviors']['negativeInvalid'] ?? false));
        $this->assertNotEquals(5.0, (float) ($report['behaviors']['negativeValue'] ?? 0));
        $this->assertEqualsWithDelta(4.76, (float) ($report['behaviors']['rounded'] ?? 0), 0.0001);
        $this->assertEqualsWithDelta(1.01, (float) ($report['behaviors']['halfUp'] ?? 0), 0.0001);
        $this->assertTrue((bool) ($report['behaviors']['loneDotBlocked'] ?? false));
        $this->assertTrue((bool) ($report['behaviors']['gasCommaInvalid'] ?? false));
        $this->assertSame('1,459', $report['behaviors']['gasCommaShown'] ?? '');
        $this->assertNotSame('1,459.000', $report['behaviors']['gasCommaShown'] ?? '');
        $this->assertNotSame('1459.000', $report['behaviors']['gasCommaShown'] ?? '');
        $this->assertFalse((bool) ($report['behaviors']['storedNegativeBlank'] ?? true));
        $this->assertStringContainsString('-', (string) ($report['behaviors']['storedNegativeShown'] ?? ''));
        $this->assertFalse((bool) ($report['behaviors']['storedNegativeSubmitAllowed'] ?? true));
        $this->assertTrue((bool) ($report['behaviors']['storedNegativeInvalid'] ?? false));
        $this->assertEqualsWithDelta(-200.0, (float) ($report['behaviors']['allowNegativeNum'] ?? 0), 0.0001);
        $this->assertTrue((bool) ($report['behaviors']['allowNegativeSubmitAllowed'] ?? false));
        $this->assertFalse((bool) ($report['behaviors']['allowNegativeInvalid'] ?? true));
        $this->assertEqualsWithDelta(-200.0, (float) ($report['behaviors']['allowNegativeTypedNum'] ?? 0), 0.0001);
        $this->assertFalse((bool) ($report['behaviors']['allowNegativeTypedInvalid'] ?? true));
        $this->assertTrue((bool) ($report['behaviors']['oneDashTwoInvalid'] ?? false));
        $this->assertStringContainsString('valid amount', strtolower((string) ($report['behaviors']['oneDashTwoMessage'] ?? '')));
        $this->assertStringNotContainsString('negative', strtolower((string) ($report['behaviors']['oneDashTwoMessage'] ?? '')));
        $this->assertEqualsWithDelta(-30.49, (float) ($report['behaviors']['negHalfRound'] ?? 0), 0.0001);

        $this->assertIsArray($report['keypress'] ?? null);
        $this->assertTrue((bool) ($report['keypress']['typedMinus']['invalid'] ?? false));
        $this->assertStringContainsString('-', (string) ($report['keypress']['typedMinus']['value'] ?? ''));
        $this->assertStringContainsString('negative', strtolower((string) ($report['keypress']['typedMinus']['message'] ?? '')));
        $this->assertFalse((bool) ($report['keypress']['allowMinus']['invalid'] ?? true));
        $this->assertEqualsWithDelta(-200.0, (float) str_replace(',', '', (string) ($report['keypress']['allowMinus']['value'] ?? '')), 0.0001);
        $this->assertTrue((bool) ($report['keypress']['gasComma']['invalid'] ?? false));
        $this->assertNotSame('1459.000', $report['keypress']['gasComma']['value'] ?? '');
        $this->assertEqualsWithDelta(1.01, $this->parseMoney($report['keypress']['halfUp']['value'] ?? ''), 0.0001);
        $this->assertSame('1.234,56', $report['keypress']['mixedTyped']['value'] ?? '');
        $this->assertSame('1.234,56', $report['keypress']['mixedBlur']['value'] ?? '');
        $this->assertNotSame('1.23', $report['keypress']['mixedBlur']['value'] ?? '');
        $this->assertTrue((bool) ($report['keypress']['mixedBlur']['invalid'] ?? false));
        $this->assertFalse((bool) ($report['keypress']['mixedSubmit'] ?? true));
        $this->assertFalse((bool) ($report['keypress']['usThousands']['invalid'] ?? true));
        $this->assertEqualsWithDelta(1234.56, $this->parseMoney($report['keypress']['usThousands']['value'] ?? ''), 0.0001);
        $this->assertFalse((bool) ($report['keypress']['usMillions']['invalid'] ?? true));
        $this->assertEqualsWithDelta(1234567.89, $this->parseMoney($report['keypress']['usMillions']['value'] ?? ''), 0.0001);
        $this->assertFalse((bool) ($report['keypress']['usInteger']['invalid'] ?? true));
        $this->assertEqualsWithDelta(12345678.0, $this->parseMoney($report['keypress']['usInteger']['value'] ?? ''), 0.0001);
        $this->assertSame('1,2,3', $report['keypress']['ambigCommasTyped']['value'] ?? '');
        $this->assertSame('1,2,3', $report['keypress']['ambigCommasBlur']['value'] ?? '');
        $this->assertNotSame('1.23', $report['keypress']['ambigCommasBlur']['value'] ?? '');
        $this->assertTrue((bool) ($report['keypress']['ambigCommasBlur']['invalid'] ?? false));
        $this->assertFalse((bool) ($report['keypress']['ambigCommasSubmit'] ?? true));

        $this->assertIsArray($report['labourRateKeypress'] ?? null);
        $this->assertSame(0, (int) ($report['labourRateKeypress']['failed'] ?? 1));
        $labourFields = $report['labourRateKeypress']['fields'] ?? [];
        $this->assertCount(3, $labourFields);
        $expectedLabour = ['65', '35', '120.50'];
        foreach ($labourFields as $i => $field) {
            $this->assertSame($expectedLabour[$i], $field['typed'] ?? '');
            $this->assertSame($expectedLabour[$i], $field['focusedValue'] ?? '');
            $this->assertSame($expectedLabour[$i], $field['posted'] ?? '');
            $this->assertNotSame('6.01', $field['focusedValue'] ?? '');
            foreach ($field['snapshots'] ?? [] as $snap) {
                $this->assertSame($snap['typedSoFar'] ?? null, $snap['value'] ?? '', ($field['name'] ?? 'labour').' rewritten mid-type');
                $this->assertTrue((bool) ($snap['focused'] ?? false), ($field['name'] ?? 'labour').' lost focus mid-type');
            }
        }
        $labourPosted = (string) ($report['labourRateKeypress']['posted'] ?? '');
        $this->assertTrue(str_contains($labourPosted, '120.50'), 'paving draft must post owner 120.50');
        $savedQuote = PavingQuotation::query()->latest('id')->first();
        if ($savedQuote) {
            $this->assertTrue((bool) $savedQuote->is_draft);
            $this->assertEqualsWithDelta(120.50, (float) ($savedQuote->inputs['owner_hourly_rate'] ?? 0), 0.0001);
            $this->assertEqualsWithDelta(35.00, (float) ($savedQuote->inputs['helper_hourly_rate'] ?? 0), 0.0001);
        }
        $this->assertEqualsWithDelta(4.75, (float) str_replace(',', '', (string) ($report['pavingRateMask']['comma']['value'] ?? '')), 0.0001);
        $this->assertFalse((bool) ($report['pavingRateMask']['comma']['invalid'] ?? true));
        $this->assertTrue((bool) ($report['pavingRateMask']['negative']['invalid'] ?? false));
        $this->assertStringContainsString('-', (string) ($report['pavingRateMask']['negative']['value'] ?? ''));
        $this->assertTrue((bool) ($report['pavingRateMask']['ambiguous']['invalid'] ?? false));
        $this->assertNotSame('1.23', $report['pavingRateMask']['ambiguous']['value'] ?? '');
        $this->assertEqualsWithDelta(1.01, (float) ($report['pavingRateMask']['halfUp']['value'] ?? 0), 0.0001);
        $this->assertSame(1, (int) ($report['fenceGateNotice']['afterTicks'] ?? 0));
        $this->assertFalse((bool) ($report['fenceGateNotice']['noticeHidden'] ?? true));
        $this->assertStringContainsString('cleared', strtolower((string) ($report['fenceGateNotice']['notice'] ?? '')));
        $this->assertSame('3', (string) ($report['fenceGateNotice']['afterRetype']['gates'] ?? ''));
        $this->assertSame(2, (int) ($report['fenceGateNotice']['afterRetype']['ticks'] ?? 0));
        $this->assertStringNotContainsString('cleared', strtolower((string) ($report['fenceGateNotice']['afterRetype']['notice'] ?? '')));
        $afterRemove = $report['fenceGateNotice']['afterRemove'] ?? [];
        $this->assertSame(2, (int) ($afterRemove['ticks'] ?? 0));
        $removedIds = $afterRemove['ids'] ?? [];
        $restoredIds = $afterRemove['tickedIds'] ?? [];
        $this->assertNotSame('', (string) ($removedIds[0] ?? ''));
        $this->assertNotSame('', (string) ($removedIds[2] ?? ''));
        $this->assertContains((string) $removedIds[0], $restoredIds);
        $this->assertContains((string) $removedIds[2], $restoredIds);

        $this->assertIsArray($report['negativeResave'] ?? null);
        foreach (['standard', 'hourly'] as $kind) {
            $posted = (string) ($report['negativeResave'][$kind]['posted'] ?? '');
            $this->assertTrue(
                str_contains($posted, '-200.00') || str_contains($posted, '%2D200.00'),
                $kind.' page JS must post -200.00'
            );
        }
        $this->assertFalse((bool) ($report['hourlyMaterialsNegative']['allowNegative'] ?? true));
        $this->assertTrue((bool) ($report['hourlyMaterialsNegative']['invalid'] ?? false));
        $this->assertFalse((bool) ($report['hourlyMaterialsNegative']['submitAllowed'] ?? true));
        $this->assertTrue((bool) ($report['hourlyMaterialsNegative']['afterKindChange']['allowNegative'] ?? false));
        $this->assertFalse((bool) ($report['hourlyMaterialsNegative']['afterKindChange']['invalid'] ?? true));
        $this->assertStringContainsString('-', (string) ($report['hourlyMaterialsNegative']['afterKindChange']['value'] ?? ''));
        $negStandard->refresh();
        $negHourly->refresh();
        $this->assertNegativeLineTotals($negStandard, $negHourly);

        $created = FlooringType::query()->where('name', 'Direct entry oak')->first();
        $this->assertNotNull($created, 'browser save must create the add-type row');
        $this->assertEqualsWithDelta(4.75, (float) $created->installation_price_per_sqft, 0.0001);
        $this->assertNotEquals(0.0475, (float) $created->installation_price_per_sqft);
        foreach ($existing as $id => $price) {
            $this->assertEqualsWithDelta(
                $price,
                (float) FlooringType::query()->whereKey($id)->value('installation_price_per_sqft'),
                0.0001
            );
        }
    }

    private function makeInstallmentInvoiceEditUrl(): string
    {
        $project = ClientProject::factory()->create([
            'name' => 'Invoice discount preview',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_FIXED,
            'payment_plan' => 1,
            'total_amount' => 1000,
        ]);
        $project->installments()->create([
            'sort_order' => 0,
            'label' => 'On signing',
            'percentage' => 100,
            'amount' => 1000,
            'is_paid' => false,
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => 1,
            'installment_description' => 'On signing',
            'items' => [
                [
                    'description' => 'Deposit',
                    'amount' => 1000.00,
                    'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR,
                ],
            ],
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
            'hst_exempt' => false,
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);

        return parse_url(route('admin.client-projects.invoices.edit', [$project, $invoice]), PHP_URL_PATH);
    }

    /**
     * @return array{0: ClientProjectInvoice, 1: string}
     */
    private function makeNegativeLineInvoice(string $kind): array
    {
        $hourly = $kind === 'hourly';
        $project = ClientProject::factory()->create([
            'name' => $hourly ? 'Hourly credit invoice' : 'Standard credit invoice',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => $hourly ? ClientProject::BILLING_HOURLY : ClientProject::BILLING_FIXED,
            'hourly_rate' => $hourly ? 50 : null,
            'payment_plan' => 1,
            'total_amount' => $hourly ? null : 1000,
        ]);
        $items = $hourly
            ? [
                ['description' => 'Hours', 'amount' => 1000.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => 'Prepaid labour credit', 'amount' => -200.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT],
            ]
            : [
                ['description' => 'Labour', 'amount' => 1000.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => 'Deposit credit', 'amount' => -200.00],
            ];
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => null,
            'installment_description' => $hourly ? 'Hourly work' : 'Manual invoice',
            'items' => $items,
            'subtotal' => 800.00,
            'hst' => 104.00,
            'total' => 904.00,
            'hst_exempt' => false,
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);

        return [
            $invoice,
            parse_url(route('admin.client-projects.invoices.edit', [$project, $invoice]), PHP_URL_PATH),
        ];
    }

    private function assertNegativeLineTotals(ClientProjectInvoice $standard, ClientProjectInvoice $hourly): void
    {
        $this->assertEqualsWithDelta(800.00, (float) $standard->subtotal, 0.0001);
        $this->assertEqualsWithDelta(104.00, (float) $standard->hst, 0.0001);
        $this->assertEqualsWithDelta(904.00, (float) $standard->total, 0.0001);
        $standardCredit = collect($standard->cleanItems())->firstWhere('description', 'Deposit credit');
        $this->assertNotNull($standardCredit);
        $this->assertEqualsWithDelta(-200.00, (float) $standardCredit['amount'], 0.0001);

        $this->assertEqualsWithDelta(800.00, (float) $hourly->subtotal, 0.0001);
        $this->assertEqualsWithDelta(104.00, (float) $hourly->hst, 0.0001);
        $this->assertEqualsWithDelta(904.00, (float) $hourly->total, 0.0001);
    }

    /**
     * @return list<string>
     */
    private function moneySweepPages(): array
    {
        $project = ClientProject::query()->first();

        return array_values(array_filter([
            '/admin/quotations/flooring/price-configuration',
            '/admin/quotations/basement/price-configuration',
            '/admin/quotations/millwork/price-configuration',
            '/admin/quotations/shed/price-configuration',
            '/admin/quotations/tile/price-configuration',
            '/admin/quotations/painting/price-configuration',
            '/admin/quotations/trimming/price-configuration',
            '/admin/quotations/stairs/price-configuration',
            '/admin/quotations/paving/price-configuration',
            '/admin/quotations/fence/config',
            '/admin/quotations/low-deck/config',
            '/admin/quotations/shared-prices',
            '/admin/quotations/flooring/create',
            '/admin/quotations/basement/create',
            '/admin/quotations/tile/create',
            '/admin/quotations/millwork/create',
            '/admin/quotations/shed/create',
            '/admin/quotations/fence/create',
            '/admin/quotations/standard/create',
            '/admin/quotations/low-deck/create',
            '/admin/quotations/paving/create',
            '/admin/quotations/stairs/create',
            '/admin/materials/create',
            '/admin/helpers/create',
            '/admin/vehicles',
            '/admin/financials/expenses',
            '/admin/financials/etransfers',
            '/admin/client-projects/create',
            $project ? '/admin/client-projects/'.$project->id.'/edit' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function moneyInputTags(string $text): array
    {
        $tags = [];
        $offset = 0;
        while (($start = strpos($text, '<input', $offset)) !== false) {
            $end = $this->findInputTagEnd($text, $start + 6);
            if ($end === false) {
                break;
            }
            $tag = substr($text, $start, $end - $start + 1);
            if (str_contains($tag, 'cp-money-input')) {
                $tags[] = $tag;
            }
            $offset = $end + 1;
        }

        return $tags;
    }

    private function findInputTagEnd(string $text, int $from): int|false
    {
        $n = strlen($text);
        $quote = null;
        for ($i = $from; $i < $n; $i++) {
            $ch = $text[$i];
            if ($quote !== null) {
                if ($ch === '\\' && $i + 1 < $n) {
                    $i++;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                continue;
            }
            if (($ch === '=' || $ch === '-') && $i + 1 < $n && $text[$i + 1] === '>') {
                $i++;
                continue;
            }
            if ($ch === '>') {
                return $i;
            }
        }

        return false;
    }

    private function startLayoutServer(): string
    {
        $port = $this->layoutServerPort;
        $base = 'http://127.0.0.1:'.$port;
        $router = storage_path('app/money-direct-router.php');
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
            1 => ['file', sys_get_temp_dir().'/money-direct-serve.log', 'w'],
            2 => ['file', sys_get_temp_dir().'/money-direct-serve.err', 'w'],
        ];
        $this->layoutServerProcess = proc_open($cmd, $descriptors, $pipes, base_path(), $env);
        $this->assertTrue(is_resource($this->layoutServerProcess), 'Could not start PHP server for money sweep');
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
        $this->fail('Money sweep server did not become ready on '.$base);
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

    private function parseMoney(mixed $value): float
    {
        return (float) preg_replace('/[^\d.]/', '', (string) $value);
    }

    protected function tearDown(): void
    {
        $this->stopLayoutServer();
        // This class sets $connectionsToTransact = [] so the layout server
        // can see rows. Factory invoices are therefore committed and would
        // leak into later mysql-suite tests (e-transfer recency matching).
        ClientProjectInvoice::query()->delete();
        parent::tearDown();
    }
}
