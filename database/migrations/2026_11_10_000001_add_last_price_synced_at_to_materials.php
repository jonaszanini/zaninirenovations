<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->timestamp('last_price_synced_at')->nullable()->after('last_scraped_at');
        });

        if (! Schema::hasColumn('materials', 'last_price_synced_at')) {
            return;
        }

        Material::query()
            ->where('last_scrape_status', Material::SCRAPE_OK)
            ->whereNotNull('last_scraped_at')
            ->update(['last_price_synced_at' => DB::raw('last_scraped_at')]);
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('last_price_synced_at');
        });
    }
};
