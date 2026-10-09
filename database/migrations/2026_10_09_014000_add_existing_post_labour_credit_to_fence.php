<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Labour credit when an existing-post tick actually removes a post.
 *
 * Config default is $30. Existing saved quotes are snapshotted at $0 so
 * their labour totals stay exactly as they were before this feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->decimal('existing_post_labour_credit', 10, 2)->default(30.00);
        });

        DB::table('fence_formula_settings')->update([
            'existing_post_labour_credit' => 30.00,
        ]);

        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->decimal('existing_post_labour_credit', 10, 2)->nullable()->after('labour_rate_8ft_per_ft');
        });

        DB::table('fence_quotations')->update([
            'existing_post_labour_credit' => 0.00,
        ]);
    }

    public function down(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->dropColumn('existing_post_labour_credit');
        });

        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn('existing_post_labour_credit');
        });
    }
};
