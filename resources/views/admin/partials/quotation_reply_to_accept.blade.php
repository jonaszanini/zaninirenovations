{{--
  Required Yes/No: ask the client to reply "I accept" to the quotation email.
  No default — neither radio is selected until the admin chooses.

  @param object|null  $quotation
  @param string       $formId
--}}
@php
  $quotation = $quotation ?? null;
  $formId = $formId ?? '';
  $field = \App\Support\QuotationReplyToAccept::FIELD;
  $choice = \App\Support\QuotationReplyToAccept::parse(old($field, $quotation->{$field} ?? null));
  $hasError = $errors->has($field);
@endphp

<div class="admin-card cp-reply-to-accept @if ($hasError) cp-reply-to-accept--error @endif">
  <fieldset class="cp-reply-to-accept__fieldset">
    <legend class="cp-reply-to-accept__legend">
      Ask client to reply “I accept” to this email
      <span class="cp-required">*</span>
      <x-admin.info-tip label="Ask client to reply I accept">
        Required. Pick Yes or No before saving or emailing. When Yes, the email asks the client to reply “I accept”.
      </x-admin.info-tip>
    </legend>
    <div class="cp-reply-to-accept__choices">
      <label class="cp-checkbox-row cp-reply-to-accept__choice">
        <input type="radio"
               name="{{ $field }}"
               value="1"
               @if ($formId !== '') form="{{ $formId }}" @endif
               @checked($choice === true)>
        <span>Yes</span>
      </label>
      <label class="cp-checkbox-row cp-reply-to-accept__choice">
        <input type="radio"
               name="{{ $field }}"
               value="0"
               @if ($formId !== '') form="{{ $formId }}" @endif
               @checked($choice === false)>
        <span>No</span>
      </label>
    </div>
    @if ($hasError)
      <p class="admin-form-error" role="alert">{{ $errors->first($field) }}</p>
    @endif
  </fieldset>
</div>
