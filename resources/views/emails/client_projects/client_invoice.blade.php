@component('emails.client_projects.layout', ['title' => 'Invoice #'.$invoice->invoice_number.', '.$project->name])
  <p>Hi {{ $project->client_name }},</p>

  @if ($project->usesInvoiceBillingOverride())
    <p style="font-size:14px;color:#374151;">
      The attached PDF is addressed to <strong>{{ $project->invoiceBillToName() }}</strong> for billing purposes.
    </p>
  @endif

  <p>
    Please find attached <strong>Invoice #{{ $invoice->invoice_number }}</strong>
    @if (!$invoice->isManual())
      for the <strong>{{ $invoice->installmentTitle() }}</strong>
    @endif
    on your <strong>{{ $project->name }}</strong> project.
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 6px 12px; background: #f4fafe; border: 1px solid #024768; font-size: 15px;">
        <strong>Total due:</strong> {{ \App\Models\ClientProject::formatMoney($invoice->total) }}
        &nbsp;·&nbsp; <strong>Due:</strong> {{ $invoice->dueOnLabel(true) }}
      </td>
    </tr>
  </table>

  <p>
    <strong>Please pay via e-Transfer to:</strong>
    <a href="mailto:zaninirenovations@gmail.com">zaninirenovations@gmail.com</a><br>
    <span style="color:#4b5563;font-size:14px;">
      Please include invoice number <strong>{{ $invoice->invoice_number }}</strong>
      in the e-Transfer message so I can match the payment to your project.
    </span>
  </p>

  <p>If you have any questions about this invoice, just reply to this email and I'll get right back to you.</p>

  <p>Thank you for your business, it's a pleasure working with you.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
