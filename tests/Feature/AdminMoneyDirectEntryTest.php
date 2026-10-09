<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\FlooringSettings;
use App\Models\FlooringType;
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

        $this->assertTrue(is_dir(base_path('node_modules/puppeteer')), 'Puppeteer must be installed');
        $pages = $this->moneySweepPages();
        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }

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
            '/admin/quotations/painting/create',
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
        parent::tearDown();
    }
}
