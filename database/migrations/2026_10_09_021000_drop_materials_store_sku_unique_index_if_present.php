<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-only rollback of a short-lived unique index from a previous revision.
 * Does not rewrite or remount material rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('materials', 'materials_store_sku_unique')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropUnique('materials_store_sku_unique');
            });
        }

        if (! $this->indexExists('materials', 'materials_store_sku_index')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->index('store_sku');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: a unique index on store_sku is not desired.
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
};
