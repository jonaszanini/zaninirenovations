@extends('admin.layouts.app')

@section('heading', 'New category')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.categories.store') }}" method="post">
      @csrf
      @include('admin.partials.once_submit')
      <div class="admin-form-group">
        <label for="name">Name</label>
        <input class="admin-input" type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Kitchen renovation">
        @error('name')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="sort_order">Sort order</label>
        <input class="admin-input" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="32767">
        <p class="admin-form-help">Lower numbers appear first in dropdowns. Default 0.</p>
        @error('sort_order')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <p class="admin-form-help" style="margin-top:-0.5rem;">A URL-friendly slug is created automatically from the name (e.g. “Kitchen renovation” → <code class="admin-code">kitchen-renovation</code>). You can change it when editing.</p>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>Save category</button>
        <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
