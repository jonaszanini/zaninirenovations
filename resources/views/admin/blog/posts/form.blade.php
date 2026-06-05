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
        @if ($post->featured_image_path)
          <div class="blog-admin-featured-preview">
            <img src="{{ $post->featuredImageUrl() }}" alt="" id="bp-featured-preview-img">
          </div>
          <label class="admin-checkbox" style="margin-top:8px;">
            <input type="hidden" name="remove_featured_image" value="0">
            <input type="checkbox" class="admin-checkbox__input" name="remove_featured_image" value="1">
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Remove featured image</span>
          </label>
        @endif
        <div class="blog-admin-crop-wrap" id="bp-crop-wrap" hidden>
          <img id="bp-crop-image" alt="Crop preview">
        </div>
        <input type="hidden" name="crop_x" id="bp-crop-x">
        <input type="hidden" name="crop_y" id="bp-crop-y">
        <input type="hidden" name="crop_width" id="bp-crop-w">
        <input type="hidden" name="crop_height" id="bp-crop-h">
        <p class="admin-form-help">Upload a new image to replace. Optional crop applies on save (16:9 suggested).</p>
        @error('featured_image') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="bp-editor">Content</label>
        <textarea id="bp-editor" name="html_content" rows="24">{{ old('html_content', $post->html_content) }}</textarea>
        @error('html_content') <p class="admin-form-error">{{ $message }}</p> @enderror
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

      tinymce.init({
        selector: '#bp-editor',
        menubar: 'edit insert view format table tools',
        plugins: 'advlist autolink lists link image table code preview searchreplace fullscreen visualblocks wordcount paste help',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist | link table image | removeformat code preview fullscreen help',
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
        content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.55; } img { max-width: 100%; height: auto; }',
        setup: function (editor) {
          editor.on('change keyup undo redo', function () { editor.save(); });
        }
      });

      var fileInput = document.getElementById('bp-featured');
      var cropWrap = document.getElementById('bp-crop-wrap');
      var cropImg = document.getElementById('bp-crop-image');
      var cropper = null;

      if (fileInput && cropImg) {
        fileInput.addEventListener('change', function () {
          var file = fileInput.files && fileInput.files[0];
          if (!file) return;
          var url = URL.createObjectURL(file);
          cropImg.src = url;
          cropWrap.hidden = false;
          if (cropper) cropper.destroy();
          cropper = new Cropper(cropImg, { aspectRatio: 16 / 9, viewMode: 1 });
        });
      }

      document.getElementById('blog-post-form').addEventListener('submit', function () {
        if (cropper) {
          var d = cropper.getData(true);
          document.getElementById('bp-crop-x').value = Math.round(d.x);
          document.getElementById('bp-crop-y').value = Math.round(d.y);
          document.getElementById('bp-crop-w').value = Math.round(d.width);
          document.getElementById('bp-crop-h').value = Math.round(d.height);
        }
        if (typeof tinymce !== 'undefined') tinymce.triggerSave();
      });
    })();
  </script>
@endpush
