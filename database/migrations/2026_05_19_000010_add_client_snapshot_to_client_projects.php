<?php

use App\Models\ClientProject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->json('client_snapshot')->nullable()->after('client_address_longitude');
            $table->timestamp('client_snapshot_captured_at')->nullable()->after('client_snapshot');
        });

        if (! Schema::hasTable('client_projects')) {
            return;
        }

        $keys = ClientProject::clientSnapshotKeys();
        $now = now();

        DB::table('client_projects')
            ->where('status', ClientProject::STATUS_COMPLETED)
            ->whereNull('client_snapshot_captured_at')
            ->orderBy('id')
            ->each(function ($row) use ($keys, $now) {
                $snapshot = [];
                foreach ($keys as $key) {
                    $snapshot[$key] = $row->{$key} ?? null;
                }

                DB::table('client_projects')->where('id', $row->id)->update([
                    'client_snapshot' => json_encode($snapshot),
                    'client_snapshot_captured_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn(['client_snapshot', 'client_snapshot_captured_at']);
        });
    }
};
