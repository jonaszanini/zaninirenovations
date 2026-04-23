@extends('admin.layouts.app')

@section('heading', 'Quotations')

@section('content')
  <div class="admin-card fq-hub-intro">
    <h3 style="margin:0 0 8px;">Standalone quotations</h3>
    <p class="admin-form-help" style="margin:0;">
      These calculators generate quotes that don't have to be tied to a client project.
      You can still link a saved estimate to an existing project later to add it as a
      line item on that project's Quotation.
    </p>
  </div>

  <div class="fq-hub-grid">
    {{-- Fence card — first, and the only tile wired up today. --}}
    <a href="{{ route('admin.quotations.fence.index') }}" class="fq-hub-card">
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

    {{-- Future quotation types plug in as sibling cards here. --}}
  </div>
@endsection
