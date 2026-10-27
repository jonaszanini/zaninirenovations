<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('address');
            $table->unsignedSmallInteger('drive_minutes')->nullable()->after('distance_km');
            $table->timestamp('distance_checked_at')->nullable()->after('drive_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_sites', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'drive_minutes', 'distance_checked_at']);
        });
    }
};
