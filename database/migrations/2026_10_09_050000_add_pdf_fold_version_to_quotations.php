<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Version the client-PDF commute/debris fold. Existing rows stay on
 * version 1 so their lines, HST and total do not change.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'client_project_quotations',
        'fence_quotations',
        'paving_quotations',
        'standard_quotations',
        'stairs_quotations',
        'painting_quotations',
        'flooring_quotations',
        'tile_quotations',
        'low_deck_quotations',
        'basement_quotations',
        'shed_quotations',
        'trimming_quotations',
        'millwork_quotations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException(
                    'pdf_fold_version migration: required table ['.$table.'] is missing.'
                );
            }

            if (Schema::hasColumn($table, 'pdf_fold_version')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedTinyInteger('pdf_fold_version')->nullable();
            });

            DB::table($table)->whereNull('pdf_fold_version')->update(['pdf_fold_version' => 1]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException(
                    'pdf_fold_version migration down: required table ['.$table.'] is missing.'
                );
            }

            if (! Schema::hasColumn($table, 'pdf_fold_version')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('pdf_fold_version');
            });
        }
    }
};
