@php
  $due = $invoice->due_on
    ? \Illuminate\Support\Carbon::parse($invoice->due_on)
    : null;
  $overdue = $due && $due->isPast();
@endphp

@component('emails.client_projects.layout', ['title' => 'Reminder: Invoice #'.$invoice->invoice_number])
  <p>Hi {{ $project->client_name }},</p>

  @if ($project->usesInvoiceBillingOverride())
    <p style="font-size:14px;color:#374151;">
      That invoice is addressed to <strong>{{ $project->invoiceBillToName() }}</strong> on the PDF.
    </p>
  @endif

  <p>
    Just a friendly reminder that <strong>Invoice #{{ $invoice->invoice_number }}</strong>
    @if (!$invoice->isManual())
      for the <strong>{{ $invoice->installmentTitle() }}</strong>
    @endif
    on your <strong>{{ $project->name }}</strong> project is
    @if ($overdue)
      currently <strong style="color:#b91c1c;">past its due date</strong> ({{ \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on) }}).
    @elseif ($due)
      due on <strong>{{ \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on) }}</strong>.
    @else
      still outstanding.
    @endif
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 6px 12px; background: #f4fafe; border: 1px solid #024768; font-size: 15px;">
        <strong>Amount due:</strong> {{ \App\Models\ClientProject::formatMoney($invoice->total) }}
      </td>
    </tr>
  </table>

  <p>
    I've reattached the original invoice to this message for your convenience.
    Please pay via e-Transfer to
    <a href="mailto:zaninirenovations@gmail.com"><strong>zaninirenovations@gmail.com</strong></a>
    and include invoice number <strong>{{ $invoice->invoice_number }}</strong>
    in the message.
  </p>

  <p>If the payment has already been sent, please disregard this reminder. If you have any questions or need to discuss, just reply to this email.</p>

  <p>Thank you!</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
