<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $seed = [
            ['name' => 'Lumber', 'sort_order' => 0],
            ['name' => 'Hardware', 'sort_order' => 10],
            ['name' => 'Fencing', 'sort_order' => 20],
            ['name' => 'Decking', 'sort_order' => 30],
            ['name' => 'Paving', 'sort_order' => 40],
            ['name' => 'Concrete', 'sort_order' => 50],
            ['name' => 'Fasteners', 'sort_order' => 60],
            ['name' => 'Finishing', 'sort_order' => 70],
            ['name' => 'Electrical', 'sort_order' => 80],
            ['name' => 'Plumbing', 'sort_order' => 90],
            ['name' => 'Other', 'sort_order' => 100],
        ];

        foreach ($seed as $row) {
            DB::table('material_categories')->insert([
                'name' => $row['name'],
                'slug' => Str::slug($row['name']) ?: 'category',
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('short_name')
                ->constrained('material_categories')
                ->nullOnDelete();
        });

        $categoryIdsByName = DB::table('material_categories')->pluck('id', 'name');

        DB::table('materials')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($categoryIdsByName) {
                foreach ($rows as $row) {
                    $name = trim((string) $row->category);
                    if ($name === '') {
                        continue;
                    }

                    $categoryId = $categoryIdsByName[$name] ?? null;
                    if ($categoryId === null) {
                        $slug = Str::slug($name) ?: 'category';
                        $base = $slug;
                        $n = 2;
                        while (DB::table('material_categories')->where('slug', $slug)->exists()) {
                            $slug = $base.'-'.$n;
                            $n++;
                        }

                        $categoryId = DB::table('material_categories')->insertGetId([
                            'name' => $name,
                            'slug' => $slug,
                            'sort_order' => 500,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $categoryIdsByName[$name] = $categoryId;
                    }

                    DB::table('materials')->where('id', $row->id)->update([
                        'category_id' => $categoryId,
                    ]);
                }
            });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_categories')) {
            return;
        }

        if (! Schema::hasColumn('materials', 'category')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->string('category', 120)->nullable()->after('short_name');
                $table->index('category');
            });

            $names = DB::table('material_categories')->pluck('name', 'id');

            DB::table('materials')->orderBy('id')->chunkById(200, function ($rows) use ($names) {
                foreach ($rows as $row) {
                    $name = $row->category_id ? ($names[$row->category_id] ?? null) : null;
                    DB::table('materials')->where('id', $row->id)->update(['category' => $name]);
                }
            });
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('material_categories');
    }
};
