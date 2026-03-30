@extends('admin.layouts.app')

@section('title', 'New project')
@section('heading', 'New project')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.projects.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255">
        @error('title')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required>{{ old('description') }}</textarea>
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="project_category_id">Category</label>
        <select id="project_category_id" name="project_category_id" required>
          <option value="">Select category</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string) old('project_category_id') === (string) $cat->id)>{{ $cat->name }}</option>
          @endforeach
        </select>
        @error('project_category_id')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="photos">Photos</label>
        <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
        <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP, resized so the longest side is at most {{ (int) config('webp.max_edge_px', 1920) }}px, and recompressed for the web; originals are not kept. Display order matches the order you select (first = top).</p>
        @error('photos')
          <div class="admin-error">{{ $message }}</div>
        @enderror
        @error('photos.*')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save project</button>
        <a href="{{ route('admin.projects.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
