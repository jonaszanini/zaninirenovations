{{-- Expects: $project, $quotation, $legacy (bool) --}}
@unless ($legacy)
  <div class="cp-quotation-controls">
    <div class="cp-quotation-control">
      <form method="post"
            action="{{ route('admin.client-projects.quotation.status', [$project, $quotation]) }}"
            class="cp-quotation-control__form">
        @csrf
        <label for="cp-q-status-{{ $quotation->id }}" class="cp-quotation-control__label">Workflow status</label>
        <select class="admin-input" id="cp-q-status-{{ $quotation->id }}"
                name="status"
                class="admin-input cp-quotation-control__select"
                onchange="this.form.submit()">
          <option value="{{ \App\Models\ClientProjectQuotation::STATUS_DRAFT }}" @selected($quotation->status === \App\Models\ClientProjectQuotation::STATUS_DRAFT)>Draft</option>
          <option value="{{ \App\Models\ClientProjectQuotation::STATUS_SENT }}" @selected($quotation->status === \App\Models\ClientProjectQuotation::STATUS_SENT)>Sent</option>
          <option value="{{ \App\Models\ClientProjectQuotation::STATUS_ACCEPTED }}" @selected($quotation->status === \App\Models\ClientProjectQuotation::STATUS_ACCEPTED)>Accepted</option>
          <option value="{{ \App\Models\ClientProjectQuotation::STATUS_REJECTED }}" @selected($quotation->status === \App\Models\ClientProjectQuotation::STATUS_REJECTED)>Rejected</option>
        </select>
      </form>
    </div>

    @if ($quotation->hasContent())
      <div class="cp-quotation-control">
        <form method="post"
              action="{{ route('admin.client-projects.quotation.subtotal-override', [$project, $quotation]) }}"
              class="cp-quotation-control__form">
          @csrf
          <label for="cp-q-sub-override-{{ $quotation->id }}" class="cp-quotation-control__label">Subtotal override (pre-tax)</label>
          <div class="cp-quotation-control__field-row">
            <input class="admin-input" type="text"
                   id="cp-q-sub-override-{{ $quotation->id }}"
                   name="subtotal_override"
                   class="admin-input cp-money-input cp-quotation-control__input"
                   inputmode="decimal"
                   autocomplete="off"
                   placeholder="0.00"
                   value="{{ old('subtotal_override', $quotation->hasSubtotalOverrideActive() ? number_format((float) $quotation->subtotal_override, 2, '.', '') : '') }}">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm cp-quotation-control__submit">
              <i class="fa fa-save"></i> Save
            </button>
          </div>
        </form>
      </div>
    @endif
  </div>
@endunless
