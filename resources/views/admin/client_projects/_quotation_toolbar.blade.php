{{--
  Quotation action buttons (Edit, PDF, Send, SMS, Copy link, Delete).
  Expects: $project, $quotation, $legacy, $hasContent, $canSend, $canSms,
  $smsTitle, $shareLinkUrl, $sendConfirmMessage, $deleteConfirm,
  $missingDelivery (optional), $canDeliverByEmail, $canDeliverBySms
--}}
<div class="cp-quotation-toolbar">
  @unless ($legacy)
    <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
       class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-pencil"></i> Edit
    </a>
  @endunless

  @if ($legacy)
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Read-only legacy document.">
      <i class="fa fa-ban"></i> Read-only
    </button>
  @endif

  @if ($hasContent)
    <a href="{{ route('admin.client-projects.quotation.pdf', [$project, $quotation]) }}"
       target="_blank" rel="noopener"
       class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-download"></i> PDF
    </a>
  @else
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before PDF is available.">
      <i class="fa fa-download"></i> PDF
    </a>
  @endif

  @if ($canSend && ! $legacy)
    <form action="{{ route('admin.client-projects.quotation.send', [$project, $quotation]) }}"
          method="post" class="cp-inline-form cp-quotation-toolbar__send-form"
          onsubmit='return confirm(@json($sendConfirmMessage));'>
      @csrf
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" title="Generate a fresh PDF and email it to the client.">
        <i class="fa fa-paper-plane"></i> {{ $quotation->isSent() ? 'Resend' : 'Send' }}
      </button>
    </form>
  @else
  @php
    $sendDisabledTitle = $legacy
        ? 'Legacy document — not sendable from this flow.'
        : (! $canDeliverByEmail
            ? "Add the client's email address to email this quotation. Use SMS or Copy link if you only have a phone."
            : 'Add at least one line item first.');
  @endphp
    <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
            title="{{ $sendDisabledTitle }}">
      <i class="fa fa-paper-plane"></i> Send
    </button>
  @endif

  @unless ($legacy)
    @if ($canSms)
      <form action="{{ route('admin.client-projects.quotation.sms', [$project, $quotation]) }}"
            method="post" class="cp-inline-form"
            onsubmit="return confirm('Send an SMS to the client with a secure link to view or download this quotation PDF?');">
        @csrf
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="{{ $smsTitle }}">
          <i class="fa fa-comment"></i> SMS
        </button>
      </form>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="{{ $smsTitle }}">
        <i class="fa fa-comment"></i> SMS
      </button>
    @endif

    @if ($shareLinkUrl)
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-copy-link"
              data-cp-copy-quotation-link
              data-copy-url="{{ $shareLinkUrl }}"
              title="Copy a secure link to share via Messenger, Instagram, etc.">
        <i class="fa fa-link"></i> Link
      </button>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before a share link is available.">
        <i class="fa fa-link"></i> Link
      </button>
    @endif
  @endunless

  @unless ($legacy)
    <form action="{{ route('admin.client-projects.quotation.destroy', [$project, $quotation]) }}"
          method="post" class="cp-inline-form"
          onsubmit='return confirm(@json($deleteConfirm));'>
      @csrf
      @method('DELETE')
      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Remove this document from the project and delete the linked calculator quote.">
        <i class="fa fa-trash"></i> Delete
      </button>
    </form>
  @endunless
</div>
