@extends('admin.layouts.app')

@section('heading', 'Paving price configuration')

@push('head')
  @include('admin.quotations.paving._database_styles')
@endpush

@section('content')
  @php $qparam = $qpid ? ['project' => $qpid] : []; @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.paving.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
      <x-admin.info-tip>Manage stone types, fixed material rates, and the pricing engine used by the paving quotation calculator.</x-admin.info-tip>
    </div>
  </div>

  @include('admin.quotations.paving._database_tab')
@endsection
