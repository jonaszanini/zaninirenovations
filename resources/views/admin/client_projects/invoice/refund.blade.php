<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Refund {{ $invoice->invoice_number }} — {{ $project->name }}</title>
  @php
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));
    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $refundedOn = $invoice->refunded_at
      ? \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($invoice->refunded_at)
      : \App\Support\EasternStandardTimeDisplay::todayLongDate();
    $paidAmount = $invoice->paidCash();
    $refundedAmount = $invoice->refundCash();
    $remaining = $invoice->refundableBalance();
  @endphp
  <style>
    @page { margin: 118pt 60pt 90pt 60pt; }

    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 400;
      src: url("{{ $fontDir }}/Roboto-Regular.ttf") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 700;
      src: url("{{ $fontDir }}/Roboto-Bold.ttf") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 400;
      src: url("{{ $fontDir }}/Roboto-Italic.ttf") format('truetype');
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', 'DejaVu Sans', Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    .pdf-header {
      position: fixed;
      top: -88pt;
      left: 0;
      right: 0;
      border-bottom: 1pt solid #024768;
      padding-bottom: 8pt;
    }
    .pdf-header table { width: 100%; border-collapse: collapse; }
    .pdf-header td { vertical-align: bottom; padding: 0; }
    .pdf-header .logo-cell { width: 260px; }
    .pdf-header .logo-cell img { width: 254px; height: 63px; }
    .pdf-header .info-cell {
      text-align: right;
      font-size: 8.5pt;
      line-height: 1.1;
      color: #1f2937;
    }

    .pdf-footer {
      position: fixed;
      bottom: -60pt;
      left: 0;
      right: 0;
      height: 30pt;
      border-top: 1pt solid #d8dee4;
      padding-top: 6pt;
      font-size: 9pt;
      color: #6b7280;
    }
    .pdf-footer table { width: 100%; border-collapse: collapse; }

    .receipt-hero {
      background: #fef2f2;
      border: 2pt solid #dc2626;
      border-radius: 4pt;
      padding: 22pt 24pt 20pt;
      text-align: center;
      margin-bottom: 20pt;
    }
    .receipt-hero .big {
      color: #b91c1c;
      font-size: 22pt;
      font-weight: 700;
      letter-spacing: 0.04em;
      margin: 0 0 4pt;
      text-transform: uppercase;
    }
    .receipt-hero .sub { color: #1f2937; font-size: 13pt; margin: 0; }
    .receipt-hero .sub strong { color: #b91c1c; }

    .receipt-meta { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
    .receipt-meta td { vertical-align: top; padding: 0; }
    .receipt-meta .left { width: 55%; }
    .receipt-meta .right { width: 45%; text-align: right; }

    .receipt-client {
      display: inline-block;
      text-align: left;
      font-size: 10pt;
      line-height: 1.45;
      background: #f9fafb;
      border: 1pt solid #d8dee4;
      padding: 10pt 14pt;
      min-width: 220pt;
    }
    .receipt-client .label {
      font-size: 8.5pt;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: #024768;
      text-transform: uppercase;
      margin: 0 0 4pt;
    }
    .receipt-client .name { font-size: 12pt; font-weight: 700; margin: 0 0 4pt; color: #1f2937; }
    .receipt-client .line { margin: 0; font-size: 10pt; color: #1f2937; }

    .summary { width: 100%; border-collapse: collapse; margin-bottom: 14pt; border: 1pt solid #d8dee4; }
    .summary td { padding: 10pt 14pt; font-size: 11pt; vertical-align: middle; border-bottom: 1pt solid #eef1f4; }
    .summary tr:last-child td { border-bottom: none; }
    .summary td.label { color: #4b5563; width: 46%; font-weight: 400; }
    .summary td.value { text-align: right; font-weight: 700; color: #1f2937; }
    .summary tr.paid-row td {
      background: #fef2f2;
      border-top: 2pt solid #dc2626;
      color: #b91c1c;
      font-size: 13pt;
    }
    .summary tr.paid-row td.label { color: #b91c1c; }

    .thanks { font-size: 12pt; margin: 18pt 0 8pt; color: #024768; font-weight: 700; }
    .closing { font-size: 10.5pt; color: #1f2937; line-height: 1.55; page-break-inside: avoid; }
    .closing p { margin: 0 0 8pt; }
    .closing .signoff { margin-top: 12pt; }
  </style>
</head>
<body>
  <div class="pdf-header">
    <table>
      <tr>
        <td class="logo-cell">
          @if (extension_loaded('gd') && file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" alt="Zanini Renovations">
          @else
            <span style="font-weight:700;color:#024768;font-size:14pt;">ZANINI RENOVATIONS</span>
          @endif
        </td>
        <td class="info-cell">
          @include('admin.client_projects._pdf_header_contact')
        </td>
      </tr>
    </table>
  </div>

  <div class="pdf-footer">
    <table>
      <tr>
        <td>ZANINI RENOVATIONS — Refund for Invoice {{ $invoice->invoice_number }}</td>
        <td></td>
      </tr>
    </table>
  </div>

  <div class="receipt-hero">
    <p class="big">Refund issued</p>
    <p class="sub">
      Refund for <strong>Invoice #{{ $invoice->invoice_number }}</strong>
      @if ($invoice->installmentTitle() && ! $invoice->isManual())
        <br><span style="font-size:11pt;color:#4b5563;">{{ $invoice->installmentTitle() }}</span>
      @endif
    </p>
  </div>

  <table class="receipt-meta">
    <tr>
      <td class="left">
        <p style="margin:0;font-size:10.5pt;color:#4b5563;"><strong style="color:#1f2937;">Refund date:</strong> {{ $refundedOn }}</p>
      </td>
      <td class="right">
        <div class="receipt-client">
          <p class="label">Refunded to</p>
          <p class="name">{{ $project->invoiceBillToName() ?: '—' }}</p>
          <p class="line">{!! nl2br(e($project->invoiceBillToAddress() !== '' ? $project->invoiceBillToAddress() : '—')) !!}</p>
          <p class="line">{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</p>
          <p class="line">{{ $project->client_email ?: '—' }}</p>
          <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">Project: {{ $project->name }}</p>
        </div>
      </td>
    </tr>
  </table>

  <table class="summary">
    <tr>
      <td class="label">Invoice number</td>
      <td class="value">{{ $invoice->invoice_number }}</td>
    </tr>
    @if (! $invoice->isManual())
      <tr>
        <td class="label">Installment</td>
        <td class="value" style="font-weight:400;color:#1f2937;">{{ $invoice->installmentTitle() }}</td>
      </tr>
    @endif
    <tr>
      <td class="label">Invoice total</td>
      <td class="value" style="font-weight:400;color:#4b5563;">{{ $fmt($invoice->total) }}</td>
    </tr>
    <tr>
      <td class="label">Amount originally paid</td>
      <td class="value" style="font-weight:400;color:#1f2937;">{{ $fmt($paidAmount) }}</td>
    </tr>
    @if ($invoice->refundMethodLabel())
      <tr>
        <td class="label">Refund method</td>
        <td class="value" style="font-weight:400;color:#1f2937;">{{ $invoice->refundMethodLabel() }}</td>
      </tr>
    @endif
    <tr class="paid-row">
      <td class="label">Amount refunded</td>
      <td class="value">{{ $fmt($refundedAmount) }}</td>
    </tr>
    @if ($remaining >= 0.01)
      <tr>
        <td class="label">Still held from this invoice</td>
        <td class="value" style="font-weight:400;color:#1f2937;">{{ $fmt($remaining) }}</td>
      </tr>
    @endif
  </table>

  @if (filled($invoice->refund_note))
    <p style="font-size:10.5pt;color:#4b5563;"><strong style="color:#1f2937;">Note:</strong> {{ $invoice->refund_note }}</p>
  @endif

  <p class="thanks">This refund has been issued.</p>

  <div class="closing">
    <p>
      This receipt confirms that the refund above has been returned against
      Invoice #{{ $invoice->invoice_number }}. The original invoice and payment
      receipt are unchanged. If you have any questions, please get in touch.
    </p>
    <div class="signoff">
      <p>Best regards,</p>
      <p>
        <strong>Jonas Zanini</strong><br>
        Tel: (647) 834-7570<br>
        Zanini Renovations<br>
        E-mail: zaninirenovations@gmail.com<br>
        Website: www.zaninirenovations.ca<br>
        Instagram: instagram.com/zaninirenovations
      </p>
    </div>
  </div>
</body>
</html>
