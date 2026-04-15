@extends('admin.layouts.app')

@section('title', 'Edit project')
@section('heading', 'Edit project')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.projects.update', $project) }}" method="post" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="{{ old('title', $project->title) }}" required maxlength="255">
        @error('title')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required>{{ old('description', $project->description) }}</textarea>
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', $project->categories->pluck('id')->map(fn ($id) => (string) $id)->all());
      @endphp
      <div class="admin-form-group">
        <span class="admin-form-group-label">Categories</span>
        <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        <div class="admin-category-checkboxes">
          @foreach ($categories as $cat)
            <label class="admin-checkbox-tile">
              <input type="checkbox" name="project_category_ids[]" value="{{ $cat->id }}" @checked(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true))>
              <span>{{ $cat->name }}</span>
            </label>
          @endforeach
        </div>
        @error('project_category_ids')
          <div class="admin-error">{{ $message }}</div>
        @enderror
        @error('project_category_ids.*')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', optional($project->completed_on)->format('Y-m-d')) }}" required>
        <p class="admin-form-hint">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>

      @if ($project->photos->isNotEmpty())
        <div class="admin-form-group">
          <label>Photos — order &amp; remove</label>
          <p class="admin-form-hint">Drag by the grip icon to reorder. Use rotate to fix orientation (90°). Unchecked photos are saved in this order. Check “Remove” to delete from storage.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            @foreach ($project->photos as $photo)
              <li class="admin-photo-sortable-item" data-photo-id="{{ $photo->id }}">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  <img src="{{ $photo->url() }}" alt="">
                </div>
                <div class="admin-photo-sortable-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° counter-clockwise"
                    data-post-url="{{ route('admin.projects.photos.rotate', [$project, $photo]) }}"
                    data-direction="ccw">
                    <i class="fa fa-undo" aria-hidden="true"></i><span class="admin-sr-only"> Rotate left</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° clockwise"
                    data-post-url="{{ route('admin.projects.photos.rotate', [$project, $photo]) }}"
                    data-direction="cw">
                    <i class="fa fa-repeat" aria-hidden="true"></i><span class="admin-sr-only"> Rotate right</span>
                  </button>
                </div>
                <label class="admin-photo-remove-label">
                  <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}"> Remove
                </label>
              </li>
            @endforeach
          </ul>
          <div id="admin-photo-sequence-fields" aria-hidden="true"></div>
          @error('photo_sequence')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
      @endif

      @php
        $canAdd = 15 - $project->photos->count();
      @endphp
      @if ($canAdd > 0)
        <div class="admin-form-group">
          <label for="photos">Add photos</label>
          <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
          <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). New files are converted to WebP and scaled so the longest side is at most {{ (int) config('webp.max_edge_px', 1920) }}px; originals are not kept. Up to {{ $canAdd }} more (15 total).</p>
          @error('photos')
            <div class="admin-error">{{ $message }}</div>
          @enderror
          @error('photos.*')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
      @else
        <p class="admin-form-hint">Maximum of 15 photos reached. Remove one to add more.</p>
      @endif

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="{{ route('admin.projects.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>
  </div>
@endsection

@if ($project->photos->isNotEmpty())
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
      (function () {
        var csrf = @json(csrf_token());

        /* Rotate: full form POST (same as normal Laravel) — works even if Sortable CDN fails */
        document.querySelectorAll('.js-rotate-photo').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-post-url');
            var direction = btn.getAttribute('data-direction');
            if (!url || !direction) return;
            if (btn.disabled) return;
            btn.disabled = true;
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = url;
            f.style.display = 'none';
            var token = document.createElement('input');
            token.name = '_token';
            token.value = csrf;
            var dir = document.createElement('input');
            dir.name = 'direction';
            dir.value = direction;
            f.appendChild(token);
            f.appendChild(dir);
            document.body.appendChild(f);
            f.submit();
          });
        });

        var list = document.getElementById('admin-photo-sortable');
        var form = list && list.closest('form');
        var fields = document.getElementById('admin-photo-sequence-fields');
        if (!list || !form || !fields || typeof Sortable === 'undefined') return;

        Sortable.create(list, {
          animation: 180,
          handle: '.admin-photo-drag-handle',
          ghostClass: 'admin-photo-sortable-ghost',
          dragClass: 'admin-photo-sortable-drag',
          forceFallback: true,
        });

        form.addEventListener('submit', function () {
          fields.innerHTML = '';
          list.querySelectorAll('.admin-photo-sortable-item').forEach(function (li) {
            var cb = li.querySelector('input[name="delete_photos[]"]');
            if (cb && cb.checked) return;
            var id = li.getAttribute('data-photo-id');
            if (!id) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'photo_sequence[]';
            input.value = id;
            fields.appendChild(input);
          });
        });
      })();
    </script>
  @endpush
@endif
