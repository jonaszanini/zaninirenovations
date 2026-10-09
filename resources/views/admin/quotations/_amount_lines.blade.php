@once
  @push('scripts')
    <script src="{{ asset('js/admin-additional-costs.js') }}?v={{ @filemtime(public_path('js/admin-additional-costs.js')) ?: time() }}"></script>
  @endpush
  <style>
    .q-ac-rows { display: flex; flex-direction: column; gap: 12px; }
    .q-ac-row {
      display: grid;
      grid-template-columns: minmax(0, 1.6fr) minmax(120px, 0.55fr) auto auto;
      gap: 12px;
      align-items: end;
      padding: 12px 14px;
      background: var(--admin-surface-hover);
      border: 1px dashed var(--admin-border);
      border-radius: 8px;
    }
    .q-ac-row--with-category {
      grid-template-columns: minmax(0, 1.5fr) minmax(7rem, 0.45fr) minmax(10.5rem, 0.7fr) auto auto;
    }
    .q-ac-row__category select.admin-input {
      min-width: 10.5rem;
      padding-right: 2.6rem;
    }
    [data-ac-card] {
      container-type: inline-size;
    }
    @container (max-width: 720px) {
      .q-ac-row,
      .q-ac-row--with-category {
        grid-template-columns: minmax(0, 1fr) minmax(10.5rem, 0.8fr) auto auto;
        align-items: end;
      }
      .q-ac-row__desc {
        grid-column: 1 / -1;
      }
    }
    .q-ac-row .admin-form-group { margin-bottom: 0; min-width: 0; }
    .q-ac-row__desc label,
    .q-ac-row__amount label,
    .q-ac-row__category label,
    .q-ac-row__hst-label {
      display: block;
      overflow: visible;
      text-overflow: unset;
      white-space: nowrap;
    }
    .q-ac-row__hst-label {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--admin-muted);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .q-ac-hst-toggle.cp-visit-toggle {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3px;
      min-height: 44px;
      min-width: 64px;
    }
    .q-ac-hst-toggle .cp-visit-toggle__label {
      margin: 0;
      font-size: 0.68rem;
      font-weight: 600;
      line-height: 1.1;
      white-space: nowrap;
      color: var(--admin-muted);
    }
    .q-ac-hst-toggle input:checked ~ .cp-visit-toggle__label { color: var(--admin-text); }
    .q-ac-row__hst { text-align: center; }
    .q-ac-row__actions {
      display: flex;
      align-items: flex-end;
      padding-bottom: 2px;
    }
    .q-ac-row__actions .admin-btn {
      min-width: 44px;
      min-height: 44px;
    }
    .q-ac-footer {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
      margin-top: 12px;
    }
    .q-ac-summary { margin: 0; }
    .q-trade-lines { margin-top: 16px; }
    .q-trade-lines .q-additional-costs-card__intro { margin-bottom: 12px; }
    .q-additional-costs-card__title {
      margin: 0 0 6px;
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--admin-heading);
    }
    .q-additional-costs-card__intro { margin: 0 0 14px; }
    @media (max-width: 900px) {
      .q-ac-row,
      .q-ac-row--with-category {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        align-items: end;
      }
      .q-ac-row__desc {
        grid-column: 1 / -1;
      }
    }
    @media (max-width: 700px) {
      .q-ac-row,
      .q-ac-row--with-category {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 8px;
        align-items: end;
        padding: 10px;
      }
      .q-ac-row {
        grid-template-areas:
          "desc desc"
          "amount hst"
          "actions actions";
      }
      .q-ac-row--with-category {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
          "desc desc"
          "amount category"
          "taxrow taxrow";
      }
      .q-ac-row--with-category .q-ac-row__hst,
      .q-ac-row--with-category .q-ac-row__actions {
        grid-area: taxrow;
      }
      .q-ac-row--with-category .q-ac-row__hst {
        padding-right: 52px;
      }
      .q-ac-row--with-category .q-ac-row__actions {
        justify-self: end;
        align-self: center;
      }
      .q-ac-row__desc { grid-area: desc; }
      .q-ac-row__amount { grid-area: amount; }
      .q-ac-row__category { grid-area: category; }
      .q-ac-row__hst { grid-area: hst; }
      .q-ac-row__actions {
        grid-area: actions;
        justify-content: flex-end;
        align-items: center;
        padding-bottom: 0;
      }
      body .q-ac-row--with-category .q-ac-row__category select.admin-input {
        min-width: 8rem;
        width: auto;
        padding-right: 1.35rem;
      }
      .q-ac-row__hst-label {
        display: none;
      }
      .q-ac-row__hst {
        display: flex;
        align-items: center;
        justify-content: flex-start;
      }
      .q-ac-hst-toggle.cp-visit-toggle {
        flex-direction: row;
        align-items: center;
        gap: 8px;
        min-width: 0;
      }
    }
  </style>
@endonce

@php
  $field = $field ?? 'additional_costs';
  $idPrefix = $idPrefix ?? 'q-ac-'.preg_replace('/[^a-z0-9]+/i', '-', $field);
  $cardTitle = $cardTitle ?? '';
  $intro = $intro ?? '';
  $addLabel = $addLabel ?? 'Add line';
  $placeholder = $placeholder ?? 'Description';
  $inputClass = $inputClass ?? '';
  $eventName = $eventName ?? 'additional-costs:change';
  $summaryMode = $summaryMode ?? 'materials';
  $isAdditionalCard = $isAdditionalCard ?? false;
  $wrapClass = $wrapClass ?? 'q-trade-lines';
  $defaultHstIncluded = filter_var($defaultHstIncluded ?? true, FILTER_VALIDATE_BOOLEAN);
  $showCategory = ! empty($showCategory);

  $sourceRows = old($field);
  if (! is_array($sourceRows)) {
      $sourceRows = is_array($rows ?? null) ? $rows : [];
  }

  $normalizedRows = [];
  foreach ($sourceRows as $row) {
      if (! is_array($row)) {
          continue;
      }
      $desc = trim((string) ($row['description'] ?? ''));
      $amt = $row['amount'] ?? '';
      $hst = array_key_exists('hst_included', $row) ? ($row['hst_included'] ?? '0') : ($defaultHstIncluded ? '1' : '0');
      $hstIncluded = $hst === true || $hst === 1 || $hst === '1';
      $category = \App\Services\AdditionalCostsService::normalizeCategory($row['category'] ?? null);
      if ($desc === '' && ($amt === '' || $amt === null || (is_numeric($amt) && (float) $amt <= 0))) {
          continue;
      }
      $normalizedRows[] = [
          'description' => $desc,
          'amount' => is_numeric($amt) ? number_format((float) $amt, 2, '.', '') : (string) $amt,
          'hst_included' => $hstIncluded,
          'category' => $category,
      ];
  }

  if ($normalizedRows === []) {
      $normalizedRows[] = [
          'description' => '',
          'amount' => '',
          'hst_included' => $defaultHstIncluded,
          'category' => \App\Services\AdditionalCostsService::CATEGORY_MATERIALS,
      ];
  }
@endphp

<div class="{{ $wrapClass }}"
     @if($isAdditionalCard) id="q-additional-costs" data-cp-additional-costs @endif
     data-ac-card
     data-ac-field="{{ $field }}"
     data-ac-id-prefix="{{ $idPrefix }}"
     data-ac-placeholder="{{ $placeholder }}"
     data-ac-input-class="{{ $inputClass }}"
     data-ac-event="{{ $eventName }}"
     data-ac-summary-mode="{{ $summaryMode }}"
     data-ac-default-hst-included="{{ $defaultHstIncluded ? '1' : '0' }}"
     data-ac-show-category="{{ $showCategory ? '1' : '0' }}">
  @if($cardTitle !== '' || $intro !== '')
    <div class="q-additional-costs-card__head">
      @if($cardTitle !== '')
        <h3 class="q-additional-costs-card__title">
          {{ $cardTitle }}
          @if($intro !== '')
            <x-admin.info-tip :label="$cardTitle">{!! $intro !!}</x-admin.info-tip>
          @endif
        </h3>
      @elseif($intro !== '')
        <x-admin.info-tip label="Additional costs">{!! $intro !!}</x-admin.info-tip>
      @endif
    </div>
  @endif

  <div class="q-ac-rows" data-ac-rows>
    @foreach ($normalizedRows as $idx => $row)
      <div class="q-ac-row{{ $showCategory ? ' q-ac-row--with-category' : '' }}" data-ac-row data-ac-index="{{ $idx }}">
        <div class="admin-form-group q-ac-row__desc">
          <label for="{{ $idPrefix }}-desc-{{ $idx }}">Description</label>
          <input type="text"
                 id="{{ $idPrefix }}-desc-{{ $idx }}"
                 name="{{ $field }}[{{ $idx }}][description]"
                 class="admin-input {{ $inputClass }}"
                 maxlength="200"
                 placeholder="{{ $placeholder }}"
                 value="{{ $row['description'] }}"
                 data-ac-description>
        </div>
        <div class="admin-form-group q-ac-row__amount">
          <label for="{{ $idPrefix }}-amt-{{ $idx }}">Amount</label>
          <input type="text"
                 id="{{ $idPrefix }}-amt-{{ $idx }}"
                 name="{{ $field }}[{{ $idx }}][amount]"
                 class="admin-input cp-money-input {{ $inputClass }}"
                 inputmode="decimal"
                 autocomplete="off"
                
                 placeholder="0.00"
                 value="{{ $row['amount'] }}"
                 data-ac-amount>
        </div>
        @if ($showCategory)
          <div class="admin-form-group q-ac-row__category">
            <label for="{{ $idPrefix }}-cat-{{ $idx }}">Type</label>
            <select id="{{ $idPrefix }}-cat-{{ $idx }}"
                    name="{{ $field }}[{{ $idx }}][category]"
                    class="admin-input {{ $inputClass }}"
                    data-ac-category
                    aria-label="Materials or labour">
              <option value="materials" @selected(($row['category'] ?? 'materials') === 'materials')>Materials</option>
              <option value="labour" @selected(($row['category'] ?? 'materials') === 'labour')>Labour</option>
            </select>
          </div>
        @endif
        <div class="admin-form-group q-ac-row__hst">
          <span class="q-ac-row__hst-label" id="{{ $idPrefix }}-hst-label-{{ $idx }}">Tax</span>
          <label class="cp-visit-toggle cp-visit-toggle--compact q-ac-hst-toggle"
                 for="{{ $idPrefix }}-hst-{{ $idx }}">
            <input type="hidden" name="{{ $field }}[{{ $idx }}][hst_included]" value="0">
            <input type="checkbox"
                   id="{{ $idPrefix }}-hst-{{ $idx }}"
                   name="{{ $field }}[{{ $idx }}][hst_included]"
                   value="1"
                   data-ac-hst-toggle
                   aria-describedby="{{ $idPrefix }}-hst-label-{{ $idx }}"
                   @checked($row['hst_included'])>
            <span class="cp-visit-toggle__control" aria-hidden="true"></span>
            <span class="cp-visit-toggle__label" data-ac-hst-text>
              {{ $row['hst_included'] ? 'Tax included' : 'Before tax' }}
            </span>
          </label>
        </div>
        <div class="q-ac-row__actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm"
                  data-ac-remove
                  title="Remove line"
                  aria-label="Remove line">
            <i class="fa fa-times" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    @endforeach
  </div>

  <div class="q-ac-footer">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ac-add>
      <i class="fa fa-plus" aria-hidden="true"></i> {{ $addLabel }}
    </button>
    <p class="admin-form-help q-ac-summary" data-ac-summary hidden></p>
  </div>

  @error($field)
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error($field.'.*.description')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error($field.'.*.amount')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
</div>
