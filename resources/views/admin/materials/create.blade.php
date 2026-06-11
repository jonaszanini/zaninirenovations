@extends('admin.layouts.app')

@section('heading', 'New material')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-card mat-form-card">
    <form action="{{ route('admin.materials.store') }}" method="post">
      @csrf
      @include('admin.materials._form')
      <div class="mat-edit-footer">
        <div class="admin-form-actions mat-edit-actions">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-save"></i> Create material
          </button>
          <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
        </div>
      </div>
    </form>
  </div>
@endsection
