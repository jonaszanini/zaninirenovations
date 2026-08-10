@extends('admin.layouts.app')

@section('heading', 'Edit material — '.$material->displayName())

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <nav class="mat-edit-nav" aria-label="Material navigation">
    @if ($previousMaterial)
      <a href="{{ route('admin.materials.edit', $previousMaterial) }}"
         class="admin-btn admin-btn--ghost mat-edit-nav__btn"
         title="Previous: {{ $previousMaterial->displayName() }}">
        <i class="fa fa-chevron-left" aria-hidden="true"></i>
        <span class="mat-edit-nav__btn-label">Previous</span>
      </a>
    @else
      <span class="admin-btn admin-btn--ghost mat-edit-nav__btn is-disabled" aria-disabled="true">
        <i class="fa fa-chevron-left" aria-hidden="true"></i>
        <span class="mat-edit-nav__btn-label">Previous</span>
      </span>
    @endif

    <p class="mat-edit-nav__position">
      @if ($materialTotal > 0 && $materialPosition > 0)
        {{ number_format($materialPosition) }} of {{ number_format($materialTotal) }}
      @else
        —
      @endif
    </p>

    @if ($nextMaterial)
      <a href="{{ route('admin.materials.edit', $nextMaterial) }}"
         class="admin-btn admin-btn--ghost mat-edit-nav__btn"
         title="Next: {{ $nextMaterial->displayName() }}">
        <span class="mat-edit-nav__btn-label">Next</span>
        <i class="fa fa-chevron-right" aria-hidden="true"></i>
      </a>
    @else
      <span class="admin-btn admin-btn--ghost mat-edit-nav__btn is-disabled" aria-disabled="true">
        <span class="mat-edit-nav__btn-label">Next</span>
        <i class="fa fa-chevron-right" aria-hidden="true"></i>
      </span>
    @endif
  </nav>

  <div class="admin-card mat-form-card">
    <form action="{{ route('admin.materials.update', $material) }}" method="post">
      @csrf
      @method('PUT')
      @include('admin.materials._form', ['material' => $material])

      <div class="mat-edit-footer">
        <div class="mat-edit-footer__main">
          <div class="admin-form-actions mat-edit-actions">
            <button type="submit" class="admin-btn admin-btn--primary">
              <i class="fa fa-save"></i> Save
            </button>
            @if ($material->isSerpApiSyncable())
              <button type="submit" form="mat-sync-form" class="admin-btn admin-btn--ghost">
                <i class="fa fa-refresh"></i> Sync price now
              </button>
            @endif
            <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
          </div>
        </div>
      </div>
    </form>

    @if ($material->isSerpApiSyncable())
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
