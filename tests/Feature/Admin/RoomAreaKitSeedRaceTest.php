<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaAssembly;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\IntegrationTestCase;

class RoomAreaKitSeedRaceTest extends IntegrationTestCase
{
    /**
     * Child processes must see committed rows. RefreshDatabase's
     * per-test transaction would lock the seed tables and hang them.
     */
    protected function connectionsToTransact()
    {
        return [];
    }

    public function test_two_concurrent_first_loads_create_three_kits(): void
    {
        RoomAreaAssembly::query()->delete();
        DB::table('room_area_kit_seeds')->delete();
        $this->assertSame(0, RoomAreaAssembly::query()->count());

        $env = $this->childDatabaseEnv();
        $cmd = [PHP_BINARY, base_path('tests/Support/ensure_room_area_kits.php')];
        $one = new Process($cmd, base_path(), $env);
        $two = new Process($cmd, base_path(), $env);
        $one->setTimeout(45);
        $two->setTimeout(45);
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $this->assertSame(0, $one->getExitCode(), $one->getErrorOutput().$one->getOutput());
        $this->assertSame(0, $two->getExitCode(), $two->getErrorOutput().$two->getOutput());
        $this->assertSame(3, RoomAreaAssembly::query()->count());

        RoomAreaAssembly::query()->delete();
        DB::table('room_area_kit_seeds')->delete();
    }

    public function test_get_lock_timeout_does_not_load_empty_kits(): void
    {
        RoomAreaAssembly::query()->delete();
        DB::table('room_area_kit_seeds')->delete();
        $this->assertSame(0, RoomAreaAssembly::query()->count());

        $db = config('database.connections.'.config('database.default'));
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? '3306',
            $db['database'] ?? 'zanini_renovations_test'
        );
        $holder = new \PDO($dsn, (string) ($db['username'] ?? 'root'), (string) ($db['password'] ?? ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $lock = 'room_area_kit_seed_'.\App\Services\RoomAreaAssemblyDefaults::SEED_KEY;
        $got = (int) $holder->query("SELECT GET_LOCK(".$holder->quote($lock).", 0)")->fetchColumn();
        $this->assertSame(1, $got, 'test must hold the seed lock on a second connection');

        $threw = false;
        try {
            app(\App\Services\RoomAreaAssemblyDefaults::class)->ensure(0, 1);
        } catch (\App\Services\RoomAreaKitSeedException $e) {
            $threw = true;
            $this->assertStringContainsString('Could not load the default kits', $e->getMessage());
        } finally {
            $holder->query("SELECT RELEASE_LOCK(".$holder->quote($lock).")");
        }

        $this->assertTrue($threw, 'ensure() must surface a lock timeout instead of loading no kits');
        $this->assertSame(0, RoomAreaAssembly::query()->count());
    }

    /**
     * @return array<string, string>
     */
    private function childDatabaseEnv(): array
    {
        $connection = (string) config('database.default');
        $db = config('database.connections.'.$connection, []);

        return array_merge($_ENV, getenv() ?: [], [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection,
            'DB_HOST' => (string) ($db['host'] ?? env('DB_HOST', '127.0.0.1')),
            'DB_PORT' => (string) ($db['port'] ?? env('DB_PORT', '3306')),
            'DB_DATABASE' => (string) ($db['database'] ?? env('DB_DATABASE', '')),
            'DB_USERNAME' => (string) ($db['username'] ?? env('DB_USERNAME', '')),
            'DB_PASSWORD' => (string) ($db['password'] ?? env('DB_PASSWORD', '')),
            'CACHE_DRIVER' => 'array',
        ]);
    }
}
