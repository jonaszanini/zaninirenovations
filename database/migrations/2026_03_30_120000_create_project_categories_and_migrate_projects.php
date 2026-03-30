<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    private function seedCategories(): array
    {
        return [
            ['deck', 'Deck', 0],
            ['fencing', 'Fencing', 1],
            ['basement_renovation', 'Basement renovation', 2],
            ['tile', 'Tile', 3],
            ['flooring', 'Flooring', 4],
            ['forniture', 'Forniture', 5],
        ];
    }

    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ($this->seedCategories() as [$slug, $name, $order]) {
            DB::table('project_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_category_id')
                ->nullable()
                ->after('description')
                ->constrained('project_categories')
                ->restrictOnDelete();
        });

        $slugToId = DB::table('project_categories')->pluck('id', 'slug')->all();

        foreach (DB::table('projects')->cursor() as $project) {
            $slug = $project->category;
            $id = $slugToId[$slug] ?? null;
            if ($id === null) {
                $id = DB::table('project_categories')->insertGetId([
                    'name' => ucfirst(str_replace('_', ' ', $slug)),
                    'slug' => 'legacy-'.$project->id,
                    'sort_order' => 999,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $slugToId[$slug] = $id;
            }
            DB::table('projects')->where('id', $project->id)->update(['project_category_id' => $id]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('category', 64)->after('description');
        });

        $idToSlug = DB::table('project_categories')->pluck('slug', 'id')->all();
        foreach (DB::table('projects')->cursor() as $project) {
            $slug = $idToSlug[$project->project_category_id] ?? 'deck';
            DB::table('projects')->where('id', $project->id)->update(['category' => $slug]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_category_id']);
            $table->dropColumn('project_category_id');
        });

        Schema::dropIfExists('project_categories');
    }
};
