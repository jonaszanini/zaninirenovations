<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_area_assemblies')) {
            Schema::table('room_area_assemblies', function (Blueprint $table) {
                $table->unique('name', 'room_area_assemblies_name_unique');
            });
        }

        if (Schema::hasTable('room_area_kit_seeds') && ! Schema::hasColumn('room_area_kit_seeds', 'owner')) {
            Schema::table('room_area_kit_seeds', function (Blueprint $table) {
                $table->string('owner', 40)->default('app');
                $table->unique(['owner', 'key'], 'room_area_kit_seeds_owner_key_unique');
            });
        }

        if (Schema::hasTable('room_area_quote_tokens') && ! Schema::hasColumn('room_area_quote_tokens', 'expires_at')) {
            Schema::table('room_area_quote_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('consumed_at');
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_area_assemblies')) {
            Schema::table('room_area_assemblies', function (Blueprint $table) {
                $table->dropUnique('room_area_assemblies_name_unique');
            });
        }

        if (Schema::hasTable('room_area_kit_seeds') && Schema::hasColumn('room_area_kit_seeds', 'owner')) {
            Schema::table('room_area_kit_seeds', function (Blueprint $table) {
                $table->dropUnique('room_area_kit_seeds_owner_key_unique');
                $table->dropColumn('owner');
            });
        }

        if (Schema::hasTable('room_area_quote_tokens') && Schema::hasColumn('room_area_quote_tokens', 'expires_at')) {
            Schema::table('room_area_quote_tokens', function (Blueprint $table) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            });
        }
    }
};
