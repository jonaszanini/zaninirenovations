@extends('admin.layouts.app')

@section('heading', 'Edit helper')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.helpers.update', $helper) }}" method="post">
      @csrf
      @method('PUT')
      @if ($returnProjectId)
        <input type="hidden" name="return_project" value="{{ $returnProjectId }}">
      @endif
      @include('admin.helpers._form', ['helper' => $helper])
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="{{ $returnProjectId ? route('admin.helpers.index', ['return_project' => $returnProjectId]) : route('admin.helpers.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
