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
    $invoiceActionsReadOnly - bool (cancelled/rejected: PDF + delete only)
--}}

@php
  use App\Models\ClientProjectInvoice;
  use App\Services\MoceanSmsService;

  $invoiceActionsReadOnly = $invoiceActionsReadOnly ?? false;

  /** @var MoceanSmsService $moceanSms */
  $moceanSms = app(MoceanSmsService::class);

  /** First paid invoice on this project prompts for a project status in the mark-paid modal. */
  $showMarkPaidStatusPicker = $project->invoices->where('status', ClientProjectInvoice::STATUS_PAID)->isEmpty()
      && ! $project->isClosed();
  $moceanReady = $moceanSms->isConfigured();

  // Content/readiness checks shared — email also needs full contact card.
  $needsDueDate = false;
  $contentBlockers = [];
  if ($invoice) {
      $completeItems = array_filter(
          $invoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0)> 0
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

  $needsInvoiceEdit = count($contentBlockers) > 0;
  $needsProjectEdit = count($contactBlockersForEmail) > 0
      || ($invoice && \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) === null);

  $clientEmail = $project->clientEmailRecipientsLabel();
  $clientSms = $project->clientSmsRecipientsLabel();
  $sendConfirm = "This will generate a new PDF, email it to {$clientEmail}, and save it on the server. Continue?";
  $smsConfirm = "Send an SMS to {$clientSms} with a secure link to view or download this invoice PDF?";
  $canReminder = filled(trim((string) $project->client_email)) || $canSms;
  $reminderConfirm = filled(trim((string) $project->client_email))
    ? "Email a payment reminder to {$clientEmail} now?"
    : "Send a payment reminder SMS to {$clientSms} now?";

  $canMarkPaid = false;
  $pauseConfirm = '';
  $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
  if ($invoice) {
      $canMarkPaid = ! $invoice->isPaid() && $invoice->hasContent() && (float) $invoice->total > 0;
      if ($invoice->isSent()) {
          $pauseConfirm = $invoice->dailyRemindersPaused()
              ? "Resume daily payment reminders for invoice {$invoice->invoice_number}? The client will be emailed or texted again each day this invoice stays unpaid past the due date."
              : "Stop daily payment reminders for invoice {$invoice->invoice_number}? The invoice stays on this line. You can still send a one-off reminder, or resume daily reminders later.";
      }
      if ($invoice->wasEverSent()) {
          $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to {$clientEmail}. Deleting it removes the invoice from our records and returns this line to Create Invoice — the payment-plan row stays. The client will still have the old PDF in their inbox. Continue?";
      }
  }
@endphp
@if (!$invoice)
  @unless ($invoiceActionsReadOnly)
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
  @endunless
@elseif ($invoiceActionsReadOnly)
  @php
    $clientEmail = $project->clientEmailRecipientsLabel();
    if ($invoice->wasEverSent()) {
        $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to {$clientEmail}. Deleting it removes the invoice from our records. The client will still have the old PDF in their inbox. Continue?";
    } else {
        $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
    }
    $readOnlyDropdownItems = [];
    if ($invoice->hasContent() || $invoice->total > 0) {
        $readOnlyDropdownItems[] = [
            'type' => 'link',
            'href' => route('admin.client-projects.invoices.pdf', [$project, $invoice]),
            'icon' => 'fa-file-pdf-o',
            'label' => 'PDF',
            'target' => '_blank',
        ];
    }
    if ($invoice->isPaid()) {
        $readOnlyDropdownItems[] = [
            'type' => 'link',
            'href' => route('admin.client-projects.invoices.receipt', [$project, $invoice]),
            'icon' => 'fa-download',
            'label' => 'Receipt',
            'target' => '_blank',
            'class' => 'admin-actions-dropdown__item--success',
        ];
    } else {
        $readOnlyDropdownItems[] = [
            'type' => 'form',
            'action' => route('admin.client-projects.invoices.destroy', [$project, $invoice]),
            'method' => 'DELETE',
            'icon' => 'fa-trash',
            'label' => 'Delete',
            'confirm' => $deleteConfirm,
            'class' => 'admin-actions-dropdown__item--danger',
        ];
    }
  @endphp
  <div class="cp-invoice-actions cp-invoice-actions--readonly admin-table-actions">
    @if ($invoice->hasContent() || $invoice->total > 0)
      <a href="{{ route('admin.client-projects.invoices.pdf', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         aria-label="View invoice PDF">
        <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
        PDF
      </a>
    @endif
    @if ($invoice->isPaid())
      <a href="{{ route('admin.client-projects.invoices.receipt', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--success admin-btn--sm"
         aria-label="Receipt">
        <i class="fa fa-download" aria-hidden="true"></i>
        Receipt
      </a>
    @else
      <form action="{{ route('admin.client-projects.invoices.destroy', [$project, $invoice]) }}"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm({{ json_encode($deleteConfirm) }});">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" aria-label="Delete invoice">
          <i class="fa fa-trash" aria-hidden="true"></i>
          Delete
        </button>
      </form>
    @endif

    @if (count($readOnlyDropdownItems) > 0)
      <div class="admin-actions-dropdown">
        <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions menu" aria-expanded="false" aria-haspopup="menu">
          <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
        </button>
        <div class="admin-actions-dropdown__menu">
          @foreach ($readOnlyDropdownItems as $item)
            @if ($item['type'] === 'link')
              <a href="{{ $item['href'] }}"
                 class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
                 @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif>
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </a>
            @elseif ($item['type'] === 'form')
              <form action="{{ $item['action'] }}"
                    method="post"
                    class="admin-actions-dropdown__form"
                    @if (! empty($item['confirm'])) onsubmit="return confirm({{ json_encode($item['confirm']) }});" @endif>
                @csrf
                @if (! empty($item['method']) && $item['method'] === 'DELETE')
                  @method('DELETE')
                @endif
                <button type="submit" class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}">
                  <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                  {{ $item['label'] }}
                </button>
              </form>
            @endif
          @endforeach
        </div>
      </div>
    @endif
  </div>
@else
  @php
    $canMarkPaid = $invoice->hasContent() && (float) $invoice->total > 0;
    $markPaidTitle = $canMarkPaid
      ? ($invoice->isSent()
          ? 'Mark as paid and generate a receipt.'
          : 'Mark this draft invoice as paid (e.g. cash payment) and generate a receipt.')
      : 'Add at least one line item with a description and amount before marking paid.';
    $pauseConfirm = $invoice->dailyRemindersPaused()
      ? "Resume daily payment reminders for invoice {$invoice->invoice_number}? The client will be emailed or texted again each day this invoice stays unpaid past the due date."
      : "Stop daily payment reminders for invoice {$invoice->invoice_number}? The invoice stays on this line. You can still send a one-off reminder, or resume daily reminders later.";
    if ($invoice->wasEverSent()) {
        $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to {$clientEmail}. Deleting it removes the invoice from our records and returns this line to Create Invoice — the payment-plan row stays. The client will still have the old PDF in their inbox. Continue?";
    } else {
        $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
    }

    $invoiceDropdownItems = [];
    if (! $invoice->isPaid()) {
        $invoiceDropdownItems[] = [
            'type' => 'link',
            'href' => route('admin.client-projects.invoices.edit', [$project, $invoice]),
            'icon' => 'fa-pencil',
            'label' => 'Edit',
        ];
    }
    if ($invoice->hasContent() || $invoice->total > 0) {
        $invoiceDropdownItems[] = [
            'type' => 'link',
            'href' => route('admin.client-projects.invoices.pdf', [$project, $invoice]),
            'icon' => 'fa-file-pdf-o',
            'label' => 'PDF',
            'target' => '_blank',
        ];
    }
    if (! $invoice->isPaid()) {
        if ($canSend) {
            $invoiceDropdownItems[] = [
                'type' => 'form',
                'action' => route('admin.client-projects.invoices.send', [$project, $invoice]),
                'icon' => 'fa-paper-plane',
                'label' => $invoice->wasEverSent() ? 'Resend' : 'Send',
                'confirm' => $sendConfirm,
            ];
        }
        if ($canSms) {
            $invoiceDropdownItems[] = [
                'type' => 'form',
                'action' => route('admin.client-projects.invoices.sms', [$project, $invoice]),
                'icon' => 'fa-comment',
                'label' => 'SMS',
                'confirm' => $smsConfirm,
            ];
        }
    }
    if ($invoice->isSent()) {
        if ($canReminder) {
            $invoiceDropdownItems[] = [
                'type' => 'form',
                'action' => route('admin.client-projects.invoices.reminder', [$project, $invoice]),
                'icon' => 'fa-bell-o',
                'label' => $invoice->dailyRemindersPaused() ? 'Send one reminder' : 'Reminder',
                'confirm' => $reminderConfirm,
            ];
        }
        $invoiceDropdownItems[] = [
            'type' => 'form',
            'action' => route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]),
            'icon' => $invoice->dailyRemindersPaused() ? 'fa-play' : 'fa-pause',
            'label' => $invoice->dailyRemindersPaused() ? 'Resume reminders' : 'Stop reminders',
            'confirm' => $pauseConfirm,
        ];
    }
    if (! $invoice->isPaid() && $canMarkPaid) {
        $invoiceDropdownItems[] = [
            'type' => 'markpaid',
            'icon' => 'fa-check',
            'label' => 'Mark paid',
            'url' => route('admin.client-projects.invoices.mark-paid', [$project, $invoice]),
            'amount' => number_format((float) $invoice->total, 2, '.', ''),
            'invoiceLabel' => 'Invoice #' . $invoice->invoice_number,
            'firstPaid' => $showMarkPaidStatusPicker ? '1' : '0',
        ];
    }
    if ($invoice->isPaid()) {
        $invoiceDropdownItems[] = [
            'type' => 'link',
            'href' => route('admin.client-projects.invoices.receipt', [$project, $invoice]),
            'icon' => 'fa-download',
            'label' => 'Receipt',
            'target' => '_blank',
            'class' => 'admin-actions-dropdown__item--success',
        ];
    }
    if (! $invoice->isPaid()) {
        $invoiceDropdownItems[] = [
            'type' => 'form',
            'action' => route('admin.client-projects.invoices.destroy', [$project, $invoice]),
            'method' => 'DELETE',
            'icon' => 'fa-trash',
            'label' => 'Delete',
            'confirm' => $deleteConfirm,
            'class' => 'admin-actions-dropdown__item--danger',
        ];
    }
  @endphp
  <div class="cp-invoice-actions admin-table-actions">
    {{-- Edit (hidden on paid invoices; view a read-only version via PDF) --}}
    @unless ($invoice->isPaid())
      <a href="{{ route('admin.client-projects.invoices.edit', [$project, $invoice]) }}"
         class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn"
         aria-label="Edit">
        <i class="fa fa-pencil" aria-hidden="true"></i>
        <span class="cp-action-tooltip">Edit</span>
      </a>
    @endunless

    {{-- PDF (always available if there's something to render) --}}
    @if ($invoice->hasContent() || $invoice->total> 0)
      <a href="{{ route('admin.client-projects.invoices.pdf', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn"
         aria-label="PDF">
        <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
        <span class="cp-action-tooltip">PDF</span>
      </a>
    @endif

    {{-- Send / Resend (draft or sent state) --}}
    @unless ($invoice->isPaid())
      @if ($canSend)
        <form action="{{ route('admin.client-projects.invoices.send', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm({{ json_encode($sendConfirm) }});">
          @csrf
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm cp-action-icon-btn" aria-label="{{ $invoice->wasEverSent() ? 'Resend' : 'Send' }}">
            <i class="fa fa-paper-plane" aria-hidden="true"></i>
            <span class="cp-action-tooltip">{{ $invoice->wasEverSent() ? 'Resend' : 'Send' }}</span>
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--primary admin-btn--sm cp-action-icon-btn"
                disabled
                aria-label="Send">
          <i class="fa fa-paper-plane" aria-hidden="true"></i>
          <span class="cp-action-tooltip">Send</span>
        </button>
      @endif

      @if ($canSms)
        <form action="{{ route('admin.client-projects.invoices.sms', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm({{ json_encode($smsConfirm) }});">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn" aria-label="SMS">
            <i class="fa fa-comment" aria-hidden="true"></i>
            <span class="cp-action-tooltip">SMS</span>
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn"
                disabled
                aria-label="SMS">
          <i class="fa fa-comment" aria-hidden="true"></i>
          <span class="cp-action-tooltip">SMS</span>
        </button>
      @endif
    @endunless

    {{-- Reminder (only once sent, and not paid) --}}
    @if ($invoice->isSent())
      @if ($canReminder)
        <form action="{{ route('admin.client-projects.invoices.reminder', [$project, $invoice]) }}"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm({{ json_encode($reminderConfirm) }});">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn" aria-label="Send reminder">
            <i class="fa fa-bell-o" aria-hidden="true"></i>
            <span class="cp-action-tooltip">{{ $invoice->dailyRemindersPaused() ? 'Send one reminder' : 'Reminder' }}</span>
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn"
                disabled
                aria-label="Send reminder">
          <i class="fa fa-bell-o" aria-hidden="true"></i>
          <span class="cp-action-tooltip">Reminder</span>
        </button>
      @endif

      @php
        $pauseConfirm = $invoice->dailyRemindersPaused()
          ? "Resume daily payment reminders for invoice {$invoice->invoice_number}? The client will be emailed or texted again each day this invoice stays unpaid past the due date."
          : "Stop daily payment reminders for invoice {$invoice->invoice_number}? The invoice stays on this line. You can still send a one-off reminder, or resume daily reminders later.";
      @endphp
      <form action="{{ route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]) }}"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm({{ json_encode($pauseConfirm) }});">
        @csrf
        @if ($invoice->dailyRemindersPaused())
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn" aria-label="Resume daily reminders">
            <i class="fa fa-play" aria-hidden="true"></i>
            <span class="cp-action-tooltip">Resume daily reminders</span>
          </button>
        @else
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm cp-action-icon-btn" aria-label="Stop daily reminders">
            <i class="fa fa-pause" aria-hidden="true"></i>
            <span class="cp-action-tooltip">Stop daily reminders</span>
          </button>
        @endif
      </form>
    @endif

    {{-- Mark as paid — available on any non-paid invoice with content,
         even drafts. Useful when the client paid in person/cash and we
         skip the email/SMS step. We still require line items + a total so
         the receipt PDF has something meaningful to render. --}}
    @unless ($invoice->isPaid())
      @php
        $canMarkPaid = $invoice->hasContent() && (float) $invoice->total> 0;
        $markPaidTitle = $canMarkPaid
          ? ($invoice->isSent()
              ? 'Mark as paid and generate a receipt.'
              : 'Mark this draft invoice as paid (e.g. cash payment) and generate a receipt.')
          : 'Add at least one line item with a description and amount before marking paid.';
      @endphp
      @if ($canMarkPaid)
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm cp-action-icon-btn"
                data-cp-markpaid-url="{{ route('admin.client-projects.invoices.mark-paid', [$project, $invoice]) }}"
                data-cp-markpaid-amount="{{ number_format((float) $invoice->total, 2, '.', '') }}"
                data-cp-markpaid-label="Invoice #{{ $invoice->invoice_number }}"
                data-cp-markpaid-first-paid="{{ $showMarkPaidStatusPicker ? '1' : '0' }}"
                aria-label="Mark paid">
          <i class="fa fa-check" aria-hidden="true"></i>
          <span class="cp-action-tooltip">Mark paid</span>
        </button>
      @else
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm cp-action-icon-btn"
                disabled
                aria-label="Mark paid">
          <i class="fa fa-check" aria-hidden="true"></i>
          <span class="cp-action-tooltip">Mark paid</span>
        </button>
      @endif
    @endunless

    {{-- Receipt download (only once paid) --}}
    @if ($invoice->isPaid())
      <a href="{{ route('admin.client-projects.invoices.receipt', [$project, $invoice]) }}"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--success admin-btn--sm cp-action-icon-btn"
         aria-label="Receipt">
        <i class="fa fa-download" aria-hidden="true"></i>
        <span class="cp-action-tooltip">Receipt</span>
      </a>
    @endif

    {{-- Delete — allowed for any non-paid invoice. Sent invoices get a
         stronger confirmation dialog because the client already has the
         emailed PDF; deleting on our end just lets the admin start the
         installment over (e.g. fix a wrong total, regenerate the number). --}}
    @unless ($invoice->isPaid())
      @php
        if ($invoice->wasEverSent()) {
            $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to {$clientEmail}. Deleting it removes the invoice from our records and returns this line to Create Invoice — the payment-plan row stays. The client will still have the old PDF in their inbox. Continue?";
        } else {
            $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
        }
      @endphp
      <form action="{{ route('admin.client-projects.invoices.destroy', [$project, $invoice]) }}"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm({{ json_encode($deleteConfirm) }});">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm cp-action-icon-btn" aria-label="Delete">
          <i class="fa fa-trash" aria-hidden="true"></i>
          <span class="cp-action-tooltip">Delete</span>
        </button>
      </form>
    @endunless

    {{-- Mobile actions dropdown --}}
    @if (count($invoiceDropdownItems) > 0)
      <div class="admin-actions-dropdown">
        <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions menu" aria-expanded="false" aria-haspopup="menu">
          <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
        </button>
        <div class="admin-actions-dropdown__menu">
          @foreach ($invoiceDropdownItems as $item)
            @if ($item['type'] === 'link')
              <a href="{{ $item['href'] }}"
                 class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
                 @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif>
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </a>
            @elseif ($item['type'] === 'form')
              <form action="{{ $item['action'] }}"
                    method="post"
                    class="admin-actions-dropdown__form"
                    @if (! empty($item['confirm'])) onsubmit="return confirm({{ json_encode($item['confirm']) }});" @endif>
                @csrf
                @if (! empty($item['method']) && $item['method'] === 'DELETE')
                  @method('DELETE')
                @endif
                <button type="submit" class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}">
                  <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                  {{ $item['label'] }}
                </button>
              </form>
            @elseif ($item['type'] === 'markpaid')
              <button type="button"
                      class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
                      data-cp-markpaid-url="{{ $item['url'] }}"
                      data-cp-markpaid-amount="{{ $item['amount'] }}"
                      data-cp-markpaid-label="{{ $item['invoiceLabel'] }}"
                      data-cp-markpaid-first-paid="{{ $item['firstPaid'] }}">
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </button>
            @endif
          @endforeach
        </div>
      </div>
    @endif
  </div>
@endif
