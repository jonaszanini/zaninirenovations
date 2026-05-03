<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links projects to the clients directory, then one-time backfill:
 * dedupe existing projects into `clients` rows (by email when present,
 * else name + normalised phone) and assign `client_id` on every project.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('id')
                ->constrained('clients')
                ->nullOnDelete();
        });

        if (! Schema::hasTable('clients') || ! Schema::hasTable('client_projects')) {
            return;
        }

        $rows = DB::table('client_projects')
            ->orderBy('id')
            ->get(['id', 'client_name', 'client_email', 'client_email_secondary', 'client_phone', 'client_address']);

        /** @var array<string, list<int>> $keyToProjectIds */
        $keyToProjectIds = [];

        foreach ($rows as $r) {
            $email = $r->client_email ? strtolower(trim((string) $r->client_email)) : '';
            if ($email !== '') {
                $key = 'e:'.$email;
            } else {
                $phone = preg_replace('/\D/', '', (string) ($r->client_phone ?? ''));
                $nameKey = strtolower(trim((string) ($r->client_name ?? '')));
                $key = 'np:'.$nameKey.'|'.$phone;
            }
            $keyToProjectIds[$key] ??= [];
            $keyToProjectIds[$key][] = (int) $r->id;
        }

        $now = now();

        foreach ($keyToProjectIds as $projectIds) {
            $first = DB::table('client_projects')
                ->whereIn('id', $projectIds)
                ->orderBy('id')
                ->first();

            if (!$first) {
                continue;
            }

            $clientId = DB::table('clients')->insertGetId([
                'name' => $first->client_name,
                'email' => $first->client_email ?: null,
                'email_secondary' => $first->client_email_secondary ?: null,
                'phone' => $first->client_phone ?: null,
                'address' => $first->client_address ?: null,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('client_projects')->whereIn('id', $projectIds)->update(['client_id' => $clientId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_projects', 'client_id')) {
            DB::table('client_projects')->update(['client_id' => null]);
        }

        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
