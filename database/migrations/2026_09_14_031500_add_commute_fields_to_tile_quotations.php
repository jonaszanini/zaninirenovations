<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tile_quotations') || Schema::hasColumn('tile_quotations', 'include_commute')) {
            return;
        }

        Schema::table('tile_quotations', function (Blueprint $table) {
            $table->boolean('include_commute')->default(false)->after('payment_percentages');
            $table->decimal('commute_trip_percent', 5, 2)->default(100)->after('include_commute');
            $table->unsignedSmallInteger('commute_estimated_trips')->default(1)->after('commute_trip_percent');
            $table->decimal('commute_round_trip_cost', 10, 2)->nullable()->after('commute_estimated_trips');
            $table->decimal('commute_amount', 12, 2)->default(0)->after('commute_round_trip_cost');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tile_quotations') || ! Schema::hasColumn('tile_quotations', 'include_commute')) {
            return;
        }

        Schema::table('tile_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'include_commute',
                'commute_trip_percent',
                'commute_estimated_trips',
                'commute_round_trip_cost',
                'commute_amount',
            ]);
        });
    }
};
