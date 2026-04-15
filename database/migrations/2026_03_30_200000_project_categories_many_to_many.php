<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_project_category', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_category_id')->constrained('project_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'project_category_id']);
        });

        foreach (DB::table('projects')->select('id', 'project_category_id')->cursor() as $row) {
            if ($row->project_category_id === null) {
                continue;
            }
            DB::table('project_project_category')->insert([
                'project_id' => $row->id,
                'project_category_id' => $row->project_category_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_category_id']);
            $table->dropColumn('project_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_category_id')
                ->nullable()
                ->after('description')
                ->constrained('project_categories')
                ->restrictOnDelete();
        });

        $rows = DB::table('project_project_category')
            ->select('project_id', DB::raw('MIN(project_category_id) as project_category_id'))
            ->groupBy('project_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('projects')->where('id', $row->project_id)->update([
                'project_category_id' => $row->project_category_id,
            ]);
        }

        Schema::dropIfExists('project_project_category');
    }
};
