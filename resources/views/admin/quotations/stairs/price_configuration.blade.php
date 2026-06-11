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
    .sq-db-material {
      margin-bottom: 16px;
      background: #f8fafc;
    }
    .sq-db-material-header {
      display: flex;
      gap: 16px;
      align-items: flex-start;
      margin-bottom: 16px;
    }
    .sq-db-material-photo {
      flex-shrink: 0;
    }
    .sq-db-material-photo img,
    .sq-db-material-photo__placeholder {
      display: block;
      width: 112px;
      height: 112px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      background: #fff;
    }
    .sq-db-material-photo__placeholder {
      box-sizing: border-box;
    }
    .sq-db-material-intro {
      flex: 1;
      min-width: 0;
    }
    .sq-db-material-title {
      margin: 0 0 4px;
      font-size: 1.05rem;
      font-weight: 700;
      line-height: 1.35;
      color: #0f172a;
    }
    .sq-db-material-intro .pq-hint {
      margin: 0 0 10px;
    }
    .sq-db-material-intro input[type="file"] {
      max-width: 100%;
    }
    .sq-db-rate-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 20px;
    }
    .sq-db-rate-grid .admin-form-group {
      margin-bottom: 0;
    }
    .sq-db-rate-label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      margin-bottom: 6px;
    }
    @media (max-width: 560px) {
      .sq-db-material-header {
        flex-direction: column;
      }
      .sq-db-rate-grid {
        grid-template-columns: 1fr;
      }
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

  @if ($errors->any())
    <div class="admin-flash admin-flash--danger">
      <strong>Could not save.</strong>
      <ul style="margin:8px 0 0 18px;padding:0;">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @include('admin.quotations.stairs._database_tab', ['hideDatabaseHeading' => true])
@endsection
