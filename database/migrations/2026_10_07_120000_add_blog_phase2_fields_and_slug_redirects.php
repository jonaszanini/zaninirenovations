<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('seo_title', 70)->nullable()->after('meta_description');
            $table->string('featured_image_alt', 255)->nullable()->after('featured_image_path');
            $table->string('cta_service', 80)->nullable()->after('author_name');
            $table->boolean('noindex')->default(false)->after('cta_service');
        });

        Schema::create('blog_post_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('slug', 255)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_slug_redirects');

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'featured_image_alt', 'cta_service', 'noindex']);
        });
    }
};
