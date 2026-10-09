<?php

namespace Tests\Unit\Models;

use App\Models\BlogPost;
use PHPUnit\Framework\TestCase;

class BlogPostListHelpersTest extends TestCase
{
    public function test_format_views_count_uses_k_and_m_units(): void
    {
        $this->assertSame('42', (new BlogPost(['views_count' => 42]))->formatViewsCount());
        $this->assertSame('1,234', (new BlogPost(['views_count' => 1234]))->formatViewsCount());
        $this->assertSame('9,999', (new BlogPost(['views_count' => 9999]))->formatViewsCount());
        $this->assertSame('12.3K', (new BlogPost(['views_count' => 12345]))->formatViewsCount());
        $this->assertSame('123.5K', (new BlogPost(['views_count' => 123456]))->formatViewsCount());
        $this->assertSame('1M', (new BlogPost(['views_count' => 999999]))->formatViewsCount());
        $this->assertSame('1M', (new BlogPost(['views_count' => 1000000]))->formatViewsCount());
        $this->assertSame('1.5M', (new BlogPost(['views_count' => 1500000]))->formatViewsCount());
        $this->assertSame('12.3M', (new BlogPost(['views_count' => 12345678]))->formatViewsCount());
    }

    public function test_featured_image_list_src_keeps_https_and_rejects_http(): void
    {
        $https = new BlogPost(['featured_image_path' => 'https://cdn.example.com/blog/deck.webp']);
        $this->assertSame('https://cdn.example.com/blog/deck.webp', $https->featuredImageListSrc());
        $this->assertFalse($https->featuredImageIsMissing());

        $http = new BlogPost(['featured_image_path' => 'http://cdn.example.com/blog/deck.webp']);
        $this->assertNull($http->featuredImageListSrc());
        $this->assertTrue($http->featuredImageIsInsecureUrl());
        $this->assertFalse($http->featuredImageIsMissing());
    }

    public function test_featured_image_list_src_memoizes_content_hash(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3).'/app/Models/BlogPost.php');
        $this->assertIsString($src);
        $this->assertStringContainsString('featuredImageListHashCache', $src);
        $this->assertStringContainsString('hash_file', $src);
        $this->assertStringContainsString('??=', $src);
    }
}
