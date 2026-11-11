@extends('admin.layouts.app')

@section('heading', 'Flooring price configuration')

@push('head')
  <style>
    .pq-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 700px) { .pq-form-grid { grid-template-columns: 1fr; } }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .fl-type-row {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) 140px 70px 80px auto;
      gap: 10px;
      align-items: end;
      margin-bottom: 10px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eef2f7;
    }
    @media (max-width: 800px) { .fl-type-row { grid-template-columns: 1fr; } }
  </style>
@endpush

@section('content')
  @php $qparam = $qpid ? ['project' => $qpid] : []; @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.flooring.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.flooring.database.update', $qparam) }}" class="admin-card">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif
    @include('admin.partials.quotation_return_quote_hidden')

    @include('admin.quotations._shared_price_banner', ['consumer' => 'flooring'])

    <h3 style="margin:0 0 12px;">
      This quote only — add-on rates
      <x-admin.info-tip label="This quote only — add-on rates">Carpet removal and shoe moulding live in Shared prices. Closet doors and re-screw stay here.</x-admin.info-tip>
    </h3>
    <input type="hidden" name="remove_carpet_dispose_rate" value="{{ number_format($settings->removeCarpetRate(), 4, '.', '') }}">
    <input type="hidden" name="install_shoe_moulding_rate" value="{{ number_format($settings->shoeInstallRate(), 4, '.', '') }}">
    <input type="hidden" name="caulk_paint_shoe_moulding_rate" value="{{ number_format($settings->shoeCaulkPaintRate(), 4, '.', '') }}">

    <div class="admin-form-grid-4">
      <div class="admin-form-group">
        <label for="rescrew_subfloor_rate">Re-screw subfloor to joists (flat fee per project, CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" id="rescrew_subfloor_rate" name="rescrew_subfloor_rate"
               placeholder="0.00" value="{{ old('rescrew_subfloor_rate', number_format((float) $settings->rescrew_subfloor_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
      </div>
      <div class="admin-form-group">
        <label for="closet_door_reinstall_rate">Closet door remove &amp; reinstall (per door, CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" id="closet_door_reinstall_rate" name="closet_door_reinstall_rate"
               placeholder="0.00" value="{{ old('closet_door_reinstall_rate', number_format((float) $settings->closet_door_reinstall_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
      </div>
    </div>

    <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">

    <h3 style="margin:0 0 12px;">
      Flooring types
      <x-admin.info-tip label="Flooring types">Installation rate per sq ft for each flooring product type.</x-admin.info-tip>
    </h3>

    @foreach ($flooringTypes as $i => $type)
      <div class="fl-type-row">
        <input type="hidden" name="flooring_types[{{ $i }}][id]" value="{{ $type->id }}">
        <div class="admin-form-group" style="margin:0;">
          <label>Flooring type name</label>
          <input type="text" class="admin-input" name="flooring_types[{{ $i }}][name]" value="{{ old('flooring_types.'.$i.'.name', $type->name) }}" required maxlength="160">
        </div>
        <div class="admin-form-group" style="margin:0;">
          <label>Install $/sq ft</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                 class="admin-input cp-money-input" name="flooring_types[{{ $i }}][installation_price_per_sqft]"
                 value="{{ old('flooring_types.'.$i.'.installation_price_per_sqft', number_format((float) $type->installation_price_per_sqft, 4, '.', '')) }}" required data-cp-money-entry="direct">
        </div>
        <div class="admin-form-group" style="margin:0;">
          <label>Sort</label>
          <input type="number" class="admin-input" name="flooring_types[{{ $i }}][sort_order]" value="{{ old('flooring_types.'.$i.'.sort_order', $type->sort_order) }}" min="0">
        </div>
        <div class="admin-form-group" style="margin:0;">
          @include('admin.partials.checkbox', [
              'name' => 'flooring_types['.$i.'][is_active]',
              'checked' => (bool) old('flooring_types.'.$i.'.is_active', $type->is_active),
              'label' => 'Active',
              'hiddenFallback' => true,
          ])
        </div>
        <div class="admin-form-group" style="margin:0;">
          @include('admin.partials.checkbox', [
              'name' => 'delete_flooring_type_ids[]',
              'checkboxValue' => (string) $type->id,
              'checked' => false,
              'label' => 'Delete',
          ])
        </div>
      </div>
    @endforeach

    <div class="admin-form-group" style="margin-top:16px;">
      <label>Add flooring type</label>
      <div class="pq-form-grid">
        <input type="text" class="admin-input" name="new_flooring_types[0][name]" maxlength="160" placeholder="e.g. Engineered hardwood">
        <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
               class="admin-input cp-money-input" name="new_flooring_types[0][installation_price_per_sqft]" placeholder="Install $/sq ft" data-cp-money-entry="direct">
      </div>
    </div>

    <div style="margin-top:20px;">
      <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </form>
@endsection
