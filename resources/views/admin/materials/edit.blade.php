@extends('admin.layouts.app')

@section('heading', 'Edit material — '.$material->name)

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-card admin-card--narrow">
    <form action="{{ route('admin.materials.update', $material) }}" method="post">
      @csrf
      @method('PUT')
      @include('admin.materials._form', ['material' => $material])
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save
        </button>
        <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>

    <form action="{{ route('admin.materials.destroy', $material) }}" method="post" class="mat-delete-form"
          onsubmit="return confirm('Delete this material permanently?');">
      @csrf
      @method('DELETE')
      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
        <i class="fa fa-trash"></i> Delete material
      </button>
    </form>
  </div>
@endsection
