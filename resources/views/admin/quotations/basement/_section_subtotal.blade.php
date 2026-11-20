{{-- Pre-HST labour + materials for this wizard trade step (live-updated from preview JSON). --}}
@php
  $supportsClientProvides = in_array($sectionKey, ['framing', 'insulation', 'ceiling', 'drywall', 'flooring', 'doors_trim', 'painting'], true);
@endphp
<div class="br-section-footer">
  @if ($supportsClientProvides)
    <div class="br-section-footer__client-provides">
      @include('admin.partials.checkbox', [
        'name' => '',
        'id' => 'br-inline-client-provides-'.$sectionKey,
        'checkboxValue' => $sectionKey,
        'checked' => false,
        'label' => 'Client supplies materials',
        'inputClass' => 'admin-checkbox__input',
        'attrs' => ['data-br-inline-client-provides' => $sectionKey],
      ])
    </div>
  @endif
  <div class="br-section-subtotal" data-br-section-subtotal="{{ $sectionKey }}" hidden aria-live="polite">
    <span class="br-section-subtotal__label">Section subtotal</span>
    <span class="br-section-subtotal__amount" data-br-section-subtotal-amount>CA$0.00</span>
  </div>
</div>
