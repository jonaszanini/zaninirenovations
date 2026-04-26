<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->boolean('visit_required')->default(false)->after('end_date');
            $table->date('visit_date')->nullable()->after('visit_required');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn(['visit_required', 'visit_date']);
        });
    }
};
