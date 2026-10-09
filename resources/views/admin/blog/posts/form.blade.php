@extends('admin.layouts.app')

@php
  $isEdit = $post->exists;
  $formAction = $isEdit ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store');
  $publishedValue = old('published_at', $post->published_at?->format('Y-m-d\TH:i'));
  $currentStatus = old('status', $post->status ?: 'draft');
  $slugLocked = $isEdit && $post->status === \App\Models\BlogPost::STATUS_PUBLISHED;
  $authorValue = old('author_name', $post->author_name ?: config('blog.author_name'));
  $authorOptions = $authorOptions ?? [];
  if ($authorValue && ! in_array($authorValue, $authorOptions, true)) {
    $authorOptions[] = $authorValue;
  }
  $ctaServices = $ctaServices ?? [];
  $tagList = array_values(array_filter(array_map('trim', explode(',', old('tag_names', $tagNames)))));
@endphp

@section('heading', $isEdit ? 'Edit post' : 'New post')

@push('head')
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" crossorigin="anonymous">
@endpush

@section('content')
  <form action="{{ $formAction }}"
        method="post"
        enctype="multipart/form-data"
        id="blog-post-form"
        class="bp-form"
        data-is-edit="{{ $isEdit ? '1' : '0' }}"
        data-post-id="{{ (int) ($post->id ?? 0) }}"
        data-published="{{ $post->isPublishedNow() ? '1' : '0' }}"
        data-slug-locked="{{ $slugLocked ? '1' : '0' }}"
        data-autosave-url="{{ $isEdit ? route('admin.blog.posts.autosave.update', $post) : route('admin.blog.posts.autosave') }}"
        data-preview-url="{{ $isEdit ? route('admin.blog.posts.preview', $post) : '' }}"
        data-suggest-url="{{ route('admin.blog.posts.ai-suggest') }}"
        data-image-url="{{ route('admin.blog.posts.ai-image') }}"
        data-check-url="{{ route('admin.blog.posts.check-content') }}"
        data-upload-url="{{ route('admin.blog.posts.upload-image') }}"
        data-site-host="zaninirenovations.ca">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    <input type="hidden" name="intent" id="bp-intent" value="save">

    <div class="bp-layout">
      <div class="bp-main">
        <div class="bp-block bp-block--title admin-card">
          <div class="admin-form-group bp-field">
            <div class="bp-label-row">
              <label for="bp-title">Title <span class="cp-required">*</span></label>
              <span class="admin-char-count" id="bp-title-count" aria-live="polite"></span>
            </div>
            <input class="admin-input" type="text" id="bp-title" name="title" value="{{ old('title', $post->title) }}" required maxlength="255" autocomplete="off">
            @error('title') <p class="admin-form-error" data-bp-error="title">{{ $message }}</p> @enderror
            <p class="admin-form-error" id="bp-title-inline" hidden></p>
          </div>
          <div class="bp-slug-row">
            <div class="admin-form-group bp-field bp-field--slug">
              <div class="bp-label-row">
                <label for="bp-slug">Slug</label>
                @if ($slugLocked)
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-slug-unlock">Change</button>
                @endif
              </div>
              <input class="admin-input" type="text" id="bp-slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="255" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="auto from title" @if($slugLocked) readonly @endif>
              <p class="admin-form-help" id="bp-slug-hint">{{ $slugLocked ? 'Locked on published posts. Changing it 301s the old URL.' : 'Auto from title until you edit it.' }}</p>
              @error('slug') <p class="admin-form-error" data-bp-error="slug">{{ $message }}</p> @enderror
            </div>
          </div>
        </div>

        <div class="bp-block bp-block--content admin-card">
          <div class="admin-form-group">
            <div class="bp-label-row">
              <label for="bp-editor">Content</label>
              <span class="admin-char-count" id="bp-word-count" aria-live="polite"></span>
            </div>
            <textarea class="admin-input" id="bp-editor" name="html_content" rows="16">{{ old('html_content', $post->html_content) }}</textarea>
            @error('html_content') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="bp-inline-alts" id="bp-inline-alts" hidden></p>
          </div>
        </div>

        <div class="bp-block bp-block--ai admin-card">
          <div class="blog-admin-ai-metadata-wrap">
            @component('admin.partials.ai_action_group', [
              'instructionKey' => \App\Models\AiInstruction::KEY_BLOG_POST,
              'instructionLabel' => 'Edit blog post metadata prompt',
              'showHistory' => true,
              'historyKey' => 'blog-post-'.((int) ($post->id ?? 0)),
            ])
              <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="bp-ai-suggest-btn">
                <i class="fa fa-magic" aria-hidden="true"></i> Suggest metadata
              </button>
              @slot('extra')
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-ai-image-btn">
                  <i class="fa fa-picture-o" aria-hidden="true"></i> Generate image
                </button>
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-check-btn">
                  Check content
                </button>
              @endslot
            @endcomponent
            <span id="bp-ai-metadata-status" class="blog-admin-gemini-status" aria-live="polite"></span>
          </div>
        </div>
      </div>

      <aside class="bp-side">
        <div class="bp-block bp-block--publish admin-card">
          <div class="bp-publish-grid">
            <div class="admin-form-group bp-field bp-field--status">
              <label for="bp-status">Status</label>
              <select id="bp-status" name="status" class="admin-input">
                <option value="draft" @selected($currentStatus === 'draft')>Draft</option>
                <option value="published" @selected($currentStatus === 'published')>Published</option>
              </select>
            </div>
            <div class="admin-form-group bp-field bp-field--date">
              <label for="bp-published" id="bp-published-label">{{ $post->isScheduled() ? 'Schedule date' : 'Publish date' }}</label>
              <input type="datetime-local" id="bp-published" name="published_at" value="{{ $publishedValue }}" class="admin-input">
            </div>
          </div>
          <div class="bp-form-actions" id="bp-form-actions">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="bp-btn-save" data-intent="save">Save draft</button>
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="bp-btn-publish" data-intent="publish">Publish</button>
            <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-btn-unpublish" data-intent="draft" hidden>Unpublish</button>
            @if ($isEdit)
              <a href="{{ route('admin.blog.posts.preview', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-preview-link" target="_blank" rel="noopener">Preview</a>
            @else
              <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-preview-link" target="_blank" rel="noopener" hidden>Preview</a>
            @endif
            @if ($isEdit && $post->isPublishedNow())
              <a href="{{ $post->publicUrl() }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener">View</a>
            @endif
            <a href="{{ route('admin.blog.posts.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" id="bp-cancel-link">Cancel</a>
          </div>
          <p class="admin-form-help" id="bp-save-status" aria-live="polite"></p>
        </div>

        <div class="bp-block bp-block--featured admin-card">
          <div class="bp-featured">
            <div class="blog-admin-featured-preview" id="bp-featured-preview-wrap" @if(!$post->featured_image_path) hidden @endif>
              <img src="{{ $post->featuredImageUrl() }}" alt="" id="bp-featured-preview-img">
            </div>
            <div class="bp-featured__fields">
              <div class="admin-form-group bp-field">
                <label for="bp-featured">Featured image</label>
                <input type="file" id="bp-featured" name="featured_image" accept="image/jpeg,image/png,image/webp,image/avif,.avif" class="admin-input">
              </div>
              <div class="admin-form-group bp-field">
                <label for="bp-featured-alt">Alt text</label>
                <input type="text" id="bp-featured-alt" name="featured_image_alt" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" maxlength="{{ \App\Models\BlogPost::FEATURED_ALT_MAX }}" class="admin-input" placeholder="Describe the photo">
              </div>
              <label class="admin-checkbox" id="bp-remove-featured-wrap" @if(!$post->featured_image_path) hidden @endif>
                <input type="hidden" name="remove_featured_image" value="0" id="bp-remove-featured-hidden">
                <input type="checkbox" class="admin-checkbox__input" name="remove_featured_image" value="1" id="bp-remove-featured">
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Remove</span>
              </label>
            </div>
          </div>
          <input type="hidden" name="pending_featured_image_path" id="bp-pending-featured-path" value="">
          <div class="blog-admin-crop-wrap" id="bp-crop-wrap" hidden>
            <img id="bp-crop-image" alt="Crop preview">
          </div>
          <input type="hidden" name="featured_crop_applies" id="bp-featured-crop-applies" value="0">
          <input type="hidden" name="crop_x" id="bp-crop-x">
          <input type="hidden" name="crop_y" id="bp-crop-y">
          <input type="hidden" name="crop_width" id="bp-crop-w">
          <input type="hidden" name="crop_height" id="bp-crop-h">
          <div class="admin-form-group bp-field">
            <label for="bp-ai-image-prompt">Image prompt</label>
            <textarea id="bp-ai-image-prompt" name="ai_image_prompt" rows="2" maxlength="2000" class="admin-input" placeholder="Used by Generate image">{{ old('ai_image_prompt', $post->ai_image_prompt) }}</textarea>
          </div>
          @error('featured_image') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="bp-block bp-block--seo admin-card">
          <div class="admin-form-group bp-field">
            <div class="bp-label-row">
              <label for="bp-seo-title">SEO title</label>
              <span class="admin-char-count" id="bp-seo-title-count"></span>
            </div>
            <input type="text" id="bp-seo-title" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="{{ \App\Models\BlogPost::SEO_TITLE_MAX }}" class="admin-input" placeholder="Search result title">
            @error('seo_title') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group bp-field">
            <div class="bp-label-row">
              <label for="bp-meta">Meta description</label>
              <span class="admin-char-count" id="bp-meta-count"></span>
            </div>
            <textarea id="bp-meta" name="meta_description" rows="2" maxlength="320" class="admin-input">{{ old('meta_description', $post->meta_description) }}</textarea>
            @error('meta_description') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="bp-snippet" id="bp-snippet" aria-label="Google snippet preview">
            <p class="bp-snippet__kicker">Google preview</p>
            <p class="bp-snippet__url">zaninirenovations.ca › blog › <span id="bp-snippet-slug">{{ old('slug', $post->slug) ?: 'slug' }}</span></p>
            <p class="bp-snippet__title" id="bp-snippet-title">{{ old('seo_title', $post->seo_title) ?: old('title', $post->title) ?: 'Post title' }}</p>
            <p class="bp-snippet__desc" id="bp-snippet-desc">{{ old('meta_description', $post->meta_description) ?: 'Meta description appears here.' }}</p>
          </div>
          <div class="admin-form-group bp-field">
            <div class="bp-label-row">
              <label for="bp-excerpt">Excerpt</label>
              <span class="admin-char-count" id="bp-excerpt-count"></span>
            </div>
            <textarea id="bp-excerpt" name="excerpt" rows="2" maxlength="{{ \App\Models\BlogPost::EXCERPT_MAX }}" class="admin-input">{{ old('excerpt', $post->excerpt) }}</textarea>
            @error('excerpt') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="bp-block bp-block--tax admin-card">
          <div class="admin-form-group bp-field">
            <label>Categories</label>
            <div class="bp-cat-list">
              @foreach ($categories as $cat)
                <label class="admin-checkbox">
                  <input type="checkbox" class="admin-checkbox__input" name="category_ids[]" value="{{ $cat->id }}"
                         @checked(in_array($cat->id, old('category_ids', $selectedCategoryIds), false) || in_array((string) $cat->id, old('category_ids', $selectedCategoryIds), true))>
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  <span class="admin-checkbox__text">{{ $cat->name }}</span>
                </label>
              @endforeach
            </div>
            @if ($categories->isEmpty())
              <p class="admin-form-help"><a href="{{ route('admin.blog.categories.create') }}">Create a category</a> first.</p>
            @endif
          </div>

          <div class="admin-form-group bp-field">
            <label for="bp-tag-input">Tags</label>
            <div class="bp-tag-chips" id="bp-tag-chips">
              @foreach ($tagList as $tagName)
                <span class="bp-chip" data-tag="{{ $tagName }}">{{ $tagName }} <button type="button" class="bp-chip__x" aria-label="Remove {{ $tagName }}">&times;</button></span>
              @endforeach
            </div>
            <input type="text" id="bp-tag-input" class="admin-input" placeholder="Type a tag and press Enter" autocomplete="off">
            <input type="hidden" id="bp-tags" name="tag_names" value="{{ old('tag_names', $tagNames) }}">
          </div>

          <div class="admin-form-group bp-field">
            <label for="bp-author">Author</label>
            <select id="bp-author" name="author_name" class="admin-input">
              @foreach ($authorOptions as $author)
                <option value="{{ $author }}" @selected($authorValue === $author)>{{ $author }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group bp-field">
            <label for="bp-cta">CTA service</label>
            <select id="bp-cta" name="cta_service" class="admin-input">
              <option value="">None</option>
              @foreach ($ctaServices as $ctaKey => $ctaLabel)
                <option value="{{ $ctaKey }}" @selected(old('cta_service', $post->cta_service) === $ctaKey)>{{ $ctaLabel }}</option>
              @endforeach
            </select>
          </div>

          <label class="admin-checkbox">
            <input type="hidden" name="noindex" value="0">
            <input type="checkbox" class="admin-checkbox__input" name="noindex" value="1" id="bp-noindex" @checked(old('noindex', $post->noindex))>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Hide from search engines (noindex)</span>
          </label>
        </div>

        <div class="bp-block bp-block--check admin-card" id="bp-checklist">
          <div class="bp-label-row">
            <h2 class="bp-card-title">Pre-publish checklist</h2>
            <span class="bp-check-ready" id="bp-check-ready"></span>
          </div>
          <ul class="bp-check-list" id="bp-check-list"></ul>
        </div>
      </aside>
    </div>
  </form>
@endsection

@include('admin.partials.ai_text_history_assets')

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" crossorigin="anonymous"></script>
  <script src="{{ asset('js/admin-blog-editor.js') }}?v={{ @filemtime(public_path('js/admin-blog-editor.js')) ?: time() }}"></script>
@endpush
