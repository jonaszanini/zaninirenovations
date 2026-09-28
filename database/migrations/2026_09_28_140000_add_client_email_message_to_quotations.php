<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'standard_quotations',
        'fence_quotations',
        'paving_quotations',
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
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'client_email_message')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->text('client_email_message')->nullable();
                });
            }
        }

        if (Schema::hasTable('ai_instructions')
            && ! DB::table('ai_instructions')->where('key', 'quotation_client_email_message')->exists()) {
            DB::table('ai_instructions')->insert([
                'key' => 'quotation_client_email_message',
                'name' => 'Quotation client email message',
                'instruction' => require database_path('data/ai_instruction_quotation_client_email_message.php'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'client_email_message')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('client_email_message');
                });
            }
        }

        if (Schema::hasTable('ai_instructions')) {
            DB::table('ai_instructions')->where('key', 'quotation_client_email_message')->delete();
        }
    }
};
