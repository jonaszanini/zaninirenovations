<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'standard_quotations',
        'fence_quotations',
        'paving_quotations',
        'stairs_quotations',
        'painting_quotations',
        'flooring_quotations',
        'tile_quotations',
        'low_deck_quotations',
        'client_project_quotations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'payment_percentages')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->json('payment_percentages')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'payment_percentages')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('payment_percentages');
            });
        }
    }
};
