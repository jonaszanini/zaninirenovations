@extends('admin.layouts.app')

@section('heading', 'Edit client tag')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.client-tags.update', $tag) }}" method="post">
      @csrf
      @method('PUT')
      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="name">Name</label>
          <input class="admin-input" type="text" id="name" name="name" value="{{ old('name', $tag->name) }}" required maxlength="60">
          @error('name')
            <div class="admin-form-error">{{ $message }}</div>
          @enderror
        </div>
        <div class="admin-form-group">
          <label for="color">Color</label>
          <input class="admin-input" type="color" id="color" name="color" value="{{ old('color', $tag->color) }}" style="height:var(--admin-control-height);padding:4px;">
          @error('color')
            <div class="admin-form-error">{{ $message }}</div>
          @enderror
        </div>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save tag</button>
        <a href="{{ route('admin.client-tags.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
