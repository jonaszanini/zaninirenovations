@php
  /** @var \Illuminate\Support\Collection<int, \App\Models\StairsMaterialType> $materialTypes */
  /** @var \App\Models\StairsSettings $settings */
@endphp
<div class="pq-db-wrap admin-card">
  @unless ($hideDatabaseHeading ?? false)
  <h3 style="margin:0 0 12px;">Stairs price database</h3>
  @endunless
  <p class="admin-form-help">Set per-step rates (before HST). Carpet removal, stain, and clear coat are global — one price for all materials. Other fields are per material type.</p>

  <form method="post" action="{{ route('admin.quotations.stairs.database.update') }}" enctype="multipart/form-data" class="sq-db-form">
    @csrf

    <div class="pq-form-grid sq-db-global-grid" style="margin-bottom:20px;">
      <div class="admin-form-group">
        <label for="sq-db-carpet">Carpet removal ($/step)</label>
        <input type="text" id="sq-db-carpet" name="carpet_removal_per_step" class="admin-input cp-money-input" inputmode="decimal" required
               value="{{ old('carpet_removal_per_step', number_format((float) $settings->carpet_removal_per_step, 2, '.', '')) }}">
      </div>
      <div class="admin-form-group">
        <label for="sq-db-stain">Requires stain ($/step)</label>
        <input type="text" id="sq-db-stain" name="stain_per_step" class="admin-input cp-money-input" inputmode="decimal" required
               value="{{ old('stain_per_step', number_format((float) $settings->stain_per_step, 2, '.', '')) }}">
      </div>
      <div class="admin-form-group">
        <label for="sq-db-clear">Clear coat protection ($/step)</label>
        <input type="text" id="sq-db-clear" name="clear_coat_per_step" class="admin-input cp-money-input" inputmode="decimal" required
               value="{{ old('clear_coat_per_step', number_format((float) $settings->clear_coat_per_step, 2, '.', '')) }}">
      </div>
    </div>

    @foreach ($materialTypes as $mat)
      @php
        $p = $mat->mergedPricing();
        $materialOldBase = 'materials.'.$mat->id;
        $val = function (string $path) use ($materialOldBase, $p) {
            $flatKey = $materialOldBase.'.'.str_replace(['.', '[', ']'], ['_', '', ''], $path);
            $o = old(str_replace('materials.'.$materialOldBase, $materialOldBase, $flatKey));
            $pathParts = explode('.', $path);
            $lookupKey = implode('_', $pathParts);
            $o = old($materialOldBase.'.'.$lookupKey);
            if ($o !== null && $o !== '') {
                return is_numeric($o) ? number_format((float) $o, 2, '.', '') : $o;
            }
            $cur = $p;
            foreach ($pathParts as $seg) {
                if (! is_array($cur) || ! array_key_exists($seg, $cur)) {
                    return '0.00';
                }
                $cur = $cur[$seg];
            }
            return number_format((float) $cur, 2, '.', '');
        };
      @endphp
      <div class="sq-db-material admin-card" style="margin-bottom:16px;background:#f8fafc;">
        <input type="hidden" name="materials[{{ $mat->id }}][id]" value="{{ $mat->id }}">
        <div>
          <h4 style="margin:0 0 6px;line-height:1.3;">
            @foreach ($mat->adminDisplayLines() as $line)
              <span style="display:block;">{{ $line }}</span>
            @endforeach
          </h4>
          <p class="pq-hint" style="margin:0;">
            Nosing:
            @if ($mat->nosing_mode === \App\Models\StairsMaterialType::NOSING_CHOICE)
              add-on (set prices below)
            @else
              included in material rates
            @endif
          </p>
        </div>

        <div class="admin-form-group" style="margin-top:12px;">
          <label>Material photo</label>
          @if ($mat->photoUrl())
            <div style="margin:6px 0;"><img src="{{ $mat->photoUrl() }}" alt="" style="max-height:120px;border-radius:6px;border:1px solid #e2e8f0;"></div>
          @endif
          <input type="file" name="material_photo_{{ $mat->id }}" class="admin-input" accept="image/*">
          <p class="pq-hint" style="margin-top:6px;">Choose a new image to replace the current one. Leave empty to keep the existing photo.</p>
        </div>

        <div class="sq-db-open-block">
          <p class="sq-db-open-block__title">No open sides ($/step)</p>
          <div class="pq-form-grid">
            @foreach (['standard' => 'Standard stairs', 'winder' => 'Winder', 'double_landing' => 'Double landing', 'single_landing' => 'Single landing'] as $st => $lbl)
              <div class="admin-form-group">
                <label>{{ $lbl }}</label>
                <input type="text" name="materials[{{ $mat->id }}][no_open_{{ $st }}]" class="admin-input cp-money-input" inputmode="decimal"
                       value="{{ old('materials.'.$mat->id.'.no_open_'.$st, number_format((float) ($p['no_open'][$st] ?? 0), 2, '.', '')) }}">
              </div>
            @endforeach
          </div>
        </div>

        <div class="sq-db-open-block">
          <p class="sq-db-open-block__title">One open side ($/step)</p>
          <div class="pq-form-grid">
            @foreach (['standard' => 'Standard stairs', 'winder' => 'Winder', 'double_landing' => 'Double landing', 'single_landing' => 'Single landing'] as $st => $lbl)
              <div class="admin-form-group">
                <label>{{ $lbl }}</label>
                <input type="text" name="materials[{{ $mat->id }}][one_open_{{ $st }}]" class="admin-input cp-money-input" inputmode="decimal"
                       value="{{ old('materials.'.$mat->id.'.one_open_'.$st, number_format((float) ($p['one_open'][$st] ?? 0), 2, '.', '')) }}">
              </div>
            @endforeach
          </div>
        </div>

        <div class="sq-db-open-block">
          <p class="sq-db-open-block__title">Two open sides ($/step)</p>
          <div class="admin-form-group" style="max-width:240px;margin-bottom:0;">
            <label>Standard stairs only</label>
            <input type="text" name="materials[{{ $mat->id }}][two_open_standard]" class="admin-input cp-money-input" inputmode="decimal"
                   value="{{ old('materials.'.$mat->id.'.two_open_standard', number_format((float) ($p['two_open']['standard'] ?? 0), 2, '.', '')) }}">
          </div>
        </div>

        @if ($mat->nosing_mode === \App\Models\StairsMaterialType::NOSING_CHOICE)
          <p style="margin:14px 0 8px;font-weight:600;font-size:13px;">Nosing options ($/step)</p>
          <div class="pq-form-grid">
            @foreach (\App\Models\StairsMaterialType::nosingChoiceLabels() as $nk => $nLabel)
              <div class="admin-form-group">
                <label>{{ $nLabel }}</label>
                <input type="text" name="materials[{{ $mat->id }}][nosing_{{ $nk }}]" class="admin-input cp-money-input" inputmode="decimal"
                       value="{{ old('materials.'.$mat->id.'.nosing_'.$nk, number_format((float) ($p['nosing_prices'][$nk] ?? 0), 2, '.', '')) }}">
              </div>
            @endforeach
          </div>
        @endif
      </div>
    @endforeach

    <button type="submit" class="admin-btn admin-btn--primary">
      <i class="fa fa-save"></i> Save price database
    </button>
  </form>
</div>
