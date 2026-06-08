<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table) {
            $table->string('original_image_path', 500)->nullable()->after('image_path');
            $table->string('webp_image_path', 500)->nullable()->after('original_image_path');
            $table->string('aspect_ratio', 10)->default('16:9')->after('webp_image_path');
            $table->string('image_size', 10)->default('1K')->after('aspect_ratio');
        });

        DB::table('ai_generated_images')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('ai_generated_images')
                    ->where('id', $row->id)
                    ->update([
                        'webp_image_path' => $row->image_path,
                    ]);
            }
        });

        Schema::table('ai_generated_images', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generated_images', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('prompt');
        });

        DB::table('ai_generated_images')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('ai_generated_images')
                    ->where('id', $row->id)
                    ->update([
                        'image_path' => $row->webp_image_path,
                    ]);
            }
        });

        Schema::table('ai_generated_images', function (Blueprint $table) {
            $table->dropColumn([
                'original_image_path',
                'webp_image_path',
                'aspect_ratio',
                'image_size',
            ]);
        });
    }
};
