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
      <div class="admin-form-group">
        <label for="project_category_id">Category</label>
        <select id="project_category_id" name="project_category_id" required>
          @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string) old('project_category_id', $project->project_category_id) === (string) $cat->id)>{{ $cat->name }}</option>
          @endforeach
        </select>
        @error('project_category_id')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>

      @if ($project->photos->isNotEmpty())
        <div class="admin-form-group">
          <label>Photos — order &amp; remove</label>
          <p class="admin-form-hint">Drag by the grip icon to reorder. Unchecked photos are saved in this order. Check “Remove” to delete from storage.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            @foreach ($project->photos as $photo)
              <li class="admin-photo-sortable-item" data-photo-id="{{ $photo->id }}">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  <img src="{{ $photo->url() }}" alt="">
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
        $canAdd = 10 - $project->photos->count();
      @endphp
      @if ($canAdd > 0)
        <div class="admin-form-group">
          <label for="photos">Add photos (WebP)</label>
          <input type="file" id="photos" name="photos[]" accept=".webp,image/webp" multiple>
          <p class="admin-form-hint">You can add up to {{ $canAdd }} more (10 total).</p>
          @error('photos')
            <div class="admin-error">{{ $message }}</div>
          @enderror
          @error('photos.*')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
      @else
        <p class="admin-form-hint">Maximum of 10 photos reached. Remove one to add more.</p>
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
