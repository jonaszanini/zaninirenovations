<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = function (Blueprint $table): void {
            $table->boolean('include_commute')->default(true)->after('linked_at');
            $table->decimal('commute_trip_percent', 5, 2)->default(100)->after('include_commute');
            $table->unsignedSmallInteger('commute_estimated_trips')->default(1)->after('commute_trip_percent');
            $table->decimal('commute_round_trip_cost', 10, 2)->nullable()->after('commute_estimated_trips');
            $table->decimal('commute_amount', 12, 2)->default(0)->after('commute_round_trip_cost');
        };

        Schema::table('standard_quotations', $columns);
        Schema::table('fence_quotations', $columns);
        Schema::table('paving_quotations', $columns);
        Schema::table('stairs_quotations', $columns);
    }

    public function down(): void
    {
        foreach (['standard_quotations', 'fence_quotations', 'paving_quotations', 'stairs_quotations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([
                    'include_commute',
                    'commute_trip_percent',
                    'commute_estimated_trips',
                    'commute_round_trip_cost',
                    'commute_amount',
                ]);
            });
        }
    }
};
