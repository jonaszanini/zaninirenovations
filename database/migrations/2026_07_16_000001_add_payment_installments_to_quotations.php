<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Required payment installment count (1–4) on calculator quotations and
 * the client-facing project quotation PDF.
 */
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
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'payment_installments')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedTinyInteger('payment_installments')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'payment_installments')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('payment_installments');
            });
        }
    }
};
