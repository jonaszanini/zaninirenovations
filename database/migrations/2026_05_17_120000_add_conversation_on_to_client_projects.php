<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where ongoing client conversation happens (may differ from first "contact via").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->string('conversation_on', 32)->nullable()->after('contacted_via');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn('conversation_on');
        });
    }
};
