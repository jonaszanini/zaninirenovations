{{--
  Shared sticky totals bar and Send-to-client modal for every quotation edit screen.
  Patterned after the basement quotation bottom bar.
--}}
@php
  use App\Models\ClientProject;

  $isEdit = !empty($isEdit);
  $quotation = $quotation ?? null;
  $linkedCpq = $linkedCpq ?? null;
  $project = $project ?? ($isEdit && $quotation ? ($quotation->project ?? null) : null) ?? ($linkedCpq?->clientProject ?? null);
  $client = $client ?? ($project?->client ?? null);

  $quoteNumber = $quoteNumber ?? ($quotation->quote_number ?? $linkedCpq?->quotation_number ?? 'Quotation');
  $version = (int) ($linkedCpq?->version ?? ($quotation->version ?? 1));
  $sentAt = $sentAt ?? ($linkedCpq?->sent_at ?? ($quotation->sent_at ?? null));
  $isDraft = (bool) ($isDraft ?? ($linkedCpq?->isSourceDraft() ?? ($quotation->is_draft ?? false)));

  $clientEmail = $clientEmail ?? trim((string) ($project?->client_email ?? $client?->email ?? ''));
  $clientSecondaryEmail = $clientSecondaryEmail ?? trim((string) ($project?->secondaryClientEmail() ?? ''));
  $clientName = $clientName ?? ($project?->client_name ?? $client?->name ?? ($quotation->client_name ?? ''));
  $clientEditUrl = $clientEditUrl ?? ($client ? route('admin.clients.edit', $client) : ($project ? route('admin.client-projects.edit', $project) : null));

  $sendUrl = $sendUrl ?? ($project && $linkedCpq ? route('admin.client-projects.quotation.send', [$project, $linkedCpq]) : null);
  $pdfPreviewUrl = $pdfPreviewUrl ?? ($project && $linkedCpq ? route('admin.client-projects.quotation.pdf', [$project, $linkedCpq]) : null);

  $breakdown = isset($breakdown) && is_array($breakdown) ? $breakdown : null;
  $initialMaterials = round((float) ($breakdown['materials'] ?? $initialMaterials ?? 0), 2);
  $initialLabour = round((float) ($breakdown['labour'] ?? $initialLabour ?? 0), 2);
  $initialExtras = round((float) ($breakdown['extras'] ?? $initialExtras ?? 0), 2);
  $hasExtras = !empty($breakdown['hasExtras']) || !empty($hasExtras) || $initialExtras > 0;
  $initialHst = round((float) ($breakdown['hst'] ?? $initialHst ?? 0), 2);
  $hstRateLabel = $hstRateLabel ?? ('HST (' . ($breakdown['hstRate'] ?? 13) . '%)');
  $initialGrand = round((float) ($breakdown['grand'] ?? $initialGrand ?? 0), 2);

  $hasSteps = !empty($hasSteps);
  $stepNavId = $stepNavId ?? 'quote-step-nav';
  $stepPrevId = $stepPrevId ?? 'quote-prev';
  $stepNextId = $stepNextId ?? 'quote-next';
  $stepStatusId = $stepStatusId ?? 'quote-step-status';
  $stepCount = max(1, (int) ($stepCount ?? 1));

  $formId = $formId ?? null;
  $saveId = $saveId ?? 'quote-btn-save';
  $saveLabel = $saveLabel ?? 'Save quotation';
  $saveName = $saveName ?? null;
  $saveValue = $saveValue ?? null;
  $backUrl = $backUrl ?? null;
  $barId = $barId ?? 'quote-sticky-total';

  $defaultMessage = "Please find attached your quotation {$quoteNumber} from Zanini Renovations for your review.";
@endphp

<div class="quote-totals-bar-spacer br-sticky-spacer" aria-hidden="true"></div>

<div class="quote-totals-bar br-sticky-total {{ $extraClass ?? '' }}"
     id="{{ $barId }}"
     data-quote-totals-bar
     data-send-url="{{ $sendUrl }}"
     data-is-edit="{{ $isEdit ? '1' : '0' }}"
     data-is-draft="{{ $isDraft ? '1' : '0' }}"
     data-has-client-email="{{ filled($clientEmail) ? '1' : '0' }}"
     aria-label="Quotation totals and actions">

  {{-- 1. Navigation: Back, Review / Next where quote has steps, and Save --}}
  <div class="quote-totals-bar__left">
    @if ($hasSteps)
      <div class="quote-step-nav br-step-nav" id="{{ $stepNavId }}">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="{{ $stepPrevId }}" data-step-prev disabled>← Back</button>
        <span class="br-step-nav__status" id="{{ $stepStatusId }}">1 of {{ $stepCount }}</span>
        <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="{{ $stepNextId }}" data-step-next>{{ $stepNextLabel ?? 'Review / Next' }}</button>
      </div>
    @elseif (filled($backUrl))
      <a href="{{ $backUrl }}" class="admin-btn admin-btn--ghost admin-btn--sm quote-totals-back-btn" data-quote-back title="Back">
        ← Back
      </a>
    @endif

    @if (filled($formId))
      <button type="submit"
              form="{{ $formId }}"
              id="{{ $saveId }}"
              @if(filled($saveName)) name="{{ $saveName }}" @endif
              @if(filled($saveValue)) value="{{ $saveValue }}" @endif
              class="admin-btn admin-btn--secondary admin-btn--sm quote-bar-save-btn"
              data-quote-save-btn
              title="Save quotation (Ctrl+S)">
        <i class="fa fa-save" aria-hidden="true"></i> <span>{{ $saveLabel }}</span>
      </button>
    @endif
  </div>

  {{-- 2. Live figures (desktop): Materials, Labour, Extras, Tax (HST), GRAND TOTAL --}}
  <div class="quote-totals-bar__center" role="region" aria-label="Quotation figures">
    <div class="quote-totals-figure quote-totals-figure--mat" title="Materials subtotal">
      <span class="quote-totals-figure__label">Materials</span>
      <strong class="quote-totals-figure__val" data-quote-materials>{{ ClientProject::formatMoney($initialMaterials) }}</strong>
    </div>

    <div class="quote-totals-figure quote-totals-figure--labour" title="Labour subtotal">
      <span class="quote-totals-figure__label">Labour</span>
      <strong class="quote-totals-figure__val" data-quote-labour>{{ ClientProject::formatMoney($initialLabour) }}</strong>
    </div>

    <div class="quote-totals-figure quote-totals-figure--extras"
         data-quote-extras-wrap
         @if(!$hasExtras) hidden @endif
         title="Extras subtotal">
      <span class="quote-totals-figure__label">Extras</span>
      <strong class="quote-totals-figure__val" data-quote-extras>{{ ClientProject::formatMoney($initialExtras) }}</strong>
    </div>

    <div class="quote-totals-figure quote-totals-figure--hst" title="HST">
      <span class="quote-totals-figure__label" data-quote-hst-label>{{ $hstRateLabel }}</span>
      <strong class="quote-totals-figure__val" data-quote-hst>{{ ClientProject::formatMoney($initialHst) }}</strong>
    </div>

    <div class="quote-totals-grand-container">
      <button type="button"
              class="quote-totals-grand br-toolbar-grand"
              id="{{ $grandTotalId ?? 'quote-toolbar-grand' }}"
              data-quote-grand-toggle
              data-totals-grand-toggle
              aria-expanded="false"
              aria-label="Grand total. Click to toggle breakdown details"
              title="Live grand total (incl. HST)">
        <span class="br-toolbar-grand__label">Grand total</span>
        <strong class="br-toolbar-grand__amount"
                id="{{ $grandAmountId ?? 'quote-toolbar-grand-amount' }}"
                data-quote-grand
                data-totals-grand-display>{{ ClientProject::formatMoney($initialGrand) }}</strong>
        <span class="quote-totals-updating" data-quote-updating hidden aria-hidden="true">
          <i class="fa fa-spinner fa-spin"></i> updating…
        </span>
      </button>

      <x-admin.info-tip label="Quotation totals">Totals update live as you edit. Save anytime with Ctrl+S.</x-admin.info-tip>
    </div>

    {{-- Screen-reader polite live announcer --}}
    <div class="sr-only" aria-live="polite" data-quote-live-announcer></div>
  </div>

  {{-- 3. Actions: Badge if sent, Send to client button --}}
  <div class="quote-totals-bar__right">
    <span class="quote-totals-sent-badge" data-quote-sent-badge @if(!$sentAt) hidden @endif>
      <i class="fa fa-check-circle" aria-hidden="true"></i>
      <span>Sent to client on <span data-quote-sent-date>{{ $sentAt ? ClientProject::formatDate($sentAt) : '' }}</span> (<span data-quote-sent-version>v{{ $version }}</span>)</span>
    </span>

    <button type="button"
            class="admin-btn admin-btn--primary admin-btn--sm quote-send-btn"
            data-quote-send-btn
            data-qh-email
            id="quote-send-btn"
            title="Send quotation to client">
      <i class="fa fa-paper-plane" aria-hidden="true"></i> <span>Send to client</span>
    </button>
  </div>
</div>

{{-- Mobile detailed subtotals panel (expanded when tapping Grand Total on <=640px) --}}
<div class="quote-totals-mobile-panel" data-quote-mobile-panel hidden aria-label="Detailed subtotals">
  <div class="quote-totals-mobile-row">
    <span>Materials</span>
    <strong data-quote-mobile-materials>{{ ClientProject::formatMoney($initialMaterials) }}</strong>
  </div>
  <div class="quote-totals-mobile-row">
    <span>Labour</span>
    <strong data-quote-mobile-labour>{{ ClientProject::formatMoney($initialLabour) }}</strong>
  </div>
  <div class="quote-totals-mobile-row" data-quote-mobile-extras-wrap @if(!$hasExtras) hidden @endif>
    <span>Extras</span>
    <strong data-quote-mobile-extras>{{ ClientProject::formatMoney($initialExtras) }}</strong>
  </div>
  <div class="quote-totals-mobile-row">
    <span data-quote-mobile-hst-label>{{ $hstRateLabel }}</span>
    <strong data-quote-mobile-hst>{{ ClientProject::formatMoney($initialHst) }}</strong>
  </div>
</div>

{{-- Send to client confirmation modal --}}
<div class="quote-modal-backdrop" data-quote-modal-backdrop hidden>
  <div class="quote-modal" role="dialog" aria-modal="true" aria-labelledby="quote-modal-title" tabindex="-1" data-quote-modal>
    <div class="quote-modal__header">
      <h3 id="quote-modal-title"><i class="fa fa-paper-plane"></i> Send quotation to client</h3>
      <button type="button" class="quote-modal__close" data-modal-close aria-label="Close modal">&times;</button>
    </div>

    <div class="quote-modal__body">
      {{-- Warning: unsaved changes --}}
      <div class="quote-modal-flash quote-modal-flash--warning" data-modal-unsaved-alert hidden>
        <i class="fa fa-exclamation-triangle"></i>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:100%;">
          <span><strong>Save first?</strong> You have edited fields that are not saved yet. Save your changes before sending this quotation to the client.</span>
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" data-modal-save-btn>
            <i class="fa fa-save" aria-hidden="true"></i> Save quotation
          </button>
        </div>
      </div>

      {{-- Warning: draft / revision --}}
      <div class="quote-modal-flash quote-modal-flash--info" data-modal-draft-alert @if(!$isDraft) hidden @endif>
        <label class="admin-checkbox-label" style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
          <input type="checkbox" data-modal-confirm-draft value="1" style="margin-top:3px;">
          <span><strong>This quotation is currently marked as a draft.</strong> Check this box to confirm publishing and sending this version to the client.</span>
        </label>
      </div>

      {{-- Warning: missing client email --}}
      <div class="quote-modal-flash quote-modal-flash--danger" data-modal-no-email-alert @if(filled($clientEmail)) hidden @endif>
        <i class="fa fa-envelope-o"></i>
        <div>
          The client has no email address.
          @if ($clientEditUrl)
            <a href="{{ $clientEditUrl }}" target="_blank" class="admin-text-link">Edit client record</a> to add an email address.
          @else
            Please link this quotation to a client project with a valid email address.
          @endif
        </div>
      </div>

      {{-- Server error display --}}
      <div class="quote-modal-flash quote-modal-flash--danger" data-modal-error-alert hidden></div>

      <div id="quote-send-modal-form" data-modal-form>
        <div class="admin-form-group" style="margin-bottom:12px;">
          <label class="admin-form-label" for="modal-send-to">To <span class="text-danger">*</span></label>
          <input type="email" id="modal-send-to" class="admin-form-input" data-modal-to value="{{ $clientEmail }}" aria-required="true" placeholder="client@example.com">
        </div>

        <div class="admin-form-group" style="margin-bottom:12px;">
          <label class="admin-form-label" for="modal-send-cc">CC (optional)</label>
          <input type="email" id="modal-send-cc" class="admin-form-input" data-modal-cc value="{{ $clientSecondaryEmail }}" placeholder="secondary@example.com">
        </div>

        <div class="admin-form-group" style="margin-bottom:12px;">
          <label class="admin-form-label" for="modal-send-subject">Subject <span class="text-danger">*</span></label>
          <input type="text" id="modal-send-subject" class="admin-form-input" data-modal-subject value="Your quotation {{ $quoteNumber }} from Zanini Renovations" maxlength="255" aria-required="true">
        </div>

        <div class="admin-form-group" style="margin-bottom:12px;">
          <label class="admin-form-label" for="modal-send-message">Message <span class="text-danger">*</span></label>
          <textarea id="modal-send-message" class="admin-form-input" data-modal-message rows="4" aria-required="true">{{ $defaultMessage }}</textarea>
        </div>

        <div class="admin-form-group" style="margin-bottom:12px;">
          <label class="admin-form-label">Attachment</label>
          <div class="quote-modal-attachment">
            <i class="fa fa-file-pdf-o text-danger" style="font-size:1.3rem;"></i>
            <span class="quote-modal-attachment__name">{{ $quoteNumber }}.pdf</span>
            @if(filled($pdfPreviewUrl))
              <a href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn--ghost admin-btn--sm quote-modal-attachment__link" data-modal-preview-link>
                <i class="fa fa-external-link"></i> Preview
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="quote-modal__footer">
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-modal-close>Cancel</button>
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" data-modal-submit-btn>
        <span data-modal-submit-text><i class="fa fa-paper-plane"></i> Send to client</span>
        <span data-modal-submit-spinner hidden><i class="fa fa-spinner fa-spin"></i> Sending…</span>
      </button>
    </div>
  </div>
</div>
