<?php

namespace Tests\Feature;

use App\Models\SharedWork;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class SharedPricesParallelSaveTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    public function test_parallel_saves_retry_the_transaction_on_duplicate_keys(): void
    {
        $email = 'parallel-shared-prices@zanini.ca';
        User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $worker = base_path('tests/Support/shared-prices-parallel-worker.php');
        $this->assertFileExists($worker);

        $env = [];
        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        $env['APP_ENV'] = 'testing';
        $env['DB_CONNECTION'] = (string) config('database.default');
        $env['DB_HOST'] = (string) config('database.connections.'.config('database.default').'.host');
        $env['DB_PORT'] = (string) config('database.connections.'.config('database.default').'.port');
        $env['DB_DATABASE'] = (string) config('database.connections.'.config('database.default').'.database');
        $env['DB_USERNAME'] = (string) config('database.connections.'.config('database.default').'.username');
        $env['DB_PASSWORD'] = (string) config('database.connections.'.config('database.default').'.password');
        $env['APP_KEY'] = (string) config('app.key');

        $count = 16;
        $processes = [];
        $pipes = [];
        for ($i = 0; $i < $count; $i++) {
            $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($worker).' '.$i.' '.escapeshellarg($email);
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = proc_open($cmd, $descriptors, $procPipes, base_path(), $env);
            $this->assertTrue(is_resource($proc), 'Could not start parallel worker '.$i);
            fclose($procPipes[0]);
            $processes[$i] = $proc;
            $pipes[$i] = $procPipes;
        }

        $failed = [];
        foreach ($processes as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $status = proc_close($proc);
            if ($status !== 0) {
                $failed[] = 'worker '.$i.': '.$stdout.' '.$stderr;
            }
        }

        $this->assertSame([], $failed, "Parallel saves failed:\n".implode("\n", $failed));

        $created = SharedWork::query()
            ->where('group_key', 'flooring')
            ->where('label', 'like', 'Par%allel')
            ->count();
        $this->assertSame($count, $created);
        $keys = SharedWork::query()
            ->where('group_key', 'flooring')
            ->where('label', 'like', 'Par%allel')
            ->pluck('key');
        $this->assertCount($count, $keys->unique());
    }
}
