@extends('admin.layouts.app')

@php
  $isEdit = $post->exists;
  $formAction = $isEdit ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store');
  $publishedValue = old('published_at', $post->published_at?->format('Y-m-d\TH:i'));
@endphp

@section('heading', $isEdit ? 'Edit post' : 'New post')

@push('head')
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" crossorigin="anonymous">
@endpush

@section('content')
  <div class="admin-card admin-card--wide">
    <form action="{{ $formAction }}" method="post" enctype="multipart/form-data" id="blog-post-form">
      @csrf
      @if ($isEdit) @method('PUT') @endif

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="bp-title">Title <span class="cp-required">*</span></label>
          <input type="text" id="bp-title" name="title" value="{{ old('title', $post->title) }}" required maxlength="255" class="admin-input">
          @error('title') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="bp-slug">Slug</label>
          <input type="text" id="bp-slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="255" class="admin-input" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="auto from title if empty">
          @error('slug') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="bp-featured">Featured / header image</label>
        <input type="file" id="bp-featured" name="featured_image" accept="image/jpeg,image/png,image/webp" class="admin-input">
        <p class="admin-form-help">JPEG, PNG, or WebP — stored as WebP at {{ (int) config('webp.quality', 75) }}% quality, max {{ (int) config('webp.max_edge_px', 1280) }}px, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB. Inline images in the editor use the same compression.</p>
        <div class="blog-admin-featured-preview" id="bp-featured-preview-wrap" @if(!$post->featured_image_path) hidden @endif>
          <img src="{{ $post->featuredImageUrl() }}" alt="" id="bp-featured-preview-img">
        </div>
        <label class="admin-checkbox" id="bp-remove-featured-wrap" style="margin-top:8px;@if(!$post->featured_image_path) display:none; @endif">
          <input type="hidden" name="remove_featured_image" value="0" id="bp-remove-featured-hidden">
          <input type="checkbox" class="admin-checkbox__input" name="remove_featured_image" value="1" id="bp-remove-featured">
          <span class="admin-checkbox__mark" aria-hidden="true"></span>
          <span class="admin-checkbox__text">Remove featured image</span>
        </label>
        <input type="hidden" name="pending_featured_image_path" id="bp-pending-featured-path" value="">
        <div class="blog-admin-crop-wrap" id="bp-crop-wrap" hidden>
          <img id="bp-crop-image" alt="Crop preview">
        </div>
        <input type="hidden" name="featured_crop_applies" id="bp-featured-crop-applies" value="0">
        <input type="hidden" name="crop_x" id="bp-crop-x">
        <input type="hidden" name="crop_y" id="bp-crop-y">
        <input type="hidden" name="crop_width" id="bp-crop-w">
        <input type="hidden" name="crop_height" id="bp-crop-h">
        <p class="admin-form-help">Upload a new image to replace. Optional crop applies on save (16:9 suggested).</p>
        @error('featured_image') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="bp-ai-image-prompt">AI image prompt <small class="admin-form-help cp-inline-help">(saved for future featured image generation)</small></label>
        <textarea id="bp-ai-image-prompt" name="ai_image_prompt" rows="3" maxlength="2000" class="admin-input" placeholder="Filled by Complete metadata with Gemini, or enter manually">{{ old('ai_image_prompt', $post->ai_image_prompt) }}</textarea>
        <p class="admin-form-help">Used by Gemini to generate the 16:9 featured header image when you run Complete metadata with Gemini.</p>
      </div>

      <div class="admin-form-group">
        <label for="bp-editor">Content</label>
        <textarea id="bp-editor" name="html_content" rows="24">{{ old('html_content', $post->html_content) }}</textarea>
        @error('html_content') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="blog-admin-ai-metadata-wrap">
        <button type="button"
                class="blog-admin-gemini-btn"
                id="bp-ai-metadata-btn"
                data-ai-url="{{ route('admin.blog.posts.ai-metadata') }}"
                data-post-id="{{ (int) ($post->id ?? 0) }}">
          <span class="blog-admin-gemini-btn__logo" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2L13.9 8.3L20 10.2L13.9 12.1L12 18.4L10.1 12.1L4 10.2L10.1 8.3L12 2Z" fill="url(#bp-gemini-grad-a)"/>
              <path d="M19 14L19.8 16.6L22.4 17.4L19.8 18.2L19 20.8L18.2 18.2L15.6 17.4L18.2 16.6L19 14Z" fill="url(#bp-gemini-grad-b)"/>
              <path d="M6 15L6.7 17.1L8.8 17.8L6.7 18.5L6 20.6L5.3 18.5L3.2 17.8L5.3 17.1L6 15Z" fill="url(#bp-gemini-grad-c)"/>
              <defs>
                <linearGradient id="bp-gemini-grad-a" x1="4" y1="2" x2="20" y2="18" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#4285F4"/><stop offset="1" stop-color="#9B72CB"/>
                </linearGradient>
                <linearGradient id="bp-gemini-grad-b" x1="15" y1="14" x2="23" y2="21" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#1BA1E3"/><stop offset="1" stop-color="#4285F4"/>
                </linearGradient>
                <linearGradient id="bp-gemini-grad-c" x1="3" y1="15" x2="9" y2="21" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#9B72CB"/><stop offset="1" stop-color="#D96570"/>
                </linearGradient>
              </defs>
            </svg>
          </span>
          <span class="blog-admin-gemini-btn__text">Complete metadata with Gemini</span>
        </button>
        <span id="bp-ai-metadata-status" class="blog-admin-gemini-status" aria-live="polite"></span>
        <p class="admin-form-help" style="margin:0.35rem 0 0;">Uses title and content to fill metadata, generate a 16:9 featured image (WebP, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB), and preview it above.</p>
      </div>

      <div class="admin-form-group">
        <label for="bp-excerpt">Excerpt <small class="admin-form-help cp-inline-help">(optional — auto-generated from content if empty)</small></label>
        <textarea id="bp-excerpt" name="excerpt" rows="2" maxlength="500" class="admin-input">{{ old('excerpt', $post->excerpt) }}</textarea>
      </div>

      <div class="admin-form-group">
        <label>Categories</label>
        <div class="admin-form-grid-2">
          @foreach ($categories as $cat)
            <label class="admin-checkbox">
              <input type="checkbox" class="admin-checkbox__input" name="category_ids[]" value="{{ $cat->id }}"
                     @checked(in_array($cat->id, old('category_ids', $selectedCategoryIds), true))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">{{ $cat->name }}</span>
            </label>
          @endforeach
        </div>
        @if ($categories->isEmpty())
          <p class="admin-form-help"><a href="{{ route('admin.blog.categories.create') }}">Create a category</a> first.</p>
        @endif
      </div>

      <div class="admin-form-group">
        <label for="bp-tags">Tags</label>
        <input type="text" id="bp-tags" name="tag_names" value="{{ old('tag_names', $tagNames) }}" class="admin-input" placeholder="renovation, flooring, ottawa">
        <p class="admin-form-help">Comma-separated. New tags are created automatically.</p>
      </div>

      <div class="admin-form-group">
        <label for="bp-meta">Meta description (SEO)</label>
        <textarea id="bp-meta" name="meta_description" rows="2" maxlength="320" class="admin-input">{{ old('meta_description', $post->meta_description) }}</textarea>
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="bp-status">Status</label>
          <select id="bp-status" name="status" class="admin-input">
            <option value="draft" @selected(old('status', $post->status) === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $post->status) === 'published')>Published</option>
          </select>
        </div>
        <div class="admin-form-group">
          <label for="bp-published">Publish date</label>
          <input type="datetime-local" id="bp-published" name="published_at" value="{{ $publishedValue }}" class="admin-input">
          <p class="admin-form-help">Leave empty when publishing to use now. Future date schedules the post.</p>
        </div>
      </div>

      <div class="admin-form-group">
        <label for="bp-author">Author</label>
        <input type="text" id="bp-author" name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="120" class="admin-input">
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary"><i class="fa fa-save"></i> Save post</button>
        <a href="{{ route('admin.blog.posts.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
        @if ($isEdit && $post->isPublishedNow())
          <a href="{{ $post->publicUrl() }}" class="admin-btn admin-btn--ghost" target="_blank" rel="noopener">View on site</a>
        @endif
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" crossorigin="anonymous"></script>
  <script>
    (function () {
      var slugManual = {{ json_encode((bool) old('slug', $post->slug)) }};
      var titleEl = document.getElementById('bp-title');
      var slugEl = document.getElementById('bp-slug');

      function slugify(t) {
        return (t || '').toLowerCase().trim()
          .replace(/[^a-z0-9\s-]/g, '')
          .replace(/\s+/g, '-')
          .replace(/-+/g, '-')
          .replace(/^-|-$/g, '');
      }

      if (titleEl && slugEl) {
        titleEl.addEventListener('input', function () {
          if (!slugManual || slugEl.dataset.auto === '1') {
            slugEl.value = slugify(titleEl.value);
            slugEl.dataset.auto = '1';
          }
        });
        slugEl.addEventListener('input', function () {
          slugEl.dataset.auto = '0';
        });
      }

      var bpWordWrapKey = 'bp-editor-word-wrap';

      function bpApplyWordWrap(editor, enabled) {
        if (!editor.getBody) return;
        var body = editor.getBody();
        if (!body) return;

        body.classList.toggle('bp-wrap-on', !!enabled);
        body.classList.toggle('bp-wrap-off', !enabled);

        var doc = editor.getDoc();
        if (!doc || !doc.head) return;
        var style = doc.getElementById('bp-word-wrap-style');
        if (!style) {
          style = doc.createElement('style');
          style.id = 'bp-word-wrap-style';
          doc.head.appendChild(style);
        }
        style.textContent = [
          'body.bp-wrap-on, body.bp-wrap-on p, body.bp-wrap-on div, body.bp-wrap-on li,',
          'body.bp-wrap-on td, body.bp-wrap-on th, body.bp-wrap-on span {',
          '  overflow-wrap: anywhere !important;',
          '  word-break: break-word !important;',
          '  white-space: normal !important;',
          '}',
          'body.bp-wrap-on pre, body.bp-wrap-on code {',
          '  white-space: pre-wrap !important;',
          '  overflow-wrap: anywhere !important;',
          '  word-break: break-word !important;',
          '}',
          'body.bp-wrap-off pre, body.bp-wrap-off code {',
          '  white-space: pre !important;',
          '  overflow-wrap: normal !important;',
          '  word-break: normal !important;',
          '}',
        ].join('\n');

        try {
          localStorage.setItem(bpWordWrapKey, enabled ? '1' : '0');
        } catch (e) { /* ignore */ }
      }

      tinymce.init({
        selector: '#bp-editor',
        menubar: 'edit insert view format table tools',
        plugins: 'advlist autolink lists link image table code preview searchreplace fullscreen visualblocks wordcount paste help',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | link table image | bpwordwrap | removeformat code preview fullscreen help',
        height: 560,
        branding: false,
        promotion: false,
        paste_data_images: true,
        automatic_uploads: true,
        images_upload_handler: function (blobInfo) {
          return new Promise(function (resolve, reject) {
            var fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            fd.append('post_id', '{{ $post->id ?? 0 }}');
            fetch(@json(route('admin.blog.posts.upload-image')), {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: fd
            })
              .then(function (r) { return r.json(); })
              .then(function (data) {
                if (data.location) resolve(data.location);
                else reject('Upload failed');
              })
              .catch(function () { reject('Upload failed'); });
          });
        },
        content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.55; overflow-wrap: break-word; word-break: break-word; } pre, code { white-space: pre-wrap; overflow-wrap: anywhere; } img { max-width: 100%; height: auto; }',
        setup: function (editor) {
          var wrapEnabled = true;
          try {
            wrapEnabled = localStorage.getItem(bpWordWrapKey) !== '0';
          } catch (e) { /* ignore */ }

          editor.ui.registry.addIcon('bpwordwrap', '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M4 19h6v-2H4v2zm16-14H4v2h16V5zm-3 6H4v2h13.25c1.1 0 2 .9 2 2s-.9 2-2 2H15v-2l-3 3 3 3v-2h2c2.21 0 4-1.79 4-4s-1.79-4-4-4z"/></svg>');

          editor.ui.registry.addToggleButton('bpwordwrap', {
            icon: 'bpwordwrap',
            tooltip: 'Word wrap',
            onAction: function (api) {
              wrapEnabled = !wrapEnabled;
              api.setActive(wrapEnabled);
              bpApplyWordWrap(editor, wrapEnabled);
            },
            onSetup: function (api) {
              api.setActive(wrapEnabled);
              return function () {};
            },
          });

          editor.on('init', function () {
            bpApplyWordWrap(editor, wrapEnabled);
          });

          editor.on('SetContent Undo Redo', function () {
            bpApplyWordWrap(editor, wrapEnabled);
          });

          editor.on('change keyup undo redo', function () { editor.save(); });
        }
      });

      var fileInput = document.getElementById('bp-featured');
      var cropWrap = document.getElementById('bp-crop-wrap');
      var cropImg = document.getElementById('bp-crop-image');
      var cropper = null;

      var cropAppliesInput = document.getElementById('bp-featured-crop-applies');
      var pendingFeaturedInput = document.getElementById('bp-pending-featured-path');

      function clearFeaturedCropFields() {
        if (cropAppliesInput) cropAppliesInput.value = '0';
        ['bp-crop-x', 'bp-crop-y', 'bp-crop-w', 'bp-crop-h'].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = '';
        });
      }

      if (fileInput && cropImg) {
        fileInput.addEventListener('change', function () {
          var file = fileInput.files && fileInput.files[0];
          if (!file) {
            clearFeaturedCropFields();
            return;
          }
          if (pendingFeaturedInput) pendingFeaturedInput.value = '';
          var url = URL.createObjectURL(file);
          cropImg.src = url;
          cropWrap.hidden = false;
          if (cropper) cropper.destroy();
          cropper = new Cropper(cropImg, { aspectRatio: 16 / 9, viewMode: 1 });
        });
      }

      document.getElementById('blog-post-form').addEventListener('submit', function () {
        clearFeaturedCropFields();
        var hasNewFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (cropper && hasNewFile) {
          var d = cropper.getData(true);
          document.getElementById('bp-crop-x').value = Math.round(d.x);
          document.getElementById('bp-crop-y').value = Math.round(d.y);
          document.getElementById('bp-crop-w').value = Math.round(d.width);
          document.getElementById('bp-crop-h').value = Math.round(d.height);
          if (cropAppliesInput) cropAppliesInput.value = '1';
        }
        if (typeof tinymce !== 'undefined') tinymce.triggerSave();
      });

      var aiBtn = document.getElementById('bp-ai-metadata-btn');
      var aiStatus = document.getElementById('bp-ai-metadata-status');
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

      function setAiStatus(msg, isError) {
        if (!aiStatus) return;
        aiStatus.textContent = msg || '';
        aiStatus.classList.toggle('is-error', !!isError);
      }

      function metadataFieldsHaveValues() {
        var excerpt = document.getElementById('bp-excerpt');
        var meta = document.getElementById('bp-meta');
        var tags = document.getElementById('bp-tags');
        var imagePrompt = document.getElementById('bp-ai-image-prompt');
        var hasCats = document.querySelectorAll('input[name="category_ids[]"]:checked').length > 0;
        return (excerpt && excerpt.value.trim() !== '')
          || (meta && meta.value.trim() !== '')
          || (tags && tags.value.trim() !== '')
          || (imagePrompt && imagePrompt.value.trim() !== '')
          || hasCats;
      }

      function applyFeaturedPreview(data) {
        var wrap = document.getElementById('bp-featured-preview-wrap');
        var img = document.getElementById('bp-featured-preview-img');
        var removeWrap = document.getElementById('bp-remove-featured-wrap');
        var removeCb = document.getElementById('bp-remove-featured');
        var pending = document.getElementById('bp-pending-featured-path');
        var postId = parseInt(aiBtn.getAttribute('data-post-id') || '0', 10);

        if (!data.featured_image_url || !wrap || !img) {
          return;
        }

        var url = data.featured_image_url;
        if (url.indexOf('?') === -1) {
          url += '?v=' + Date.now();
        } else {
          url += '&v=' + Date.now();
        }
        img.src = url;
        wrap.hidden = false;
        if (removeWrap) removeWrap.style.display = '';
        if (removeCb) removeCb.checked = false;

        if (pending) {
          pending.value = (postId > 0) ? '' : (data.featured_image_path || '');
        }
      }

      function applyAiMetadata(data) {
        var excerpt = document.getElementById('bp-excerpt');
        var meta = document.getElementById('bp-meta');
        var tags = document.getElementById('bp-tags');
        var imagePrompt = document.getElementById('bp-ai-image-prompt');
        if (excerpt && data.excerpt) excerpt.value = data.excerpt;
        if (meta && data.meta_description) meta.value = data.meta_description;
        if (tags && data.tags) tags.value = data.tags;
        if (imagePrompt && data.image_prompt) imagePrompt.value = data.image_prompt;

        var ids = Array.isArray(data.category_ids) ? data.category_ids.map(String) : [];
        document.querySelectorAll('input[name="category_ids[]"]').forEach(function (cb) {
          cb.checked = ids.indexOf(String(cb.value)) !== -1;
        });

        applyFeaturedPreview(data);
      }

      if (aiBtn) {
        aiBtn.addEventListener('click', function () {
          var url = aiBtn.getAttribute('data-ai-url');
          var titleEl = document.getElementById('bp-title');
          var title = titleEl ? titleEl.value.trim() : '';
          var html = '';
          if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('bp-editor');
            if (ed) html = ed.getContent() || '';
          }
          if (!html) {
            var raw = document.getElementById('bp-editor');
            html = raw ? (raw.value || '') : '';
          }

          if (!title || !html.replace(/<[^>]*>/g, '').trim()) {
            window.alert('Enter a title and blog content before generating metadata.');
            return;
          }

          if (metadataFieldsHaveValues()) {
            if (!window.confirm('Replace excerpt, categories, tags, meta description, AI image prompt, and featured image with AI suggestions?')) {
              return;
            }
          }

          var postId = parseInt(aiBtn.getAttribute('data-post-id') || '0', 10);
          var payload = { title: title, html_content: html };
          if (postId > 0) payload.post_id = postId;

          aiBtn.disabled = true;
          setAiStatus('Generating metadata and featured image with Gemini…', false);

          fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
          }).then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                throw new Error((body && body.error) ? body.error : ('HTTP ' + res.status));
              }
              return body;
            });
          }).then(function (body) {
            applyAiMetadata(body);
            var msg = 'Metadata filled in. Review before saving.';
            if (body.featured_image_url) {
              msg = 'Metadata and featured image ready. Review before saving.';
            }
            if (body.image_generation_error) {
              msg = 'Metadata filled in, but featured image failed: ' + body.image_generation_error;
              setAiStatus(msg, true);
              window.setTimeout(function () { setAiStatus('', false); }, 12000);
              return;
            }
            setAiStatus(msg, false);
            window.setTimeout(function () { setAiStatus('', false); }, 8000);
          }).catch(function (err) {
            setAiStatus(err && err.message ? err.message : 'Could not generate metadata.', true);
          }).finally(function () {
            aiBtn.disabled = false;
          });
        });
      }
    })();
  </script>
@endpush
