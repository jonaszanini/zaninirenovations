@component('emails.client_projects.layout', ['title' => 'Receipt — Invoice #'.$invoice->invoice_number])
  <p>Hi {{ $project->client_name }},</p>

  <p>
    Thank you for your prompt payment on your <strong>{{ $project->name }}</strong> project!
    Please find attached the official receipt for <strong>Invoice #{{ $invoice->invoice_number }}</strong>
    @if (!$invoice->isManual())
      ({{ $invoice->installmentTitle() }})
    @endif.
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 10px 14px; background: #e8f8ef; border: 1px solid #22c55e; font-size: 15px; color:#15803d;">
        <strong>Amount received:</strong>
        {{ \App\Models\ClientProject::formatMoney($invoice->paid_amount ?? $invoice->total) }}
        @if ($invoice->paid_at)
          &nbsp;·&nbsp; <strong>Received on:</strong> {{ $invoice->paid_at->format('F j, Y') }}
        @endif
      </td>
    </tr>
  </table>

  <p>
    If you need the receipt in a different format, or have any questions, just reply
    to this email and I'll be happy to help.
  </p>

  <p>Thanks again for trusting Zanini Renovations with your project.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovation.ca">zaninirenovation.ca</a>
  </p>
@endcomponent
