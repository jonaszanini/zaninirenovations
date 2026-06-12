@extends('admin.layouts.app')

@section('heading', 'Edit vehicle')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-vehicles.css') }}?v={{ @filemtime(public_path('css/admin-vehicles.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.vehicles.update', $vehicle) }}" method="post">
      @csrf
      @method('PUT')
      @include('admin.vehicles._form', ['vehicle' => $vehicle])
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="{{ route('admin.vehicles.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-vehicles.js') }}?v={{ @filemtime(public_path('js/admin-vehicles.js')) ?: time() }}" defer></script>
@endpush
