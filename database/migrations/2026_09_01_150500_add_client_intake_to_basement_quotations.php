<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basement_quotations', function (Blueprint $table) {
            $table->string('client_intake_token', 64)->nullable()->unique()->after('linked_at');
            $table->timestamp('client_intake_sent_at')->nullable()->after('client_intake_token');
            $table->timestamp('client_intake_submitted_at')->nullable()->after('client_intake_sent_at');
            $table->json('client_intake_answers')->nullable()->after('client_intake_submitted_at');
            $table->json('client_filled_keys')->nullable()->after('client_intake_answers');
        });
    }

    public function down(): void
    {
        Schema::table('basement_quotations', function (Blueprint $table) {
            $table->dropUnique(['client_intake_token']);
            $table->dropColumn([
                'client_intake_token',
                'client_intake_sent_at',
                'client_intake_submitted_at',
                'client_intake_answers',
                'client_filled_keys',
            ]);
        });
    }
};
