<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->boolean('hide_from_public_filters')->default(false)->after('sort_order');
        });

        DB::table('project_categories')
            ->whereIn('slug', ['plumbing-fixtures', 'plumbing_fixtures', 'plumbing-fixture'])
            ->update(['hide_from_public_filters' => true]);
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->dropColumn('hide_from_public_filters');
        });
    }
};
