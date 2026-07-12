@php
  use App\Services\PavingStoneEntryResolver;

  $stoneFormEntries = old('stone_entries');
  if (! is_array($stoneFormEntries) || $stoneFormEntries === []) {
      $stoneFormEntries = PavingStoneEntryResolver::entriesForForm($inputs);
  }
  $stoneFormEntries = array_values($stoneFormEntries);
@endphp

<div class="admin-form-group pq-stone-entries-block">
  <div class="pq-stone-entries-head">
    <span class="admin-form-group-label">Stone types <span class="cp-required">*</span></span>
    <p class="pq-hint pq-hint--inline">Add up to {{ PavingStoneEntryResolver::MAX_ENTRIES }} stone types. <strong>Total paving stone area</strong> is the sum of all paver stone areas (crushed base, sand, disposal, paver labour, etc.). Decorative marble is separate and additive.</p>
  </div>

  <div id="pq-stone-entries" class="pq-stone-entries">
    @foreach ($stoneFormEntries as $idx => $entry)
      @php
        $mode = old("stone_entries.{$idx}.input_mode", $entry['input_mode'] ?? 'sqft');
        $modeQty = $mode === 'qty';
        $modeSqft = $mode === 'sqft';
        $modeDim = $mode === 'dimensions';
        $selectedStoneId = (int) old("stone_entries.{$idx}.stone_type_id", $entry['stone_type_id'] ?? 0);
      @endphp
      <div class="pq-stone-entry" data-index="{{ $idx }}">
        <div class="pq-stone-entry__toolbar">
          <span class="pq-stone-entry__title">Stone {{ $idx + 1 }}</span>
          @if ($idx > 0)
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm pq-stone-entry-remove">Remove</button>
          @endif
        </div>

        <div class="pq-stone-entry__stone">
          <div class="pq-stone-thumb-wrap pq-stone-entry-thumb">
            <img class="pq-stone-entry-img" src="" alt="" style="display:none;">
            <i class="fa fa-cubes pq-stone-entry-placeholder" aria-hidden="true"></i>
          </div>
          <div class="pq-stone-entry__select-wrap">
            <label class="pq-stone-entry__label">Stone type</label>
            <select name="stone_entries[{{ $idx }}][stone_type_id]" class="admin-input pq-calc-in pq-stone-entry-select" required>
              @foreach ($stoneTypes as $st)
                <option value="{{ $st->id }}"
                  data-sq="{{ $st->sq_ft_per_stone }}"
                  data-pr="{{ $st->price_per_stone }}"
                  data-psq="{{ $st->price_per_sq_ft }}"
                  data-len="{{ $st->length_in }}"
                  data-wid="{{ $st->width_in }}"
                  data-hgt="{{ $st->height_in }}"
                  data-url="{{ $st->product_url }}"
                  data-img="{{ $st->image_url }}"
                  @selected($selectedStoneId === (int) $st->id)>{{ $st->name }}</option>
              @endforeach
            </select>
            <div class="pq-stone-meta pq-stone-entry-meta" aria-live="polite"></div>
          </div>
        </div>

        <div class="pq-stone-entry__mode" role="radiogroup" aria-label="How to enter stone area">
          <label>
            <input type="radio" name="stone_entries[{{ $idx }}][input_mode]" value="qty" class="pq-stone-mode-radio pq-calc-in" @checked($modeQty)>
            Quantity of stones
          </label>
          <label>
            <input type="radio" name="stone_entries[{{ $idx }}][input_mode]" value="sqft" class="pq-stone-mode-radio pq-calc-in" @checked($modeSqft)>
            Total sq ft
          </label>
          <label>
            <input type="radio" name="stone_entries[{{ $idx }}][input_mode]" value="dimensions" class="pq-stone-mode-radio pq-calc-in" @checked($modeDim)>
            Length × width (ft)
          </label>
        </div>

        <div class="pq-stone-entry__fields">
          <div class="pq-stone-field pq-stone-field--qty" @unless($modeQty) hidden @endunless>
            <label>Number of stones</label>
            <input type="number" name="stone_entries[{{ $idx }}][qty]" class="admin-input pq-calc-in pq-stone-qty-in" min="1" step="1" inputmode="numeric"
                   @if($modeQty) required @endif
                   value="{{ old("stone_entries.{$idx}.qty", $entry['qty'] ?? '') }}"
                   placeholder="e.g. 500">
          </div>
          <div class="pq-stone-field pq-stone-field--sqft" @unless($modeSqft) hidden @endunless>
            <label>Area (sq ft)</label>
            <input type="number" name="stone_entries[{{ $idx }}][area_sq_ft]" class="admin-input pq-calc-in pq-stone-area-in" min="0.01" step="0.01"
                   @if($modeSqft) required @endif
                   value="{{ old("stone_entries.{$idx}.area_sq_ft", $entry['area_sq_ft'] ?? '') }}"
                   placeholder="e.g. 168">
          </div>
          <div class="pq-stone-field pq-stone-field--dimensions" @unless($modeDim) hidden @endunless>
            <div class="pq-area-dimensions">
              <div class="admin-form-group">
                <label>Length (ft)</label>
                <input type="number" name="stone_entries[{{ $idx }}][length_ft]" class="admin-input pq-calc-in pq-stone-len-in" min="0.01" step="0.01"
                       @if($modeDim) required @endif
                       value="{{ old("stone_entries.{$idx}.length_ft", $entry['length_ft'] ?? '') }}"
                       placeholder="e.g. 12">
              </div>
              <span class="pq-area-dimensions-sep" aria-hidden="true">×</span>
              <div class="admin-form-group">
                <label>Width (ft)</label>
                <input type="number" name="stone_entries[{{ $idx }}][width_ft]" class="admin-input pq-calc-in pq-stone-wid-in" min="0.01" step="0.01"
                       @if($modeDim) required @endif
                       value="{{ old("stone_entries.{$idx}.width_ft", $entry['width_ft'] ?? '') }}"
                       placeholder="e.g. 14">
              </div>
            </div>
            <p class="pq-area-computed pq-stone-dim-computed" aria-live="polite"></p>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="pq-stone-entries-actions">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pq-add-stone-entry"
            @if(count($stoneFormEntries) >= PavingStoneEntryResolver::MAX_ENTRIES) hidden @endif>
      <i class="fa fa-plus"></i> Add stone type
    </button>
    <p class="pq-area-computed pq-total-area-line" id="pq-total-area-line" aria-live="polite"></p>
  </div>

  @error('stone_entries') <p class="admin-form-error">{{ $message }}</p> @enderror
  @foreach ($stoneFormEntries as $idx => $entry)
    @error("stone_entries.{$idx}.stone_type_id") <p class="admin-form-error">{{ $message }}</p> @enderror
    @error("stone_entries.{$idx}.qty") <p class="admin-form-error">{{ $message }}</p> @enderror
    @error("stone_entries.{$idx}.area_sq_ft") <p class="admin-form-error">{{ $message }}</p> @enderror
    @error("stone_entries.{$idx}.length_ft") <p class="admin-form-error">{{ $message }}</p> @enderror
  @endforeach
</div>

<template id="pq-stone-entry-template">
  <div class="pq-stone-entry" data-index="__INDEX__">
    <div class="pq-stone-entry__toolbar">
      <span class="pq-stone-entry__title">Stone __NUM__</span>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm pq-stone-entry-remove">Remove</button>
    </div>
    <div class="pq-stone-entry__stone">
      <div class="pq-stone-thumb-wrap pq-stone-entry-thumb">
        <img class="pq-stone-entry-img" src="" alt="" style="display:none;">
        <i class="fa fa-cubes pq-stone-entry-placeholder" aria-hidden="true"></i>
      </div>
      <div class="pq-stone-entry__select-wrap">
        <label class="pq-stone-entry__label">Stone type</label>
        <select name="stone_entries[__INDEX__][stone_type_id]" class="admin-input pq-calc-in pq-stone-entry-select" required>
          @foreach ($stoneTypes as $st)
            <option value="{{ $st->id }}"
              data-sq="{{ $st->sq_ft_per_stone }}"
              data-pr="{{ $st->price_per_stone }}"
              data-psq="{{ $st->price_per_sq_ft }}"
              data-len="{{ $st->length_in }}"
              data-wid="{{ $st->width_in }}"
              data-hgt="{{ $st->height_in }}"
              data-url="{{ $st->product_url }}"
              data-img="{{ $st->image_url }}">{{ $st->name }}</option>
          @endforeach
        </select>
        <div class="pq-stone-meta pq-stone-entry-meta" aria-live="polite"></div>
      </div>
    </div>
    <div class="pq-stone-entry__mode" role="radiogroup">
      <label><input type="radio" name="stone_entries[__INDEX__][input_mode]" value="qty" class="pq-stone-mode-radio pq-calc-in"> Quantity of stones</label>
      <label><input type="radio" name="stone_entries[__INDEX__][input_mode]" value="sqft" class="pq-stone-mode-radio pq-calc-in" checked> Total sq ft</label>
      <label><input type="radio" name="stone_entries[__INDEX__][input_mode]" value="dimensions" class="pq-stone-mode-radio pq-calc-in"> Length × width (ft)</label>
    </div>
    <div class="pq-stone-entry__fields">
      <div class="pq-stone-field pq-stone-field--qty" hidden>
        <label>Number of stones</label>
        <input type="number" name="stone_entries[__INDEX__][qty]" class="admin-input pq-calc-in pq-stone-qty-in" min="1" step="1" inputmode="numeric" placeholder="e.g. 500">
      </div>
      <div class="pq-stone-field pq-stone-field--sqft">
        <label>Area (sq ft)</label>
        <input type="number" name="stone_entries[__INDEX__][area_sq_ft]" class="admin-input pq-calc-in pq-stone-area-in" min="0.01" step="0.01" required placeholder="e.g. 168">
      </div>
      <div class="pq-stone-field pq-stone-field--dimensions" hidden>
        <div class="pq-area-dimensions">
          <div class="admin-form-group">
            <label>Length (ft)</label>
            <input type="number" name="stone_entries[__INDEX__][length_ft]" class="admin-input pq-calc-in pq-stone-len-in" min="0.01" step="0.01" placeholder="e.g. 12">
          </div>
          <span class="pq-area-dimensions-sep" aria-hidden="true">×</span>
          <div class="admin-form-group">
            <label>Width (ft)</label>
            <input type="number" name="stone_entries[__INDEX__][width_ft]" class="admin-input pq-calc-in pq-stone-wid-in" min="0.01" step="0.01" placeholder="e.g. 14">
          </div>
        </div>
        <p class="pq-area-computed pq-stone-dim-computed" aria-live="polite"></p>
      </div>
    </div>
  </div>
</template>
