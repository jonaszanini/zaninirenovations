{{--
  Collapsible "Client email message" panel for every quotation type.
  Posts `client_email_message` (rich HTML) with the quotation form; saved by
  App\Models\Concerns\HasClientEmailMessage and shown in the client quotation email.

  @param object|null  $quotation
  @param string       $formId      id of the quotation <form> (panel may sit outside it)
--}}
@php
  $quotation = $quotation ?? null;
  $formId = $formId ?? '';
  $fieldId = ($formId !== '' ? $formId : 'quotation').'-client-email-message';
  $value = old('client_email_message', $quotation->client_email_message ?? '');
  $hasValue = trim(strip_tags((string) $value)) !== '';
  $historyKey = 'quotation-client-email:'.($quotation ? class_basename($quotation).':'.$quotation->getKey() : md5(request()->path()));
@endphp

<details class="admin-accordion admin-card cp-client-email-card"
         data-client-email-message
         data-textarea-id="{{ $fieldId }}"
         data-form-id="{{ $formId }}"
         data-improve-url="{{ route('admin.quotations.client-email-message.ai') }}"
         data-csrf="{{ csrf_token() }}"
         data-history-key="{{ $historyKey }}"
         @if ($hasValue || $errors->has('client_email_message')) open @endif>
  <summary class="admin-accordion__summary">
    <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
    <span class="admin-accordion__title">
      Client Email Message
      <x-admin.info-tip>Optional. When filled in, this opens the client email after the greeting. The usual “pleasure discussing” line is left out, and the PDF sentence follows your message. Not shown on the PDF.</x-admin.info-tip>
    </span>
    @if ($hasValue)
      <span class="cp-client-email-card__badge" title="A message will be added to the client email">
        <i class="fa fa-envelope" aria-hidden="true"></i>
      </span>
    @endif
  </summary>
  <div class="admin-accordion__panel">
    <textarea class="admin-input cp-client-email-card__input"
              id="{{ $fieldId }}"
              name="client_email_message"
              @if ($formId !== '') form="{{ $formId }}" @endif
              rows="6"
              maxlength="{{ \App\Support\QuotationClientEmailMessage::MAX_LENGTH }}"
              placeholder="e.g. As discussed, we can start the week of October 12. **Materials** are included.">{{ $value }}</textarea>
    @error('client_email_message')
      <p class="admin-form-error">{{ $message }}</p>
    @enderror

    <div class="cp-client-email-card__actions">
      @component('admin.partials.ai_action_group', [
        'instructionKey' => \App\Models\AiInstruction::KEY_QUOTATION_CLIENT_EMAIL_MESSAGE,
        'instructionLabel' => 'Edit client email message prompt',
        'showHistory' => true,
      ])
        <button type="button" class="admin-btn admin-btn--primary" data-client-email-ai>
          <i class="fa fa-magic" aria-hidden="true"></i>
          <span>Improve with AI</span>
        </button>
      @endcomponent
      <span class="nb2-loading cp-client-email-card__loading" data-client-email-loading hidden aria-live="polite">
        <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
        <span>Refining…</span>
      </span>
    </div>
    <p class="nb2-status cp-client-email-card__status" data-client-email-status role="status"></p>
  </div>
</details>

@include('admin.partials.ai_text_history_assets')
@once
  @push('scripts')
    <script src="{{ asset('js/admin-quotation-client-email.js') }}?v={{ @filemtime(public_path('js/admin-quotation-client-email.js')) ?: time() }}"></script>
  @endpush
  <style>
    .cp-client-email-card { display: block; margin: 0 0 16px; }
    .cp-client-email-card > .admin-accordion__summary { min-height: 44px; cursor: pointer; }
    .cp-client-email-card:not([open]) > .admin-accordion__panel { display: none; }
    .cp-client-email-card__badge { margin-left: auto; color: var(--admin-primary, #024768); font-size: 13px; }
    .cp-client-email-card__help { margin: 0 0 10px; }
    .cp-client-email-card__input { width: 100%; min-height: 140px; }
    .cp-client-email-card__actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px 12px;
      margin-top: 10px;
    }
    .cp-client-email-card__status { margin: 6px 0 0; }
    .cp-client-email-card .tox-tinymce { border-radius: 8px; }
  </style>
@endonce
