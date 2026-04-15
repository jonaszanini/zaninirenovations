<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('completed_on')->nullable()->after('project_category_id');
        });

        foreach (DB::table('projects')->select('id', 'created_at')->cursor() as $row) {
            DB::table('projects')->where('id', $row->id)->update([
                'completed_on' => Carbon::parse($row->created_at)->toDateString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('completed_on');
        });
    }
};
