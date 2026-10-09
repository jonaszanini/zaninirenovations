@extends('admin.layouts.app')

@section('heading', 'Cut plan incomplete')

@section('content')
  <div class="admin-card">
    <div class="admin-flash admin-flash--error" role="alert">
      <strong>Parts do not fit on any sheet</strong>
      <p>{{ $message }}</p>
    </div>
    <p>
      <a class="admin-btn admin-btn--ghost" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.quotations.millwork.index') }}">Back</a>
    </p>
  </div>
@endsection
