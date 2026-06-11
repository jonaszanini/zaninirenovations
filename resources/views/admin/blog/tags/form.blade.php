@extends('admin.layouts.app')

@section('heading', $tag->exists ? 'Edit tag' : 'New tag')

@section('content')
  <div class="admin-card admin-card--narrow">
    <form method="post" action="{{ $tag->exists ? route('admin.blog.tags.update', $tag) : route('admin.blog.tags.store') }}">
      @csrf
      @if ($tag->exists) @method('PUT') @endif
      <div class="admin-form-group">
        <label for="bt-name">Name</label>
        <input class="admin-input" type="text" id="bt-name" name="name" value="{{ old('name', $tag->name) }}" required maxlength="80" class="admin-input">
      </div>
      <div class="admin-form-group">
        <label for="bt-slug">Slug</label>
        <input class="admin-input" type="text" id="bt-slug" name="slug" value="{{ old('slug', $tag->slug) }}" maxlength="80" class="admin-input">
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save</button>
        <a href="{{ route('admin.blog.tags.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
