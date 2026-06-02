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
  use App\Models\ClientProjectInvoice;
  use App\Services\MoceanSmsService;

  /** @var MoceanSmsService $moceanSms */
  $moceanSms = app(MoceanSmsService::class);

  /** First paid invoice on this project prompts for a project status in the mark-paid modal. */
  $showMarkPaidStatusPicker = $project->invoices->where('status', ClientProjectInvoice::STATUS_PAID)->isEmpty()
      && ! $project->isRejected();
  $moceanReady = $moceanSms->isConfigured();

  // Content/readiness checks shared — email also needs full contact card.
  $needsDueDate = false;
  $contentBlockers = [];
  if ($invoice) {
      $completeItems = array_filter(
          $invoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
      );
      if (count($completeItems) === 0) {
          $contentBlockers[] = 'Add at least one line item with a description and amount.';
      }
      $needsDueDate = empty($invoice->due_on);
      if ($needsDueDate) {
          $contentBlockers[] = 'Set a payment due date.';
      }
  }

  // Email only needs a recipient email address; SMS only needs a phone.
  // The two delivery channels are independent — a missing phone must not
  // disable email (and vice-versa).
  $contactBlockersForEmail = [];
  if (blank(trim((string) $project->client_email))) {
      $contactBlockersForEmail[] = "Add the client's email address.";
  }

  $sendBlockers = array_merge($contactBlockersForEmail, $contentBlockers);
  $canSend = empty($sendBlockers);
  $sendTitle = $canSend
    ? 'Generate a fresh PDF and email it to the client.'
    : implode(' ', $sendBlockers);

  $smsBlockers = $contentBlockers;
  if (!$moceanReady) {
      $smsBlockers[] = 'SMS gateway is not configured on the server (MOCEAN_API_TOKEN).';
  }
  if ($invoice && \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) === null) {
      $smsBlockers[] = "Add the client's mobile phone number.";
  }
  $canSms = empty($smsBlockers);
  $smsTitle = $canSms
    ? 'Text the client a secure link to open this invoice PDF in the browser.'
    : implode(' ', $smsBlockers);

  /** @var list<string> */
  $actionBlockers = array_values(array_unique(array_merge($sendBlockers, $smsBlockers)));
  $actionBlockersForDisplay = array_values(array_filter(
      $actionBlockers,
      fn (string $hint) => ! str_contains(strtolower($hint), 'payment due date')
  ));
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
  @if (!$invoice->isPaid() && (! $canSend || ! $canSms))
    <div class="cp-contract-warning cp-invoice-send-warning">
      <i class="fa fa-exclamation-triangle"></i>
      <div class="cp-invoice-send-warning-body">
        @php
          $disabledChannels = [];
          if (! $canSend) { $disabledChannels[] = 'Email'; }
          if (! $canSms) { $disabledChannels[] = 'SMS'; }
        @endphp
        <p class="cp-invoice-send-warning-lead">
          <strong>{{ implode(' and ', $disabledChannels) }} disabled.</strong> Address the items below:
        </p>
        <ul class="cp-invoice-send-blockers">
          @if ($needsDueDate)
            <li>Set a <strong>payment due date</strong></li>
          @endif
          @foreach ($actionBlockersForDisplay as $hint)
            <li>{{ $hint }}</li>
          @endforeach
        </ul>
        <p class="cp-invoice-send-warning-actions">
          <a href="{{ route('admin.client-projects.edit', $project) }}"
             class="admin-btn admin-btn--ghost admin-btn--sm cp-invoice-send-warning-link">
            <i class="fa fa-pencil"></i> Edit project info
          </a>
        </p>
      </div>
    </div>
  @endif
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

      @if ($canSms)
        <form action="{{ route('admin.client-projects.invoices.sms', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('Send an SMS to the client with a secure link to view or download this invoice PDF?');">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="{{ $smsTitle }}">
            <i class="fa fa-comment"></i> SMS
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                disabled
                title="{{ $smsTitle }}">
          <i class="fa fa-comment"></i> SMS
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

    {{-- Mark as paid — available on any non-paid invoice with content,
         even drafts. Useful when the client paid in person/cash and we
         skip the email/SMS step. We still require line items + a total so
         the receipt PDF has something meaningful to render. --}}
    @unless ($invoice->isPaid())
      @php
        $canMarkPaid = $invoice->hasContent() && (float) $invoice->total > 0;
        $markPaidTitle = $canMarkPaid
          ? ($invoice->isSent()
              ? 'Mark as paid and generate a receipt.'
              : 'Mark this draft invoice as paid (e.g. cash payment) and generate a receipt.')
          : 'Add at least one line item with a description and amount before marking paid.';
      @endphp
      @if ($canMarkPaid)
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm"
                data-cp-markpaid-url="{{ route('admin.client-projects.invoices.mark-paid', [$project, $invoice]) }}"
                data-cp-markpaid-amount="{{ number_format((float) $invoice->total, 2, '.', '') }}"
                data-cp-markpaid-label="Invoice #{{ $invoice->invoice_number }}"
                data-cp-markpaid-first-paid="{{ $showMarkPaidStatusPicker ? '1' : '0' }}"
                title="{{ $markPaidTitle }}">
          <i class="fa fa-check"></i> Mark paid
        </button>
      @else
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm"
                disabled
                title="{{ $markPaidTitle }}">
          <i class="fa fa-check"></i> Mark paid
        </button>
      @endif
    @endunless

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

    {{-- Delete — allowed for any non-paid invoice. Sent invoices get a
         stronger confirmation dialog because the client already has the
         emailed PDF; deleting on our end just lets the admin start the
         installment over (e.g. fix a wrong total, regenerate the number). --}}
    @unless ($invoice->isPaid())
      @php
        if ($invoice->wasEverSent()) {
            $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to the client. Deleting it here will REMOVE it from our records and let you create a fresh invoice for this installment, but the client will still have the old PDF in their inbox. You should let them know to disregard the old one. Continue?";
            $deleteTitle = 'Delete this sent invoice and start the installment over.';
        } else {
            $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
            $deleteTitle = 'Delete this draft invoice.';
        }
      @endphp
      <form action="{{ route('admin.client-projects.invoices.destroy', [$project, $invoice]) }}"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm({{ json_encode($deleteConfirm) }});">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="{{ $deleteTitle }}">
          <i class="fa fa-trash"></i>
        </button>
      </form>
    @endunless
  </div>
@endif
