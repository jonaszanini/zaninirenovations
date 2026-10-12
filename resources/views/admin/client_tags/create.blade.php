@extends('admin.layouts.app')

@section('heading', 'New client tag')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.client-tags.store') }}" method="post">
      @csrf
      @include('admin.partials.once_submit')
      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="name">Name</label>
          <input class="admin-input" type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="60" placeholder="e.g. VIP">
          @error('name')
            <div class="admin-form-error">{{ $message }}</div>
          @enderror
        </div>
        <div class="admin-form-group">
          <label for="color">Color</label>
          <input class="admin-input" type="color" id="color" name="color" value="{{ old('color', '#0284c7') }}" style="height:var(--admin-control-height);padding:4px;">
          @error('color')
            <div class="admin-form-error">{{ $message }}</div>
          @enderror
        </div>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>Save tag</button>
        <a href="{{ route('admin.client-tags.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
