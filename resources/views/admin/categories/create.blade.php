@extends('admin.layouts.app')

@section('heading', 'New category')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.categories.store') }}" method="post">
      @csrf
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="name">Name</label>
          <x-admin.info-tip label="Name">A URL-friendly slug is created automatically from the name (e.g. “Kitchen renovation” → kitchen-renovation). You can change it when editing.</x-admin.info-tip>
        </span>
        <input class="admin-input" type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Kitchen renovation">
        @error('name')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="sort_order">Sort order</label>
          <x-admin.info-tip label="Sort order">Lower numbers appear first in dropdowns. Default 0.</x-admin.info-tip>
        </span>
        <input class="admin-input" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="32767">
        @error('sort_order')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save category</button>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
