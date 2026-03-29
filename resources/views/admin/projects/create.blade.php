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
        <label for="category">Category</label>
        <select id="category" name="category" required>
          <option value="">Select category</option>
          @foreach ($categories as $slug => $label)
            <option value="{{ $slug }}" @selected(old('category') === $slug)>{{ $label }}</option>
          @endforeach
        </select>
        @error('category')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="photos">Photos (WebP)</label>
        <input type="file" id="photos" name="photos[]" accept=".webp,image/webp" multiple>
        <p class="admin-form-hint">Up to 10 files. Display order matches the order you select (first = top).</p>
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
