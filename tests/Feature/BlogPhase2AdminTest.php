<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostSlugRedirect;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BlogPhase2AdminTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_status_tabs_filter_posts(): void
    {
        $this->actingAsAdmin();
        $this->makePost(['title' => 'Draft A', 'slug' => 'draft-a', 'status' => BlogPost::STATUS_DRAFT]);
        $this->makePost([
            'title' => 'Live A',
            'slug' => 'live-a',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $this->makePost([
            'title' => 'Later A',
            'slug' => 'later-a',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('admin.blog.posts.index'))
            ->assertOk()
            ->assertSee('Search posts', false)
            ->assertSee('admin-actions-dropdown', false)
            ->assertDontSee('stored for Phase 3', false);

        $this->get(route('admin.blog.posts.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Draft A')
            ->assertDontSee('Live A')
            ->assertDontSee('Later A');

        $this->get(route('admin.blog.posts.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee('Live A')
            ->assertDontSee('Draft A')
            ->assertDontSee('Later A');

        $this->get(route('admin.blog.posts.index', ['status' => 'scheduled']))
            ->assertOk()
            ->assertSee('Later A')
            ->assertDontSee('Draft A')
            ->assertDontSee('Live A');
    }

    public function test_category_filter_and_sort(): void
    {
        $this->actingAsAdmin();
        $decks = BlogCategory::query()->create(['name' => 'Decks', 'slug' => 'decks']);
        $fences = BlogCategory::query()->create(['name' => 'Fences', 'slug' => 'fences']);

        $older = $this->makePost(['title' => 'Zebra post', 'slug' => 'zebra-post', 'views_count' => 2]);
        $newer = $this->makePost(['title' => 'Alpha post', 'slug' => 'alpha-post', 'views_count' => 9]);
        $older->categories()->sync([$decks->id]);
        $newer->categories()->sync([$fences->id]);

        $this->get(route('admin.blog.posts.index', ['category' => $decks->id]))
            ->assertOk()
            ->assertSee('Zebra post')
            ->assertDontSee('Alpha post');

        $sorted = $this->get(route('admin.blog.posts.index', ['sort' => 'title']));
        $sorted->assertOk();
        $html = $sorted->getContent();
        $this->assertTrue(strpos($html, 'Alpha post') < strpos($html, 'Zebra post'));
    }

    public function test_duplicate_and_delete_post(): void
    {
        $this->actingAsAdmin();
        $post = $this->makePost([
            'title' => 'Source post',
            'slug' => 'source-post',
            'seo_title' => 'Source SEO',
            'featured_image_alt' => 'A deck',
        ]);

        $this->post(route('admin.blog.posts.duplicate', $post))
            ->assertRedirect();

        $copy = BlogPost::query()->where('slug', 'source-post-copy')->first()
            ?? BlogPost::query()->where('title', 'Source post (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame(BlogPost::STATUS_DRAFT, $copy->status);
        $this->assertSame('Source SEO', $copy->seo_title);
        $this->assertSame('A deck', $copy->featured_image_alt);
        $this->assertNotSame($post->id, $copy->id);

        $this->delete(route('admin.blog.posts.destroy', $copy))
            ->assertRedirect(route('admin.blog.posts.index'));
        $this->assertNull(BlogPost::query()->find($copy->id));
    }

    public function test_draft_preview_requires_auth_and_is_noindex(): void
    {
        $post = $this->makePost([
            'title' => 'Hidden draft',
            'slug' => 'hidden-draft',
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->get(route('blog.show', $post))->assertNotFound();
        $this->get(route('admin.blog.posts.preview', $post))->assertRedirect();

        $this->actingAsAdmin();
        $this->get(route('admin.blog.posts.preview', $post))
            ->assertOk()
            ->assertSee('Draft preview')
            ->assertSee('Hidden draft')
            ->assertSee('noindex, nofollow', false);

        $this->assertSame(0, (int) $post->fresh()->views_count);
    }

    public function test_published_slug_change_301s_old_url(): void
    {
        $this->actingAsAdmin();
        $post = $this->makePost([
            'title' => 'Old title',
            'slug' => 'old-deck-tips',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->put(route('admin.blog.posts.update', $post), $this->payload($post, [
            'slug' => 'new-deck-tips',
            'status' => BlogPost::STATUS_PUBLISHED,
        ]))->assertRedirect();

        $post->refresh();
        $this->assertSame('new-deck-tips', $post->slug);
        $this->assertTrue(BlogPostSlugRedirect::query()->where('slug', 'old-deck-tips')->where('blog_post_id', $post->id)->exists());

        $this->get('/blog/old-deck-tips')
            ->assertRedirect('/blog/new-deck-tips')
            ->assertStatus(301);
        $this->get('/blog/new-deck-tips')->assertOk();
    }

    public function test_autosave_creates_draft_and_updates_fields(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson(route('admin.blog.posts.autosave'), [
            'title' => 'Autosave start',
            'html_content' => '<p>Hello deck world.</p>',
            'status' => 'draft',
            'author_name' => 'Jonas Zanini',
        ]);
        $create->assertOk()->assertJsonPath('ok', true);
        $id = (int) $create->json('id');
        $this->assertGreaterThan(0, $id);

        $post = BlogPost::query()->findOrFail($id);
        $this->assertSame(BlogPost::STATUS_DRAFT, $post->status);

        $this->postJson(route('admin.blog.posts.autosave.update', $post), [
            'title' => 'Autosave start',
            'seo_title' => 'Autosave SEO title',
            'featured_image_alt' => 'Ottawa deck',
            'html_content' => '<p>Updated body.</p>',
            'status' => 'draft',
            'author_name' => 'Jonas Zanini',
        ])->assertOk();

        $post->refresh();
        $this->assertSame('Autosave SEO title', $post->seo_title);
        $this->assertSame('Ottawa deck', $post->featured_image_alt);
        $this->assertStringContainsString('Updated body', (string) $post->html_content);
    }

    public function test_checklist_and_content_check_flag_licensed(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.blog.posts.check-content'), [
            'title' => 'Deck stain guide',
            'slug' => 'deck-stain-guide',
            'html_content' => '<h2></h2><p>Hire our licensed crew for stain work.</p>',
            'featured_image' => false,
            'category_ids' => [],
        ]);

        $response->assertOk();
        $codes = collect($response->json('issues'))->pluck('code')->all();
        $this->assertContains('licensed', $codes);
        $this->assertContains('empty_heading', $codes);
        $this->assertFalse((bool) $response->json('ready'));
    }

    public function test_new_fields_are_saved_on_update(): void
    {
        $this->actingAsAdmin();
        $post = $this->makePost(['title' => 'Save fields', 'slug' => 'save-fields']);

        $this->get(route('admin.blog.posts.edit', $post))
            ->assertOk()
            ->assertSee('Hide from search engines (noindex)', false)
            ->assertDontSee('stored for Phase 3', false);

        $this->put(route('admin.blog.posts.update', $post), $this->payload($post, [
            'seo_title' => 'SEO for decks',
            'featured_image_alt' => 'Cedar deck in Ottawa',
            'cta_service' => 'decks',
            'noindex' => '1',
            'meta_description' => 'A short meta about decks.',
        ]))->assertRedirect();

        $post->refresh();
        $this->assertSame('SEO for decks', $post->seo_title);
        $this->assertSame('Cedar deck in Ottawa', $post->featured_image_alt);
        $this->assertSame('decks', $post->cta_service);
        $this->assertTrue((bool) $post->noindex);
        $this->assertSame('A short meta about decks.', $post->meta_description);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePost(array $overrides = []): BlogPost
    {
        return BlogPost::query()->create(array_merge([
            'title' => 'Phase 2 test post',
            'slug' => 'phase-2-test-post-'.uniqid(),
            'excerpt' => 'A short excerpt.',
            'html_content' => '<p>Body copy for the test post.</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'author_name' => 'Jonas Zanini',
            'views_count' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(BlogPost $post, array $overrides = []): array
    {
        return array_merge([
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'html_content' => $post->html_content,
            'meta_description' => $post->meta_description,
            'seo_title' => $post->seo_title,
            'featured_image_alt' => $post->featured_image_alt,
            'cta_service' => $post->cta_service,
            'noindex' => $post->noindex ? '1' : '0',
            'status' => $post->status,
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            'author_name' => $post->author_name ?: 'Jonas Zanini',
            'tag_names' => '',
        ], $overrides);
    }
}
