@extends('admin.layouts.app')

@section('heading', 'Stairs price configuration')

@push('head')
  <style>
    .pq-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (min-width: 900px) {
      .sq-db-global-grid.pq-form-grid { grid-template-columns: 1fr 1fr 1fr; }
    }
    @media (max-width: 700px) { .pq-form-grid { grid-template-columns: 1fr; } }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .sq-db-open-block {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 12px;
      background: #fff;
    }
    .sq-db-open-block__title {
      margin: 0 0 10px;
      font-weight: 600;
      font-size: 13px;
    }
  </style>
@endpush

@section('content')
  @php $qparam = $qpid ? ['project' => $qpid] : []; @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.stairs.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Stairs quotations
      </a>
    </div>
    <a href="{{ route('admin.quotations.stairs.create', $qparam) }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New stairs quotation
    </a>
  </div>

  <p class="admin-form-help" style="margin:0 0 16px;">
    These rates power the stairs calculator. After saving, return to the list to create or edit quotations.
  </p>

  @if (! ($publicStorageLinked ?? true))
    <div class="admin-flash admin-flash--warning" style="margin-bottom:16px;">
      Public storage is not linked. Images will not load in the browser until you run
      <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;">php artisan storage:link</code>
      from the Laravel project root (once per server). On shared hosting, ensure <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;">public/storage</code> points to <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px;">storage/app/public</code>.
    </div>
  @endif

  @include('admin.quotations.stairs._database_tab', ['hideDatabaseHeading' => true])
@endsection
