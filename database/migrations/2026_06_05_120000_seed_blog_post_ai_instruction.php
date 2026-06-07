<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ai_instructions')->where('key', 'blog_post')->exists()) {
            return;
        }

        $instruction = require database_path('data/ai_instruction_blog_post.php');

        DB::table('ai_instructions')->insert([
            'key' => 'blog_post',
            'name' => 'Blog post metadata',
            'instruction' => $instruction,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ai_instructions')->where('key', 'blog_post')->delete();
    }
};
