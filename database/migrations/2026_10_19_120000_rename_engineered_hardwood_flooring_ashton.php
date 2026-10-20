<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('projects')
            ->where('slug', 'engineering-hard-wood-flooring-installation')
            ->orWhere('title', 'like', '%Engineering hard-wood%')
            ->update([
                'title' => 'Engineered Hardwood Flooring Installation in Ashton',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retain updated title for SEO.
    }
};
