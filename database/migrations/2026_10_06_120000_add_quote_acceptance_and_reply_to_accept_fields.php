<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE_TABLES = [
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
        if (Schema::hasTable('client_project_quotations')) {
            Schema::table('client_project_quotations', function (Blueprint $table) {
                if (! Schema::hasColumn('client_project_quotations', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable();
                }
                if (! Schema::hasColumn('client_project_quotations', 'accepted_via')) {
                    $table->string('accepted_via', 16)->nullable();
                }
                if (! Schema::hasColumn('client_project_quotations', 'accepted_ip')) {
                    $table->string('accepted_ip', 45)->nullable();
                }
                if (! Schema::hasColumn('client_project_quotations', 'accepted_source_note')) {
                    $table->string('accepted_source_note', 255)->nullable();
                }
                if (! Schema::hasColumn('client_project_quotations', 'ask_reply_to_accept')) {
                    $table->boolean('ask_reply_to_accept')->nullable();
                }
            });
        }

        foreach (self::SOURCE_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'ask_reply_to_accept')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('ask_reply_to_accept')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_project_quotations')) {
            Schema::table('client_project_quotations', function (Blueprint $table) {
                $drops = [];
                foreach (['accepted_at', 'accepted_via', 'accepted_ip', 'accepted_source_note', 'ask_reply_to_accept'] as $col) {
                    if (Schema::hasColumn('client_project_quotations', $col)) {
                        $drops[] = $col;
                    }
                }
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }

        foreach (self::SOURCE_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'ask_reply_to_accept')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('ask_reply_to_accept');
                });
            }
        }
    }
};
