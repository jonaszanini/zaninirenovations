<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'include_debris_removal')) {
                    $blueprint->boolean('include_debris_removal')->default(false);
                }
                if (! Schema::hasColumn($table, 'debris_removal_amount')) {
                    $blueprint->decimal('debris_removal_amount', 12, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $cols = [];
                if (Schema::hasColumn($table, 'include_debris_removal')) {
                    $cols[] = 'include_debris_removal';
                }
                if (Schema::hasColumn($table, 'debris_removal_amount')) {
                    $cols[] = 'debris_removal_amount';
                }
                if ($cols !== []) {
                    $blueprint->dropColumn($cols);
                }
            });
        }
    }
};
