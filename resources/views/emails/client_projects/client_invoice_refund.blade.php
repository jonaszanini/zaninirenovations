@component('emails.client_projects.layout', ['title' => 'Refund — Invoice #'.$invoice->invoice_number])
  <p>Hi {{ $project->client_name }},</p>

  <p>
    A refund has been issued on your <strong>{{ $project->name }}</strong> project
    for <strong>Invoice #{{ $invoice->invoice_number }}</strong>
    @if (! $invoice->isManual())
      ({{ $invoice->installmentTitle() }})
    @endif.
    The refund receipt is attached.
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 10px 14px; background: #fef2f2; border: 1px solid #dc2626; font-size: 15px; color:#b91c1c;">
        <strong>Amount refunded:</strong>
        {{ \App\Models\ClientProject::formatMoney($refundedNow) }}
        @if ($invoice->refunded_at)
          &nbsp;·&nbsp; <strong>Refunded on:</strong> {{ \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($invoice->refunded_at) }}
        @endif
      </td>
    </tr>
  </table>

  <p>
    If you have any questions about this refund, reply to this email and I'll be happy to help.
  </p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
