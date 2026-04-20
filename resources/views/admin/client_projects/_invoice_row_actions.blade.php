{{--
  Per-row invoice actions. Keeps the table markup compact and the
  button logic in one place for both the installment table and the
  manual invoices table.

  Variables:
    $project            - ClientProject
    $invoice            - ClientProjectInvoice|null (null = no invoice yet)
    $installmentNumber  - int|null (null = manual invoice / creation for installment N)
    $hasContactInfo     - bool
    $missing            - array (labels of missing client fields)
--}}

@php
  // Build a list of reasons the invoice can't be sent yet. The server
  // enforces these too, but surfacing them up-front stops the user from
  // clicking into the editor just to see the error.
  $sendBlockers = [];
  if (!$hasContactInfo) {
      $sendBlockers[] = 'Missing client info: '.implode(', ', $missing);
  }
  if ($invoice) {
      $completeItems = array_filter(
          $invoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
      );
      if (count($completeItems) === 0) {
          $sendBlockers[] = 'Add at least one line item with a description and amount.';
      }
      if (empty($invoice->due_on)) {
          $sendBlockers[] = 'Set a due date in the editor.';
      }
  }

  $canSend = empty($sendBlockers);
  $sendTitle = $canSend
    ? 'Generate a fresh PDF and email it to the client.'
    : implode(' ', $sendBlockers);
@endphp

@if (!$invoice)
  {{-- No invoice yet for this installment → offer to create one. --}}
  <form action="{{ route('admin.client-projects.invoices.create', $project) }}"
        method="post"
        class="cp-inline-form">
    @csrf
    <input type="hidden" name="mode" value="installment">
    <input type="hidden" name="installment_number" value="{{ $installmentNumber }}">
    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> Create Invoice
    </button>
  </form>
@else
  <div class="cp-invoice-actions">
    {{-- Edit (hidden on paid invoices; view a read-only version via PDF) --}}
    @unless ($invoice->isPaid())
      <a href="{{ route('admin.client-projects.invoices.edit', [$project, $invoice]) }}"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         title="Open the invoice editor">
        <i class="fa fa-pencil"></i> Edit
      </a>
    @endunless

    {{-- PDF (always available if there's something to render) --}}
    @if ($invoice->hasContent() || $invoice->total > 0)
      <a href="{{ route('admin.client-projects.invoices.pdf', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         title="Open the invoice PDF">
        <i class="fa fa-file-pdf-o"></i> PDF
      </a>
    @endif

    {{-- Send / Resend (draft or sent state) --}}
    @unless ($invoice->isPaid())
      @if ($canSend)
        <form action="{{ route('admin.client-projects.invoices.send', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('This will generate a new PDF, email it to the client, and save it on the server. Continue?');">
          @csrf
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" title="{{ $sendTitle }}">
            <i class="fa fa-paper-plane"></i>
            {{ $invoice->wasEverSent() ? 'Resend' : 'Send' }}
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--primary admin-btn--sm"
                disabled
                title="{{ $sendTitle }}">
          <i class="fa fa-paper-plane"></i> Send
        </button>
      @endif
    @endunless

    {{-- Reminder (only once sent, and not paid) --}}
    @if ($invoice->isSent())
      @if ($hasContactInfo)
        <form action="{{ route('admin.client-projects.invoices.reminder', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('Email a payment reminder to the client now?');">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Email a payment reminder to the client.">
            <i class="fa fa-bell-o"></i> Reminder
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                disabled
                title="{{ $sendTitle }}">
          <i class="fa fa-bell-o"></i> Reminder
        </button>
      @endif
    @endif

    {{-- Mark as paid (only once sent) --}}
    @if ($invoice->isSent())
      <button type="button"
              class="admin-btn admin-btn--success admin-btn--sm"
              data-cp-markpaid-url="{{ route('admin.client-projects.invoices.mark-paid', [$project, $invoice]) }}"
              data-cp-markpaid-amount="{{ number_format((float) $invoice->total, 2, '.', '') }}"
              data-cp-markpaid-label="Invoice #{{ $invoice->invoice_number }}"
              title="Mark as paid and generate a receipt.">
        <i class="fa fa-check"></i> Mark paid
      </button>
    @endif

    {{-- Receipt download (only once paid) --}}
    @if ($invoice->isPaid())
      <a href="{{ route('admin.client-projects.invoices.receipt', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--success admin-btn--sm"
         title="Open the receipt PDF.">
        <i class="fa fa-download"></i> Receipt
      </a>
    @endif

    {{-- Delete (only while draft, never once emailed) --}}
    @if ($invoice->isDraft() && !$invoice->wasEverSent())
      <form action="{{ route('admin.client-projects.invoices.destroy', [$project, $invoice]) }}"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm('Delete this draft invoice? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Delete this draft invoice.">
          <i class="fa fa-trash"></i>
        </button>
      </form>
    @endif
  </div>
@endif
