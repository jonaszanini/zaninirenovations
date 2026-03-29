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
        <label for="category">Category</label>
        <select id="category" name="category" required>
          @foreach ($categories as $slug => $label)
            <option value="{{ $slug }}" @selected(old('category', $project->category) === $slug)>{{ $label }}</option>
          @endforeach
        </select>
        @error('category')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>

      @if ($project->photos->isNotEmpty())
        <div class="admin-form-group">
          <label>Photos — order &amp; remove</label>
          <p class="admin-form-hint">Set display order (0 = first). Check “Remove” to delete a photo from storage.</p>
          <div class="admin-photo-grid">
            @foreach ($project->photos as $photo)
              <div class="admin-photo-item">
                <img src="{{ $photo->url() }}" alt="">
                <div class="admin-form-group">
                  <label for="order-{{ $photo->id }}">Order</label>
                  <input type="number" id="order-{{ $photo->id }}" name="photo_order[{{ $photo->id }}]" min="0" max="99" value="{{ old('photo_order.'.$photo->id, $photo->sort_order) }}">
                </div>
                <label style="display:flex;align-items:center;gap:0.35rem;font-size:0.8rem;color:var(--admin-muted);cursor:pointer;">
                  <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}"> Remove
                </label>
              </div>
            @endforeach
          </div>
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
