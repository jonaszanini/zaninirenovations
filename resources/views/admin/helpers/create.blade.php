@extends('admin.layouts.app')

@section('heading', 'New helper')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.helpers.store') }}" method="post">
      @csrf
      @if ($returnProjectId)
        <input type="hidden" name="return_project" value="{{ $returnProjectId }}">
      @endif
      @include('admin.helpers._form')
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save helper</button>
        <a href="{{ $returnProjectId ? route('admin.helpers.index', ['return_project' => $returnProjectId]) : route('admin.helpers.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
