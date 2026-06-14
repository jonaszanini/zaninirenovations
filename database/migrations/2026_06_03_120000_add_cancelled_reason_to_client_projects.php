<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->text('cancelled_reason')->nullable()->after('status_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn('cancelled_reason');
        });
    }
};
