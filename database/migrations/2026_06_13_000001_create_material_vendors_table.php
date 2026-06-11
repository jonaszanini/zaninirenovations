<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 80)->unique();
            $table->boolean('is_default_for_homedepot')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('material_vendors')->insert([
            'name' => 'Home Depot',
            'slug' => 'home-depot',
            'is_default_for_homedepot' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $homeDepotId = (int) DB::table('material_vendors')->where('slug', 'home-depot')->value('id');

        Schema::table('materials', function (Blueprint $table) use ($homeDepotId) {
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('category')
                ->constrained('material_vendors')
                ->nullOnDelete();
        });

        DB::table('materials')->update(['vendor_id' => $homeDepotId]);

        if (Schema::hasColumn('materials', 'vendor')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropColumn('vendor');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_vendors')) {
            return;
        }

        if (! Schema::hasColumn('materials', 'vendor')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->string('vendor', 120)->nullable()->after('category');
            });

            $names = DB::table('material_vendors')->pluck('name', 'id');

            DB::table('materials')->orderBy('id')->chunkById(200, function ($rows) use ($names) {
                foreach ($rows as $row) {
                    $name = $row->vendor_id ? ($names[$row->vendor_id] ?? null) : null;
                    DB::table('materials')->where('id', $row->id)->update(['vendor' => $name]);
                }
            });
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });

        Schema::dropIfExists('material_vendors');
    }
};
