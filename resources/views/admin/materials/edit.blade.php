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
          @if ($material->isSerpApiSyncable())
            <button type="submit" form="mat-sync-form" class="admin-btn admin-btn--ghost">
              <i class="fa fa-refresh"></i> Sync price now
            </button>
          @endif
          <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
        </div>
        @if ($material->isSerpApiSyncable())
          <p class="admin-form-help mat-sync-form-help">
            @if ($material->isAmazonSyncable())
              Fetches name, price, and image from Amazon Canada via SerpApi using the ASIN in Store SKU.
            @elseif (filled(config('materials.scraper.serpapi_key')))
              Fetches the current name, price, and image via SerpApi (store-localized). Save any URL changes before syncing.
            @else
              Fetches the current name, price, and image from the Home Depot store API. For bulk sync on your PC, run <code>php artisan materials:sync-prices</code>.
            @endif
          </p>
        @endif
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
