@php
  $scopeCopy = \App\Services\BasementClientScope::resolveCopy(is_array($inputs ?? null) ? $inputs : []);
  $feeDefault = 10;
  if (! empty($isEdit) && is_array($inputs ?? null) && ! array_key_exists('contractor_fee_percent', $inputs)) {
      $feeDefault = 0;
  }
  $feeValue = old('contractor_fee_percent', $inputs['contractor_fee_percent'] ?? $feeDefault);
  $pkgNotes = old('value_package_notes', $inputs['value_package_notes'] ?? []);
  if (! is_array($pkgNotes)) {
      $pkgNotes = [];
  }
  $scopeFieldLocked = function (string $name) use ($inputs): bool {
      return old($name) !== null || filled($inputs[$name] ?? null);
  };
@endphp
{{-- Client scope — always last trade-adjacent step, before Review --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="client_scope" open>
  <summary class="br-acc__summary">
    <span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span>
    Client scope
    <x-admin.info-tip label="Client scope">Shown on the client PDF and email after the project description and before priced values. Defaults follow the trades and optional extras you turned on. Unchecked services stay in Not included.</x-admin.info-tip>
  </summary>
  <div class="br-acc__body">
    <div class="br-client-scope">
      <div class="admin-form-group br-client-scope__fee">
        <span class="admin-label-with-tip">
          <label for="br-contractor-fee">Contractor fee (%)</label>
          @include('admin.partials.info_tip', [
            'tipText' => 'Default 10%. Applies only to materials and subcontracted services (electrical, plumbing, BCIN drawings). Does not apply to Jonas hourly labour or the city permit disbursement. Shown as its own line before HST. The same percentage applies to written change orders.',
            'tipLabel' => 'Contractor fee (%)',
          ])
        </span>
        <input type="number" min="0" max="100" step="0.1" class="admin-input br-preview br-input--fee" id="br-contractor-fee"
               name="contractor_fee_percent" value="{{ $feeValue }}">
      </div>

      <div class="br-client-scope__grid">
        <div class="admin-form-group">
          <span class="admin-label-with-tip">
            <label for="br-scope-included">Included</label>
            @include('admin.partials.info_tip', [
              'tipText' => 'Prefills from enabled trades and checked optional extras. Edit before sending. Never list BCIN drawings or a city permit unless those extras are checked.',
              'tipLabel' => 'Included',
            ])
          </span>
          <textarea class="admin-input br-preview br-scope-copy" id="br-scope-included" name="client_scope_included" rows="6"
                    data-br-scope-field="included"@if($scopeFieldLocked('client_scope_included')) data-br-scope-persisted="1" data-br-scope-dirty="1"@endif>{{ old('client_scope_included', $inputs['client_scope_included'] ?? $scopeCopy['included']) }}</textarea>
        </div>
        <div class="admin-form-group">
          <span class="admin-label-with-tip">
            <label for="br-scope-not-included">Not included</label>
            @include('admin.partials.info_tip', [
              'tipText' => 'Unchecked trades, carpet, and optional extra-charge services (BCIN drawings, city permit) that are not turned on.',
              'tipLabel' => 'Not included',
            ])
          </span>
          <textarea class="admin-input br-preview br-scope-copy" id="br-scope-not-included" name="client_scope_not_included" rows="6"
                    data-br-scope-field="not_included"@if($scopeFieldLocked('client_scope_not_included')) data-br-scope-persisted="1" data-br-scope-dirty="1"@endif>{{ old('client_scope_not_included', $inputs['client_scope_not_included'] ?? $scopeCopy['not_included']) }}</textarea>
        </div>
        <div class="admin-form-group">
          <span class="admin-label-with-tip">
            <label for="br-scope-assumptions">Assumptions</label>
            @include('admin.partials.info_tip', [
              'tipText' => 'Includes the contractor-fee sentence (materials and subcontracted services, including change orders) and fully insured wording only.',
              'tipLabel' => 'Assumptions',
            ])
          </span>
          <textarea class="admin-input br-preview br-scope-copy" id="br-scope-assumptions" name="client_scope_assumptions" rows="6"
                    data-br-scope-field="assumptions"@if($scopeFieldLocked('client_scope_assumptions')) data-br-scope-persisted="1" data-br-scope-dirty="1"@endif>{{ old('client_scope_assumptions', $inputs['client_scope_assumptions'] ?? $scopeCopy['assumptions']) }}</textarea>
        </div>
        <div class="admin-form-group">
          <span class="admin-label-with-tip">
            <label for="br-scope-allowances">Allowances</label>
            @include('admin.partials.info_tip', [
              'tipText' => 'Priced electrical/plumbing trade lines and optional extra-charge amounts when those extras are on.',
              'tipLabel' => 'Allowances',
            ])
          </span>
          <textarea class="admin-input br-preview br-scope-copy" id="br-scope-allowances" name="client_scope_allowances" rows="6"
                    data-br-scope-field="allowances"@if($scopeFieldLocked('client_scope_allowances')) data-br-scope-persisted="1" data-br-scope-dirty="1"@endif>{{ old('client_scope_allowances', $inputs['client_scope_allowances'] ?? $scopeCopy['allowances']) }}</textarea>
        </div>
      </div>

      <div class="br-value-packages" id="br-value-packages">
        <p class="br-value-packages__title">
          <span class="admin-label-with-tip">
            <span>Invest — Base / Standard / Upgrade</span>
            @include('admin.partials.info_tip', [
              'tipText' => 'Three investment options on the PDF, email, and accept page. Standard is this quotation as configured. Base uses vinyl plank (LVP). Upgrade uses hardwood. The client picks one at acceptance; that total becomes the contract. Totals stay CAD$ and include HST.',
              'tipLabel' => 'Invest — Base / Standard / Upgrade',
            ])
          </span>
        </p>
        <div class="br-value-packages__grid">
          @foreach (\App\Services\BasementClientScope::VALUE_TIERS as $tier => $tierLabel)
            <div class="br-value-package" data-br-value-tier="{{ $tier }}">
              <p class="br-value-package__label">{{ $tierLabel }}</p>
              <p class="br-value-package__total" data-br-value-total>—</p>
              <p class="br-value-package__floor" data-br-value-floor></p>
              <label class="visually-hidden" for="br-pkg-note-{{ $tier }}">{{ $tierLabel }} note</label>
              <textarea class="admin-input br-preview" id="br-pkg-note-{{ $tier }}" name="value_package_notes[{{ $tier }}]" rows="2"
                        placeholder="Short note for this option">{{ $pkgNotes[$tier] ?? '' }}</textarea>
            </div>
          @endforeach
        </div>
        <input type="hidden" name="selected_value_tier" id="br-selected-tier" value="{{ old('selected_value_tier', $inputs['selected_value_tier'] ?? 'standard') }}">
      </div>
    </div>
  </div>
</details>
