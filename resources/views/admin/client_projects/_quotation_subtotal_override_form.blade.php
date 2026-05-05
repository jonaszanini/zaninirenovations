{{-- Expects: $project (ClientProject), $quotation (ClientProjectQuotation), $legacy (bool) --}}
@unless ($legacy)
  @if ($quotation->hasContent())
    <div class="cp-quotation-subtotal-override" style="margin:12px 0;padding:12px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
      <form method="post"
            action="{{ route('admin.client-projects.quotation.subtotal-override', [$project, $quotation]) }}"
            class="cp-inline-form"
            style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;">
        @csrf
        <div style="flex:1;min-width:200px;">
          <label for="cp-q-sub-override-{{ $quotation->id }}" class="admin-form-help" style="display:block;margin-bottom:4px;">
            Subtotal override (pre-tax)
          </label>
          <input type="text"
                 id="cp-q-sub-override-{{ $quotation->id }}"
                 name="subtotal_override"
                 class="admin-input cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 style="max-width:12rem;"
                 placeholder="0.00"
                 value="{{ old('subtotal_override', $quotation->hasSubtotalOverrideActive() ? number_format((float) $quotation->subtotal_override, 2, '.', '') : '') }}">
          <p class="admin-form-help" style="margin:6px 0 0;">
            Same calculator-style CAD entry as elsewhere (digits add from the right; thousands show with commas).
            Leave blank or zero to use the sum of line items
            (<strong>{{ \App\Models\ClientProject::formatMoney($quotation->lineItemsSubtotal()) }}</strong>).
            Any other amount replaces that subtotal; HST stays proportional to which lines are taxable.
          </p>
        </div>
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
          Save override
        </button>
      </form>
    </div>
  @endif
@endunless
