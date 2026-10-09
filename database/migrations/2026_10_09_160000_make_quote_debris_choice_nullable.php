<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'standard_quotations',
        'fence_quotations',
        'paving_quotations',
        'stairs_quotations',
        'painting_quotations',
        'low_deck_quotations',
        'flooring_quotations',
        'tile_quotations',
        'basement_quotations',
        'shed_quotations',
        'millwork_quotations',
        'trimming_quotations',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'include_debris_removal')) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY `include_debris_removal` TINYINT(1) NULL DEFAULT 0");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'include_debris_removal')) {
                continue;
            }
            DB::table($table)->whereNull('include_debris_removal')->update(['include_debris_removal' => 0]);
            DB::statement("ALTER TABLE `{$table}` MODIFY `include_debris_removal` TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
};
