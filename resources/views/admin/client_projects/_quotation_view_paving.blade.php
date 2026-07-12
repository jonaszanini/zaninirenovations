{{-- Paving quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (PavingQuotation) --}}
@php
  use App\Services\PavingStoneEntryResolver;

  $inp  = is_array($sourceModel->inputs) ? $sourceModel->inputs : [];
  $calc = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
  $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

  $stoneEntries = PavingStoneEntryResolver::normalize($inp);
  $areaTotal = (float) ($inp['total_area_sq_ft'] ?? PavingStoneEntryResolver::totalArea($stoneEntries));

  // Rates & params
  $helperRate   = (float) ($inp['helper_hourly_rate'] ?? 0);
  $ownerRate    = (float) ($inp['owner_hourly_rate'] ?? 0);
  $miniDays     = (int)   ($inp['mini_skid_days'] ?? 0);
  $plateDays    = (int)   ($inp['plate_compactor_days'] ?? 0);
  $wastePct     = (float) ($inp['waste_factor'] ?? 0);
  if ($wastePct <= 1 && $wastePct > 0) $wastePct *= 100;
  $depthIn      = (float) ($inp['excavation_depth_in'] ?? 0);
  $profitPct    = (float) ($inp['profit_margin'] ?? 0);
  if ($profitPct <= 1 && $profitPct > 0) $profitPct *= 100;
  $hstRate      = (float) ($inp['hst_rate'] ?? 0.13);
  if ($hstRate <= 1 && $hstRate > 0) $hstRate *= 100;

  // Options
  $polymerSand    = filter_var($inp['add_polymeric_sand'] ?? true, FILTER_VALIDATE_BOOLEAN);
  $geotextile     = filter_var($inp['include_geotextile'] ?? true, FILTER_VALIDATE_BOOLEAN);
  $surfaceRemoval = filter_var($inp['include_old_surface_removal'] ?? false, FILTER_VALIDATE_BOOLEAN);

  // Manual override
  $manualOverride = ($inp['manual_price_override'] ?? null);

  // Calculated summary
  $finalTotal = (float) ($summary['final_quote_total'] ?? (float) $sourceModel->final_quote_total);
@endphp

{{-- Project inputs --}}
<div class="cp-vd-section">
  <div class="cp-vd-section__title">Project inputs</div>
  <div class="cp-vd-grid">
    @if ($sourceModel->client_name)
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Client name</span>
        <span class="cp-vd-field__value">{{ $sourceModel->client_name }}</span>
      </div>
    @endif

    <div class="cp-vd-field cp-vd-field--full">
      <span class="cp-vd-field__label">Total area</span>
      <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($areaTotal, 2), '0'), '.') }} sq ft</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Helper rate</span>
      <span class="cp-vd-field__value">${{ number_format($helperRate, 2) }}/hr</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Owner rate</span>
      <span class="cp-vd-field__value">${{ number_format($ownerRate, 2) }}/hr</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Mini skid steer</span>
      <span class="cp-vd-field__value">{{ $miniDays }} {{ Illuminate\Support\Str::plural('day', $miniDays) }}</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Plate compactor</span>
      <span class="cp-vd-field__value">{{ $plateDays }} {{ Illuminate\Support\Str::plural('day', $plateDays) }}</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Waste factor</span>
      <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($wastePct, 2), '0'), '.') }}%</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Excavation depth</span>
      <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($depthIn, 2), '0'), '.') }} in</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Profit margin</span>
      <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($profitPct, 2), '0'), '.') }}%</span>
    </div>

    <div class="cp-vd-field">
      <span class="cp-vd-field__label">HST rate</span>
      <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($hstRate, 2), '0'), '.') }}%</span>
    </div>
  </div>

  {{-- Options row --}}
  <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px 20px;">
    <span class="cp-vd-bool">
      <i class="fa {{ $polymerSand ? 'fa-check-square' : 'fa-square-o' }}" aria-hidden="true"></i> Polymeric sand
    </span>
    <span class="cp-vd-bool">
      <i class="fa {{ $geotextile ? 'fa-check-square' : 'fa-square-o' }}" aria-hidden="true"></i> Geotextile fabric
    </span>
    <span class="cp-vd-bool">
      <i class="fa {{ $surfaceRemoval ? 'fa-check-square' : 'fa-square-o' }}" aria-hidden="true"></i> Old surface removal
    </span>
  </div>

  @if ($manualOverride !== null && $manualOverride !== '')
    <div class="cp-vd-grid" style="margin-top:10px;">
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Manual price override</span>
        <span class="cp-vd-field__value">${{ number_format((float) $manualOverride, 2) }}</span>
      </div>
    </div>
  @endif
</div>

@if ($stoneEntries !== [])
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Stone types</div>
    <div class="cp-vd-grid">
      @foreach ($stoneEntries as $entry)
        @php
          $mode = $entry['input_mode'] ?? 'sqft';
          $entryArea = PavingStoneEntryResolver::resolvedArea($entry);
          $modeLabel = match ($mode) {
              'qty' => isset($entry['qty']) ? number_format((int) $entry['qty']).' stones' : 'Quantity',
              'dimensions' => (isset($entry['length_ft'], $entry['width_ft'])
                  ? rtrim(rtrim(number_format((float) $entry['length_ft'], 2), '0'), '.')
                    .' × '
                    .rtrim(rtrim(number_format((float) $entry['width_ft'], 2), '0'), '.')
                    .' ft'
                  : 'L × W'),
              default => rtrim(rtrim(number_format($entryArea, 2), '0'), '.').' sq ft',
          };
        @endphp
        <div class="cp-vd-field cp-vd-field--full">
          <span class="cp-vd-field__label">{{ $entry['stone_name'] ?? 'Stone' }}</span>
          <span class="cp-vd-field__value">
            {{ $modeLabel }}
            @if ($mode !== 'sqft')
              <span style="color:#9ca3af;font-size:12px;">({{ rtrim(rtrim(number_format($entryArea, 2), '0'), '.') }} sq ft)</span>
            @endif
          </span>
        </div>
      @endforeach
    </div>
  </div>
@endif

@php
  $marbleEnabled = filter_var($inp['marble_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
  $legacyMarbleStoneId = $inp['marble_stone_type_id'] ?? null;
@endphp
@if ($marbleEnabled && ! empty($inp['marble_area_sq_ft']) && (! empty($inp['marble_name']) || ! empty($inp['marble_type_id'])))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Decorative marble</div>
    <div class="cp-vd-grid">
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Marble type</span>
        <span class="cp-vd-field__value">{{ $inp['marble_name'] ?? 'Marble bed' }}</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Area</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format((float) $inp['marble_area_sq_ft'], 2), '0'), '.') }} sq ft</span>
      </div>
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Depth</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format((float) ($inp['marble_depth_in'] ?? 2), 2), '0'), '.') }} in.</span>
      </div>
      @if (! empty($inp['marble_bags']))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Bags</span>
          <span class="cp-vd-field__value">{{ (int) $inp['marble_bags'] }}</span>
        </div>
      @endif
    </div>
  </div>
@elseif ($marbleEnabled && $legacyMarbleStoneId && ! empty($inp['marble_area_sq_ft']))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Decorative marble (legacy)</div>
    <p class="admin-form-help">This quote used the old marble-as-paver setting. Re-open the quotation and pick a marble type from the new catalog to recalculate.</p>
    <div class="cp-vd-grid">
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Area</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format((float) $inp['marble_area_sq_ft'], 2), '0'), '.') }} sq ft</span>
      </div>
    </div>
  </div>
@endif

{{-- Calculator summary (from stored calculation) --}}
@if (!empty($summary))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Calculator summary</div>
    <div class="cp-vd-grid">
      @if (isset($summary['materials_pre_hst']))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Materials (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($summary['materials_pre_hst']) }}</span>
        </div>
      @endif
      @if (isset($summary['labour_total']))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Labour (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($summary['labour_total']) }}</span>
        </div>
      @endif
      @if (isset($summary['suggested_pre_hst']))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Suggested total (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($summary['suggested_pre_hst']) }}</span>
        </div>
      @endif
      <div class="cp-vd-field cp-vd-field--highlight">
        <span class="cp-vd-field__label">Final quote total (incl. HST)</span>
        <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($finalTotal) }}</span>
      </div>
    </div>
  </div>
@endif
