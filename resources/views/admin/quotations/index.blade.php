@extends('admin.layouts.app')

@section('heading', 'Quotations')

@section('content')
  @php
    $pq = ($hubProject ?? null) ? ['project' => $hubProject->id] : [];
  @endphp

  @if (!empty($hubProjectError))
    <div class="admin-flash admin-flash--danger" style="margin-bottom:16px;">{{ $hubProjectError }}</div>
  @endif

  @if ($hubProject ?? null)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      Creating quotations for project <strong>{{ $hubProject->name }}</strong> ({{ $hubProject->client_name }}).
      The first saved quotation will attach to this project automatically.
    </div>
  @else
    <div class="admin-card fq-hub-intro">
      <h3 style="margin:0 0 8px;">Standalone quotations</h3>
      <p class="admin-form-help" style="margin:0;">
        Calculators produce saved quotes that you can tie to a project when you're ready —
        either from here or via the project's Quotation tab.
      </p>
    </div>
  @endif

  <div class="fq-hub-grid">
    <a href="{{ route('admin.quotations.fence.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-icon" aria-hidden="true">
        <i class="fa fa-th-large"></i>
      </div>
      <div class="fq-hub-card-body">
        <h4>Fence</h4>
        <p>
          Calculate materials and labour for 6' and 8' pressure-treated wood fences
          against the Ontario Building Code structural rules.
        </p>
        <span class="fq-hub-card-cta">Open calculator <i class="fa fa-arrow-right"></i></span>
      </div>
    </a>

    <a href="{{ route('admin.quotations.standard.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-icon" aria-hidden="true">
        <i class="fa fa-table"></i>
      </div>
      <div class="fq-hub-card-body">
        <h4>Standard</h4>
        <p>
          Build a quote from material line items (with optional 13% HST) plus hourly labour.
          Output becomes the client-facing document on the project.
        </p>
        <span class="fq-hub-card-cta">Open standard quotation <i class="fa fa-arrow-right"></i></span>
      </div>
    </a>

    <a href="{{ route('admin.quotations.paving.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-icon" aria-hidden="true">
        <i class="fa fa-th"></i>
      </div>
      <div class="fq-hub-card-body">
        <h4>Paving stones</h4>
        <p>
          Ottawa 2026 paving calculator: materials, fixed costs, scaled labour, pricing-engine
          suggestions, optional marble inlay — with an editable price database.
        </p>
        <span class="fq-hub-card-cta">Open paving calculator <i class="fa fa-arrow-right"></i></span>
      </div>
    </a>
  </div>
@endsection
