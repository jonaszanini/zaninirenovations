<?php

namespace Tests\Unit\Support;

use App\Support\HtmlContentSanitizer;
use Tests\TestCase;

class HtmlContentSanitizerTest extends TestCase
{
    public function test_quoted_https_link_survives(): void
    {
        $html = '<p>See <a href="https://zaninirenovations.ca/warranty">our warranty</a>.</p>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('<a href="https://zaninirenovations.ca/warranty"', $clean);
        $this->assertStringContainsString('our warranty', $clean);
    }

    public function test_mailto_tel_and_hash_links_survive(): void
    {
        $html = '<p><a href="mailto:hi@example.com">Email</a> <a href="tel:+16478347570">Call</a> <a href="#contact">Contact</a></p>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('href="mailto:hi@example.com"', $clean);
        $this->assertStringContainsString('href="tel:+16478347570"', $clean);
        $this->assertStringContainsString('href="#contact"', $clean);
    }

    public function test_editor_storage_image_src_survives(): void
    {
        $html = '<p><img src="/storage/blog-inline/1/deck.webp" alt="Stained deck" width="800" height="450"></p>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('src="/storage/blog-inline/1/deck.webp"', $clean);
        $this->assertStringContainsString('alt="Stained deck"', $clean);
        $this->assertStringContainsString('width="800"', $clean);
        $this->assertStringContainsString('height="450"', $clean);
    }

    public function test_relative_storage_image_src_is_normalized_and_kept(): void
    {
        $html = '<img src="../../storage/blog-inline/1/deck.webp" alt="Deck">';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('src="/storage/blog-inline/1/deck.webp"', $clean);
        $this->assertStringNotContainsString('../../storage', $clean);
    }

    public function test_unquoted_javascript_href_is_removed(): void
    {
        $html = '<a href=javascript:alert(1)>Click</a>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    public function test_onerror_without_space_is_removed(): void
    {
        $html = '<img src="/x.png" alt="a"onerror="alert(1)">';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringContainsString('src="/x.png"', $clean);
    }

    public function test_script_tags_and_event_handlers_are_stripped(): void
    {
        $html = '<p>Hello</p><script>alert(1)</script><img src="/ok.webp" onerror="alert(1)">';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('src="/ok.webp"', $clean);
    }

    public function test_alignment_style_and_class_survive(): void
    {
        $html = '<p style="text-align: center;" class="text-center">Centered deck tips</p>'
            .'<p class="align-left">Left</p>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertMatchesRegularExpression('/text-align:\s*center/', $clean);
        $this->assertStringContainsString('text-center', $clean);
        $this->assertStringContainsString('align-left', $clean);
        $this->assertStringContainsString('Centered deck tips', $clean);
    }

    public function test_target_and_rel_on_links_survive(): void
    {
        $html = '<a href="https://example.com" target="_blank" rel="noopener noreferrer">Ext</a>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_youtube_iframe_survives_and_srcdoc_does_not(): void
    {
        $html = '<iframe src="https://www.youtube.com/embed/abc123" width="560" height="315" allowfullscreen></iframe>'
            .'<iframe src="https://evil.example/x" srcdoc="<script>alert(1)</script>"></iframe>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('youtube.com/embed/abc123', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        $this->assertStringNotContainsString('srcdoc', $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }

    public function test_headings_lists_blockquote_and_tables_survive(): void
    {
        $html = '<h2>Title</h2><ul><li>One</li></ul><blockquote>Quote</blockquote>'
            .'<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>B</td></tr></tbody></table>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<li>One</li>', $clean);
        $this->assertStringContainsString('<blockquote>Quote</blockquote>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('<th>A</th>', $clean);
        $this->assertStringContainsString('<td>B</td>', $clean);
    }

    public function test_unwraps_pre_code_that_wraps_only_an_image(): void
    {
        $html = '<div class="highlight"><pre><code><img src="/storage/blog-inline/1/deck.webp" alt="Deck"></code></pre></div>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('src="/storage/blog-inline/1/deck.webp"', $clean);
        $this->assertStringContainsString('alt="Deck"', $clean);
        $this->assertStringNotContainsString('<pre', $clean);
        $this->assertStringNotContainsString('<code', $clean);
        $this->assertStringNotContainsString('highlight', $clean);
    }

    public function test_unwraps_pre_code_that_wraps_plain_prose(): void
    {
        $html = '<pre><code>Stain the deck after it dries for two days.</code></pre>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('Stain the deck after it dries for two days.', $clean);
        $this->assertStringNotContainsString('<pre', $clean);
        $this->assertStringNotContainsString('<code', $clean);
    }

    public function test_keeps_real_code_blocks(): void
    {
        $html = "<pre><code>function stain() {\n  return true;\n}</code></pre>";
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('<pre>', $clean);
        $this->assertStringContainsString('function stain', $clean);
    }

    public function test_removes_headings_that_contain_only_nbsp_or_whitespace(): void
    {
        $html = '<h2>&nbsp;</h2><h3>   </h3><h4><span>&nbsp;</span></h4><h2>Real heading</h2>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('Real heading', $clean);
        $this->assertSame(1, substr_count(strtolower($clean), '<h2'));
        $this->assertStringNotContainsString('<h3', $clean);
        $this->assertStringNotContainsString('<h4', $clean);
    }

    public function test_removes_empty_paragraphs_and_strips_html_comments(): void
    {
        $html = '<p>Keep this.</p><!-- Gemini leftover --><p>&nbsp;</p><p> <!----> </p><p></p>';
        $clean = HtmlContentSanitizer::sanitize($html);

        $this->assertStringContainsString('Keep this.', $clean);
        $this->assertStringNotContainsString('<!--', $clean);
        $this->assertStringNotContainsString('Gemini leftover', $clean);
        $this->assertSame(1, substr_count(strtolower($clean), '<p'));
    }
}
