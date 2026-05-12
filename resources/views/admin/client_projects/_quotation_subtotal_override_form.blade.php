{{-- Expects: $project (ClientProject), $quotation (ClientProjectQuotation), $legacy (bool) --}}
@unless ($legacy)
  @if ($quotation->hasContent())
    <div class="cp-quotation-subtotal-override" style="margin:12px 0;padding:12px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
      <form method="post"
            action="{{ route('admin.client-projects.quotation.subtotal-override', [$project, $quotation]) }}"
            class="cp-inline-form cp-quotation-subtotal-override__form">
        @csrf
        <div class="admin-form-group" style="margin:0;">
          <label for="cp-q-sub-override-{{ $quotation->id }}" style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">
            Subtotal override (pre-tax)
          </label>
          <div class="cp-quotation-subtotal-override__row" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
            <input type="text"
                   id="cp-q-sub-override-{{ $quotation->id }}"
                   name="subtotal_override"
                   class="admin-input cp-money-input"
                   inputmode="decimal"
                   autocomplete="off"
                   style="min-width:10rem;max-width:14rem;flex:1;"
                   placeholder="0.00"
                   value="{{ old('subtotal_override', $quotation->hasSubtotalOverrideActive() ? number_format((float) $quotation->subtotal_override, 2, '.', '') : '') }}">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="flex-shrink:0;">
              <i class="fa fa-save"></i> Save override
            </button>
          </div>
        </div>
      </form>
    </div>
  @endif
@endunless
