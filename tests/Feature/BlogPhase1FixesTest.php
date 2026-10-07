<?php

namespace Tests\Feature;

use App\Models\AiInstruction;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Services\BlogCommentCaptcha;
use App\Services\BlogImageStorage;
use App\Services\GeminiService;
use App\Support\PlainTextLimiter;
use App\Support\SeoStructuredData;
use Illuminate\Support\Carbon;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BlogPhase1FixesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_increment_views_does_not_touch_updated_at(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $post = $this->makePublishedPost([
            'slug' => 'views-no-timestamp',
            'updated_at' => Carbon::parse('2026-06-01 12:00:00'),
            'created_at' => Carbon::parse('2026-06-01 12:00:00'),
        ]);
        $originalUpdated = $post->updated_at->copy();

        Carbon::setTestNow('2026-06-08 15:30:00');
        $post->incrementViews();
        $post->refresh();

        $this->assertSame(1, (int) $post->views_count);
        $this->assertTrue($post->updated_at->equalTo($originalUpdated));

        $this->get(route('blog.show', $post))->assertOk();
        $post->refresh();
        $this->assertSame(2, (int) $post->views_count);
        $this->assertTrue($post->updated_at->equalTo($originalUpdated));

        $page = $this->get(route('blog.show', $post));
        $page->assertOk();
        $graph = SeoStructuredData::blogPosting($post->fresh());
        $article = collect($graph['@graph'] ?? [])->first(fn ($node) => ($node['@type'] ?? '') === 'BlogPosting');
        $this->assertSame($originalUpdated->toAtomString(), $article['dateModified'] ?? null);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOk();
        $sitemap->assertSee('/blog/views-no-timestamp', false);
        $this->assertStringContainsString('2026-06-01', (string) $sitemap->getContent());
        $this->assertStringNotContainsString('2026-06-08', (string) $sitemap->getContent());
    }

    public function test_increment_views_skips_authenticated_admins(): void
    {
        $post = $this->makePublishedPost(['slug' => 'views-skip-admin']);

        $this->actingAsAdmin();
        $this->get(route('blog.show', $post))->assertOk();
        $this->assertSame(0, (int) $post->fresh()->views_count);
    }

    public function test_increment_views_skips_known_bots(): void
    {
        $post = $this->makePublishedPost(['slug' => 'views-skip-bots']);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
            ->get(route('blog.show', $post))
            ->assertOk();
        $this->assertSame(0, (int) $post->fresh()->views_count);
    }

    public function test_display_excerpt_applies_the_limit_to_stored_excerpt(): void
    {
        $post = new BlogPost([
            'excerpt' => str_repeat('Deck stain tips for Ottawa homeowners. ', 20),
        ]);

        $out = $post->displayExcerpt(80);
        $this->assertTrue(strlen($out) < strlen((string) $post->excerpt));
        $this->assertStringEndsWith('...', $out);
    }

    public function test_excerpt_over_limit_is_rejected_and_form_has_matching_maxlength(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.blog.posts.create'))
            ->assertOk()
            ->assertSee('maxlength="'.BlogPost::EXCERPT_MAX.'"', false)
            ->assertSee('id="bp-excerpt-count"', false)
            ->assertSee('admin-blog-editor.js', false);

        $editorJs = file_get_contents(public_path('js/admin-blog-editor.js'));
        $this->assertNotFalse($editorJs);
        $this->assertStringContainsString("normalize('NFD')", $editorJs);
        $this->assertStringContainsString('featuredPresent', $editorJs);
        $this->assertStringContainsString('relative_urls: false', $editorJs);

        $this->from(route('admin.blog.posts.create'))
            ->post(route('admin.blog.posts.store'), [
                'title' => 'Excerpt limit',
                'slug' => 'excerpt-limit',
                'excerpt' => str_repeat('a', BlogPost::EXCERPT_MAX + 1),
                'html_content' => '<p>Body</p>',
                'status' => BlogPost::STATUS_DRAFT,
                'author_name' => 'Jonas Zanini',
            ])
            ->assertSessionHasErrors('excerpt');
    }

    public function test_comment_reply_succeeds_with_captcha_and_fails_without(): void
    {
        $post = $this->makePublishedPost(['slug' => 'comment-reply-captcha']);
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id,
            'author_name' => 'Sam',
            'author_email' => 'sam@example.com',
            'body' => 'Great article.',
            'is_approved' => true,
        ]);

        $show = $this->get(route('blog.show', $post));
        $show->assertOk();
        $show->assertSee('captcha-answer-reply-'.$comment->id, false);
        $show->assertSee('name="captcha_token"', false);

        $this->from(route('blog.show', $post))
            ->post(route('blog.comments.store', $post), [
                'author_name' => 'Ada',
                'author_email' => 'ada@example.com',
                'body' => 'A reply without captcha.',
                'parent_id' => $comment->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('blog_comments', ['body' => 'A reply without captcha.']);

        $challenge = app(BlogCommentCaptcha::class)->generateMathChallenge();
        $this->from(route('blog.show', $post))
            ->post(route('blog.comments.store', $post), [
                'author_name' => 'Ada',
                'author_email' => 'ada@example.com',
                'body' => 'A reply with captcha.',
                'parent_id' => $comment->id,
                'captcha_token' => $challenge['token'],
                'captcha_answer' => $challenge['a'] + $challenge['b'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('blog_comments', [
            'parent_id' => $comment->id,
            'body' => 'A reply with captcha.',
        ]);
    }

    public function test_gemini_featured_image_stays_pending_until_save(): void
    {
        $this->actingAsAdmin();

        $post = $this->makePublishedPost([
            'slug' => 'keep-featured',
            'featured_image_path' => 'blog-featured/9/keep-me.webp',
            'html_content' => '<p>How to revive a deck this weekend with stain and boards.</p>',
        ]);

        BlogCategory::query()->create([
            'name' => 'Decks',
            'slug' => 'decks-phase1',
        ]);

        $this->assertNotNull(AiInstruction::forKey(AiInstruction::KEY_BLOG_POST));

        $this->mock(BlogImageStorage::class, function ($mock) {
            $mock->shouldReceive('delete')->never();
            $mock->shouldReceive('storeFeaturedFromBinary')
                ->once()
                ->withArgs(function ($bytes, $mime, $folderId) {
                    return $bytes === 'fake-image-bytes'
                        && $mime === 'image/png'
                        && (int) $folderId === 0;
                })
                ->andReturn('blog-featured/0/pending.webp');
            $mock->shouldReceive('publicUrl')
                ->once()
                ->with('blog-featured/0/pending.webp')
                ->andReturn('https://example.test/storage/blog-featured/0/pending.webp');
        });

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generateStructuredJson')->once()->andReturn([
                'excerpt' => 'A short excerpt.',
                'categories' => ['Decks'],
                'tags' => ['deck', 'ottawa'],
                'meta_description' => 'Protect your deck from moisture, UV, and winter damage, plus many extra words that would previously be chopped mid-word leaving a trailing comma in the live meta snippet for Ottawa homeowners.',
                'image_prompt' => 'Create a 16:9 photo of a stained deck.',
            ]);
            $mock->shouldReceive('generateImage')->once()->andReturn([
                'bytes' => 'fake-image-bytes',
                'mime' => 'image/png',
            ]);
        });

        $response = $this->postJson(route('admin.blog.posts.ai-metadata'), [
            'title' => $post->title,
            'html_content' => $post->html_content,
            'post_id' => $post->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('featured_image_path', 'blog-featured/0/pending.webp');

        $meta = (string) $response->json('meta_description');
        $this->assertLessThanOrEqual(155, mb_strlen($meta));
        $this->assertStringEndsNotWith(',', $meta);
        $this->assertSame($meta, PlainTextLimiter::trimToLength(
            'Protect your deck from moisture, UV, and winter damage, plus many extra words that would previously be chopped mid-word leaving a trailing comma in the live meta snippet for Ottawa homeowners.',
            155
        ));

        $this->assertSame('blog-featured/9/keep-me.webp', $post->fresh()->featured_image_path);
    }

    public function test_public_post_renders_link_image_and_centered_text(): void
    {
        $post = $this->makePublishedPost([
            'slug' => 'sanitizer-public-render',
            'html_content' => '<p>See <a href="https://zaninirenovations.ca/warranty">our warranty</a>.</p>'
                .'<p style="text-align: center;">Centered deck advice</p>'
                .'<p><img src="/storage/blog-inline/1/deck.webp" alt="Stained deck"></p>',
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('href="https://zaninirenovations.ca/warranty"', false)
            ->assertSee('src="/storage/blog-inline/1/deck.webp"', false)
            ->assertSee('text-align:center', false)
            ->assertSee('Centered deck advice', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedPost(array $overrides = []): BlogPost
    {
        return BlogPost::query()->create(array_merge([
            'title' => 'Phase 1 test post',
            'slug' => 'phase-1-test-post',
            'excerpt' => 'A short excerpt.',
            'html_content' => '<p>Body copy for the test post.</p>',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'author_name' => 'Jonas Zanini',
            'views_count' => 0,
        ], $overrides));
    }
}
