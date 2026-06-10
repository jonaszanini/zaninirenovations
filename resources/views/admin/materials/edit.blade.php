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

      <div class="mat-edit-footer">
        <div class="admin-form-actions mat-edit-actions">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-save"></i> Save
          </button>
          @if ($material->hasProductUrl())
            <button type="submit" form="mat-sync-form" class="admin-btn admin-btn--ghost">
              <i class="fa fa-refresh"></i> Sync price now
            </button>
          @endif
          <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
        </div>
        @if ($material->hasProductUrl())
          <p class="admin-form-help mat-sync-form-help">
            Fetches the current name, price, and image from the Home Depot store API. Save any URL changes before syncing.
          </p>
        @endif
      </div>
    </form>

    @if ($material->hasProductUrl())
      <form id="mat-sync-form" action="{{ route('admin.materials.sync-price', $material) }}" method="post" class="mat-sync-form">
        @csrf
      </form>
    @endif

    <div class="mat-delete-form">
      <p class="mat-delete-form__label">Danger zone</p>
      <form action="{{ route('admin.materials.destroy', $material) }}" method="post"
            onsubmit="return confirm('Delete this material permanently?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
          <i class="fa fa-trash"></i> Delete material
        </button>
      </form>
    </div>
  </div>
@endsection
