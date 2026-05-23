{{--
  Quotation action buttons (Edit, PDF, Send, Delete).
  Expects: $project, $quotation, $legacy, $hasContent, $canSend,
  $sendConfirmMessage, $deleteConfirm, $missingDelivery (optional),
  $canDeliverByEmail, $canDeliverBySms
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
    </button>
  @endif

  @if ($canSend && ! $legacy)
    <form action="{{ route('admin.client-projects.quotation.send', [$project, $quotation]) }}"
          method="post" class="cp-inline-form cp-quotation-toolbar__send-form"
          onsubmit='return confirm(@json($sendConfirmMessage));'>
      @csrf
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-paper-plane"></i> {{ $quotation->isSent() ? 'Resend' : 'Send' }}
      </button>
    </form>
  @else
    <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
            title="{{ $legacy ? 'Legacy document — not sendable from this flow.' : (($canDeliverByEmail || $canDeliverBySms) ? 'Add at least one line item first.' : 'Add at least one delivery channel: '.implode(', ', $missingDelivery ?? [])) }}">
      <i class="fa fa-paper-plane"></i> Send
    </button>
  @endif

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
