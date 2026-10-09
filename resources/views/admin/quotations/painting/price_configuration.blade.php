@extends('admin.layouts.app')

@section('heading', 'Painting price configuration')

@push('head')
  <style>
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .pt-paint-line-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 90px 80px auto;
      gap: 10px;
      align-items: end;
      margin-bottom: 10px;
    }
    @media (max-width: 700px) {
      .pt-paint-line-row { grid-template-columns: 1fr; }
    }
  </style>
@endpush

@section('content')
  @php $qparam = $qpid ? ['project' => $qpid] : []; @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.painting.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.painting.database.update', $qparam) }}" class="admin-card">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif
    @include('admin.partials.quotation_return_quote_hidden')

    @include('admin.quotations._shared_price_banner', ['consumer' => 'painting'])

    <h3 style="margin:0 0 12px;">This quote only — paint catalog</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">
      Wall labor per sq ft is shared. Coverage, paint SKUs, and the expendables fee stay here.
    </p>
    <input type="hidden" name="labor_per_sqf" value="{{ number_format(\App\Services\SharedWorkCatalog::flatRate('paint_walls', (float) $settings->labor_per_sqf), 4, '.', '') }}">

    <div class="admin-form-grid-4">
      <div class="admin-form-group">
        <label for="paint_per_gallon">Paint price per gallon (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" id="paint_per_gallon" name="paint_per_gallon"
               placeholder="0.00" value="{{ old('paint_per_gallon', number_format((float) $settings->paint_per_gallon, 2, '.', '')) }}" required data-cp-money-entry="direct">
        <p class="pq-hint">Premium paint e.g. Dulux Lifemaster</p>
      </div>
      <div class="admin-form-group">
        <label for="material_addon_fee">Material add-on fixed fee (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" id="material_addon_fee" name="material_addon_fee"
               placeholder="0.00" value="{{ old('material_addon_fee', number_format((float) $settings->material_addon_fee, 2, '.', '')) }}" required data-cp-money-entry="direct">
        <p class="pq-hint">Brushes, sleeves, masking, etc.</p>
      </div>
      <div class="admin-form-group">
        <label for="sqf_per_gallon">Sq ft floor coverage per gallon (2 top coats)</label>
        <input type="number" step="0.01" min="0.01" class="admin-input" id="sqf_per_gallon" name="sqf_per_gallon"
               value="{{ old('sqf_per_gallon', $settings->sqf_per_gallon) }}" required>
        <p class="pq-hint">Default 70 sq ft per gallon</p>
      </div>
    </div>

    <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">

    <h3 style="margin:0 0 12px;">Paint brands / lines</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">Shown in the quotation form dropdown. Pricing uses the per-gallon rate above.</p>

    @foreach ($paintLines as $i => $line)
      <div class="pt-paint-line-row">
        <input type="hidden" name="paint_lines[{{ $i }}][id]" value="{{ $line->id }}">
        <div class="admin-form-group" style="margin:0;">
          <label>Name</label>
          <input type="text" class="admin-input" name="paint_lines[{{ $i }}][name]" value="{{ old('paint_lines.'.$i.'.name', $line->name) }}" required maxlength="160">
        </div>
        <div class="admin-form-group" style="margin:0;">
          <label>Sort</label>
          <input type="number" class="admin-input" name="paint_lines[{{ $i }}][sort_order]" value="{{ old('paint_lines.'.$i.'.sort_order', $line->sort_order) }}" min="0">
        </div>
        <div class="admin-form-group" style="margin:0;">
          @include('admin.partials.checkbox', [
              'name' => 'paint_lines['.$i.'][is_active]',
              'checked' => (bool) old('paint_lines.'.$i.'.is_active', $line->is_active),
              'label' => 'Active',
              'hiddenFallback' => true,
          ])
        </div>
      </div>
    @endforeach

    <div class="admin-form-group" style="margin-top:16px;">
      <label>Add paint line</label>
      <input type="text" class="admin-input" name="new_paint_lines[0][name]" maxlength="160" placeholder="e.g. Sherwin-Williams Emerald">
    </div>

    <div style="margin-top:20px;">
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </form>
@endsection
