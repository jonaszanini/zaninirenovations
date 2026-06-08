<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ai_generated_images MODIFY aspect_ratio VARCHAR(10) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE ai_generated_images MODIFY image_size VARCHAR(10) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ai_generated_images MODIFY aspect_ratio VARCHAR(10) NOT NULL DEFAULT '16:9'");
        DB::statement("ALTER TABLE ai_generated_images MODIFY image_size VARCHAR(10) NOT NULL DEFAULT '1K'");
    }
};
