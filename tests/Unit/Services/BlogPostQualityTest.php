<?php

namespace Tests\Unit\Services;

use App\Services\BlogPostQuality;
use Tests\TestCase;

class BlogPostQualityTest extends TestCase
{
    public function test_flags_the_word_licensed(): void
    {
        $issues = BlogPostQuality::contentIssues('<p>We are a licensed renovation company.</p>');
        $codes = array_column($issues, 'code');

        $this->assertContains('licensed', $codes);
    }

    public function test_flags_empty_headings_missing_internal_links_and_alt(): void
    {
        $html = '<h2>  </h2><p>Hello</p><p><img src="/storage/x.webp"></p>';
        $issues = BlogPostQuality::contentIssues($html);
        $codes = array_column($issues, 'code');

        $this->assertContains('empty_heading', $codes);
        $this->assertContains('internal_links', $codes);
        $this->assertContains('missing_alt', $codes);
    }

    public function test_accepts_internal_links_and_alt_text(): void
    {
        $html = '<p>See <a href="https://zaninirenovations.ca/warranty">warranty</a>.</p>'
            .'<p><img src="/storage/x.webp" alt="Stained deck"></p><h2>Tips</h2>';
        $issues = BlogPostQuality::contentIssues($html);
        $codes = array_column($issues, 'code');

        $this->assertNotContains('licensed', $codes);
        $this->assertNotContains('empty_heading', $codes);
        $this->assertNotContains('internal_links', $codes);
        $this->assertNotContains('missing_alt', $codes);
    }

    public function test_checklist_requires_title_and_blocks_licensed(): void
    {
        $result = BlogPostQuality::checklist([
            'title' => '',
            'slug' => 'x',
            'html_content' => '<p>'.str_repeat('word ', 90).' licensed crew.</p>',
            'featured_image' => false,
            'category_ids' => [],
        ]);

        $this->assertFalse($result['ready']);
        $keys = array_column($result['items'], 'key');
        $this->assertContains('licensed', $keys);
        $licensed = collect($result['items'])->firstWhere('key', 'licensed');
        $this->assertFalse($licensed['ok']);
    }
}
