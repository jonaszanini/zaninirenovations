<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration has an earlier timestamp than the one that CREATES
        // stairs_settings (2026_05_18_120000), so on a fresh or forward
        // migrate the table does not exist yet. In that case the column is
        // already folded into the create migration, so we simply skip here.
        if (! Schema::hasTable('stairs_settings')
            || Schema::hasColumn('stairs_settings', 'nosing_option_photos')) {
            return;
        }

        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->json('nosing_option_photos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stairs_settings', function (Blueprint $table) {
            $table->dropColumn('nosing_option_photos');
        });
    }
};
