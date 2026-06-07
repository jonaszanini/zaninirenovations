<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('sms_intro_sent_at')->nullable()->after('phone');
        });

        // Existing directory clients are treated as already notified so we do
        // not blast them with the compliance intro on the next outbound SMS.
        DB::table('clients')->update(['sms_intro_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('sms_intro_sent_at');
        });
    }
};
