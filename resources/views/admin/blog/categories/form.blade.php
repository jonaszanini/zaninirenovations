@extends('admin.layouts.app')

@section('heading', $category->exists ? 'Edit category' : 'New category')

@section('content')
  <div class="admin-card admin-card--narrow">
    <form method="post" action="{{ $category->exists ? route('admin.blog.categories.update', $category) : route('admin.blog.categories.store') }}">
      @csrf
      @if ($category->exists) @method('PUT') @endif
      <div class="admin-form-group">
        <label for="bc-name">Name</label>
        <input type="text" id="bc-name" name="name" value="{{ old('name', $category->name) }}" required maxlength="120" class="admin-input">
      </div>
      <div class="admin-form-group">
        <label for="bc-slug">Slug</label>
        <input type="text" id="bc-slug" name="slug" value="{{ old('slug', $category->slug) }}" maxlength="120" class="admin-input" placeholder="auto from name if empty on create">
      </div>
      <div class="admin-form-group">
        <label for="bc-desc">Description</label>
        <textarea id="bc-desc" name="description" rows="4" class="admin-input">{{ old('description', $category->description) }}</textarea>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save</button>
        <a href="{{ route('admin.blog.categories.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
