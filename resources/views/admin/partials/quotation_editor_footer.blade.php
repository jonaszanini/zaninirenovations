{{--
  Persistent quotation editor footer (basement-style): live grand total + Save.
  Painting must not include this partial.

  Required: $formId
  Common:   $footerId, $amountId, $initialTotal, $extraClass, $wizard, $extra
--}}
@php
  $qhFormId = $formId ?? null;
  $qhFooterId = $footerId ?? 'qh-sticky-footer';
  $qhAmountId = $amountId ?? 'qh-footer-grand-amount';
  $qhInitial = (float) ($initialTotal ?? 0);
  $qhExtraClass = trim((string) ($extraClass ?? ''));
  $qhWizard = $wizard ?? null;
  $qhExtra = $extra ?? null;
  $qhGrandWrapId = $grandWrapId ?? null;
@endphp
<div class="qh-sticky-footer{{ $qhExtraClass !== '' ? ' '.$qhExtraClass : '' }}"
     id="{{ $qhFooterId }}"
     data-qh-footer
     aria-label="Quotation total and save">
  @if (is_array($qhWizard) && ! empty($qhWizard['navId']))
    <div class="br-step-nav qh-sticky-footer__nav"
         id="{{ $qhWizard['navId'] }}"
         role="group"
         aria-label="Wizard steps"
         @if (! empty($qhWizard['hidden'])) hidden @endif>
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm"
              id="{{ $qhWizard['prevId'] ?? 'qh-footer-prev' }}"
              disabled>← Back</button>
      <span class="br-step-nav__status"
            id="{{ $qhWizard['statusId'] ?? 'qh-footer-step-status' }}">1 of {{ (int) ($qhWizard['count'] ?? 1) }}</span>
      <button type="button"
              class="admin-btn admin-btn--primary admin-btn--sm"
              id="{{ $qhWizard['nextId'] ?? 'qh-footer-next' }}">→ Next</button>
    </div>
  @endif
  @if (filled($qhExtra))
    {!! $qhExtra !!}
  @endif
  <div class="br-toolbar-grand qh-sticky-footer__total"
       @if (filled($qhGrandWrapId)) id="{{ $qhGrandWrapId }}" @endif
       title="Live grand total (incl. HST)">
    <span class="br-toolbar-grand__label">Grand total</span>
    <strong class="br-toolbar-grand__amount"
            data-qh-footer-amount
            id="{{ $qhAmountId }}">{{ \App\Models\ClientProject::formatMoney($qhInitial) }}</strong>
  </div>
  <button type="submit"
          class="admin-btn admin-btn--primary admin-btn--sm qh-sticky-footer__save"
          @if (filled($qhFormId)) form="{{ $qhFormId }}" @endif
          data-qh-footer-save
          data-qh-save-intent="publish"
          aria-label="Save quotation">
    <i class="fa fa-save" aria-hidden="true"></i>
    <span class="qh-sticky-footer__save-full">Save quotation</span>
    <span class="qh-sticky-footer__save-short">Publish</span>
  </button>
</div>
