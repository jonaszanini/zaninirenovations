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

        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'zanini_renovations_test',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'CACHE_DRIVER' => 'array',
        ]);
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
    }
}
