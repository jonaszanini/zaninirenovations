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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug', 180)->nullable()->unique()->after('title');
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
        });

        Schema::create('project_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('slug', 180)->unique();
            $table->timestamps();
        });

        $used = [];
        foreach (DB::table('projects')->orderBy('id')->get(['id', 'title']) as $row) {
            $slug = $this->uniqueSlug((string) $row->title, $used);
            $used[$slug] = true;
            DB::table('projects')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_slug_redirects');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'meta_title', 'meta_description']);
        });
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueSlug(string $title, array $used): string
    {
        $base = Str::slug($title) ?: 'project';
        $base = rtrim(substr($base, 0, 180), '-');
        if ($base === '') {
            $base = 'project';
        }

        $candidate = $base;
        $n = 2;
        while (isset($used[$candidate])) {
            $suffix = '-'.$n;
            $candidate = rtrim(substr($base, 0, 180 - strlen($suffix)), '-').$suffix;
            $n++;
        }

        return $candidate;
    }
};
