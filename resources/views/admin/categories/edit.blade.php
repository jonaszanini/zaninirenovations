@extends('admin.layouts.app')

@section('heading', 'Edit category')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.categories.update', $category) }}" method="post">
      @csrf
      @method('PUT')
      <div class="admin-form-group">
        <label for="name">Name</label>
        <input class="admin-input" type="text" id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="120">
        @error('name')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="slug">Slug</label>
        <input class="admin-input" type="text" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
        <p class="admin-form-help">Used internally. Change only if you know you need a new identifier.</p>
        @error('slug')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="sort_order">Sort order</label>
        <input class="admin-input" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" max="32767">
        @error('sort_order')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost">Back to categories</a>
      </div>
    </form>
  </div>
@endsection
