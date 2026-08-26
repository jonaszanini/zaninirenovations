<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice {{ $invoice->invoice_number }} — {{ $project->name }}</title>
  @php
    // Normalise font paths to forward slashes — see quotation/pdf for why.
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));
    $items = $invoice->cleanItems();
    $hourlyPdf = ($isHourlyManual ?? false);
    $labourRows = $hourlyPdf ? array_values(array_filter($items, fn ($r) => !in_array(($r['line_kind'] ?? 'labour'), ['materials', 'gas', 'labour_credit']))) : $items;
    $materialsRows = $hourlyPdf ? array_values(array_filter($items, fn ($r) => in_array(($r['line_kind'] ?? ''), ['materials', 'gas']))) : [];
    $creditRows = $hourlyPdf ? array_values(array_filter($items, fn ($r) => ($r['line_kind'] ?? '') === 'labour_credit')) : [];

    $subtotal = (float) $invoice->subtotal;
    $hst = (float) $invoice->hst;
    $total = (float) $invoice->total;

    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $grossPdf = $invoice->grossLineSubtotal();
    $discPdf = $invoice->effectiveDiscountAmount();
    $showDiscPdf = !$invoice->isManual()
      && $invoice->installment_number !== null
      && $discPdf> 0.0001;
    $dateStr = \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($invoice->sent_at ?: now());
    $dueStr = $invoice->due_on
      ? \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)
      : \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate(
          \Illuminate\Support\Carbon::now(\App\Support\EasternStandardTimeDisplay::easternTimezone())
              ->addDays(\App\Models\ClientProjectInvoice::DEFAULT_DUE_DAYS)
      );
  @endphp
  <style>
    @page {
      margin: 118pt 60pt 90pt 60pt;
    }

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
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 700;
      src: url("{{ $fontDir }}/Roboto-BoldItalic.ttf") format('truetype');
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

    /* -- Repeating header (logo left, contact right) ---------------- */
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
    .pdf-header .info-cell .info-line {
      margin: 0;
      padding: 0;
      line-height: 1.1;
    }
    .pdf-header .info-cell .info-line + .info-line { margin-top: 0; }
    .pdf-header .info-cell .gst-hst-line {
      margin-top: 2pt;
      font-weight: 700;
      color: #024768;
    }

    /* -- Repeating footer ------------------------------------------- */
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
    .pdf-footer .left  { text-align: left; }
    .pdf-footer .right { text-align: right; }

    /* -- Document body ---------------------------------------------- */
    .doc-meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18pt;
    }
    .doc-meta td { vertical-align: top; padding: 0; }
    .doc-meta .left  { width: 60%; }
    .doc-meta .right { width: 40%; text-align: right; }

    .quote-title {
      font-size: 18pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 3pt;
      line-height: 1.15;
      white-space: nowrap;
    }
    .quote-subtitle {
      font-size: 11pt;
      font-weight: 400;
      color: #1f2937;
      margin: 0 0 4pt;
      line-height: 1.3;
    }
    .quote-dates {
      font-size: 10.5pt;
      color: #4b5563;
      margin: 0;
    }
    .quote-dates strong { color: #1f2937; }

    .quote-client {
      display: inline-block;
      text-align: left;
      font-size: 10pt;
      line-height: 1.45;
      background: #f9fafb;
      border: 1pt solid #d8dee4;
      padding: 10pt 14pt;
      min-width: 220pt;
    }
    .quote-client .label {
      font-size: 8.5pt;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: #024768;
      text-transform: uppercase;
      margin: 0 0 4pt;
    }
    .quote-client .name {
      font-size: 12pt;
      font-weight: 700;
      margin: 0 0 4pt;
      color: #1f2937;
    }
    .quote-client .line {
      margin: 0;
      font-size: 10pt;
      color: #1f2937;
    }

    .section-intro {
      font-size: 11pt;
      color: #1f2937;
      margin: 6pt 0 12pt;
    }

    /* Items table */
    .items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
    }
    .items th, .items td {
      border: 1pt solid #d8dee4;
      padding: 8pt 10pt;
      vertical-align: top;
      font-size: 10.5pt;
    }
    .items thead th {
      background: #024768;
      color: #ffffff;
      font-weight: 700;
      text-align: left;
      letter-spacing: 0.04em;
    }
    .items th.amount, .items td.amount {
      text-align: right;
      width: 130pt;
      white-space: nowrap;
    }
    .items tbody tr:nth-child(even) td {
      background: #f9fafb;
    }
    /* Labour credit lines — mint background + tag (same cell borders as other rows). */
    .items tbody tr.item-row-credit td {
      background: #ecfdf5;
    }
    .items tbody tr.item-row-credit:nth-child(even) td {
      background: #ecfdf5;
    }
    .item-credit-tag {
      display: inline-block;
      font-size: 7.5pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #ffffff;
      background: #15803d;
      padding: 2pt 5pt;
      border-radius: 2pt;
      margin-right: 5pt;
      vertical-align: baseline;
    }
    .item-credit-amount {
      color: #15803d;
      font-weight: 700;
    }
    .items tfoot td {
      background: #ffffff;
      border: 0 none transparent;
      border-top: 1pt solid #d8dee4;
      font-size: 11pt;
      padding: 6pt 10pt;
    }
    .items tfoot td.totals-label {
      text-align: right;
      color: #4b5563;
      font-weight: 400;
    }
    .items tfoot td.amount {
      font-weight: 700;
      color: #1f2937;
    }
    .items tfoot tr.grand td {
      border-top: 2pt solid #024768;
      font-size: 13pt;
      font-weight: 700;
      color: #024768;
      padding-top: 10pt;
    }

    /* Table wrapper: DomPDF ignores page-break-inside on divs and will
       split the blue box across pages. A one-cell table stays together. */
    .pay-instructions-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14pt;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .pay-instructions {
      margin: 0;
      padding: 12pt 14pt;
      border: 1.5pt solid #024768;
      background: #f4fafe;
      border-radius: 3pt;
      font-size: 11pt;
      color: #1f2937;
    }
    .pay-instructions strong {
      color: #024768;
    }
    .pay-instructions .email {
      font-weight: 700;
      color: #024768;
    }

    .closing {
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.55;
      margin-top: 16pt;
      page-break-inside: avoid;
    }
    .closing p { margin: 0 0 8pt; }
    .closing .signoff { margin-top: 12pt; }
  </style>
</head>
<body>

  {{-- Repeating header --}}
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

  {{-- Repeating footer. Right cell intentionally empty — the
       page_text() callback in the controller stamps "Page X of Y". --}}
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS — Invoice {{ $invoice->invoice_number }}</td>
        <td class="right"></td>
      </tr>
    </table>
  </div>

  {{-- Title + dates + client block --}}
  <table class="doc-meta">
    <tr>
      <td class="left">
        <p class="quote-title">Invoice #{{ $invoice->invoice_number }}</p>
        @if ($invoice->installmentTitle())
          <p class="quote-subtitle">{{ $invoice->installmentTitle() }}</p>
        @endif
        <p class="quote-dates">
          <strong>Date:</strong> {{ $dateStr }}<br>
          <strong>Due date:</strong> {{ $dueStr }}
        </p>
      </td>
      <td class="right">
        <div class="quote-client">
          <p class="label">Bill to</p>
          <p class="name">{{ $project->invoiceBillToName() ?: '—' }}</p>
          <p class="line">{!! nl2br(e($project->invoiceBillToAddress() !== '' ? $project->invoiceBillToAddress() : '—')) !!}</p>
          <p class="line">{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</p>
          <p class="line">{{ $project->client_email ?: '—' }}</p>
          <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">
            Project: {{ $project->name }}
          </p>
        </div>
      </td>
    </tr>
  </table>

  <p class="section-intro">Invoice for the following:</p>

  @if (!empty($hourlyPdf))
    @if (count($labourRows)> 0)
      <p style="margin:0 0 6pt;font-size:10.5pt;font-weight:700;color:#024768;letter-spacing:0.06em;text-transform:uppercase;">Labour (HST applies)</p>
      <table class="items" style="margin-bottom:16pt;">
        <thead>
          <tr>
            <th>Description</th>
            <th class="amount">Amount (pre-tax)</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($labourRows as $row)
            <tr>
              <td>
                {{ $row['description'] }}
              </td>
              <td class="amount">
                {{ $fmt((float) ($row['amount'] ?? 0)) }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if (count($creditRows) > 0)
      <p style="margin:0 0 6pt;font-size:10.5pt;font-weight:700;color:#024768;letter-spacing:0.06em;text-transform:uppercase;">Credits</p>
      <table class="items" style="margin-bottom:16pt;">
        <thead>
          <tr>
            <th>Description</th>
            <th class="amount">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($creditRows as $row)
            @php
              $creditAbs = round(abs((float) ($row['amount'] ?? 0)), 2);
            @endphp
            <tr class="item-row-credit">
              <td>
                <span class="item-credit-tag">Credit</span>
                {{ $row['description'] }}
              </td>
              <td class="amount">
                <span class="item-credit-amount">−CA${{ number_format($creditAbs, 2, '.', ',') }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if (count($materialsRows) > 0)
      <p style="margin:0 0 6pt;font-size:10.5pt;font-weight:700;color:#024768;letter-spacing:0.06em;text-transform:uppercase;">Materials / Gas (amounts include HST)</p>
      <p style="margin:0 0 8pt;font-size:9.5pt;color:#4b5563;font-style:italic;">No additional HST is charged on materials or gas — amounts below are the final client prices (tax-included).</p>
      <table class="items" style="margin-bottom:12pt;">
        <thead>
          <tr>
            <th>Description</th>
            <th class="amount">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($materialsRows as $row)
            <tr>
              <td>{{ $row['description'] }}</td>
              <td class="amount">{{ $fmt($row['amount']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if (count($labourRows) === 0 && count($materialsRows) === 0 && count($creditRows) === 0)
      <table class="items">
        <tbody>
          <tr>
            <td colspan="2" style="font-style:italic;color:#6b7280;text-align:center;">No line items</td>
          </tr>
        </tbody>
      </table>
    @endif

    <table class="items">
      <tfoot>
        <tr>
          <td class="totals-label">{{ $invoice->isHstExempt() ? 'Labour subtotal' : 'Labour subtotal (pre-HST)' }}</td>
          <td class="amount">{{ $fmt($hourlyLabourTotal ?? $subtotal) }}</td>
        </tr>
        @if ($invoice->isHstExempt())
          <tr>
            <td class="totals-label" colspan="2" style="font-style:italic;color:#4b5563;font-size:9.5pt;">
              No HST charged on this invoice (cash payment).
            </td>
          </tr>
        @else
          <tr>
            <td class="totals-label">HST (13% on labour only)</td>
            <td class="amount">{{ $fmt($hst) }}</td>
          </tr>
        @endif
        @if (($hourlyMaterialsTotal ?? 0)> 0.0001)
          <tr>
            <td class="totals-label">Materials / Gas (tax-included)</td>
            <td class="amount">{{ $fmt($hourlyMaterialsTotal) }}</td>
          </tr>
        @endif
        <tr class="grand">
          <td class="totals-label">Total due</td>
          <td class="amount">{{ $fmt($total) }}</td>
        </tr>
      </tfoot>
    </table>
  @else
  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="amount">Amount</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($items as $row)
        <tr>
          <td>{{ $row['description'] }}</td>
          <td class="amount">{{ $fmt($row['amount']) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="2" style="font-style:italic;color:#6b7280;text-align:center;">
            {{ $invoice->installmentTitle() ?: 'Services rendered' }}
          </td>
        </tr>
      @endforelse
    </tbody>
    <tfoot>
      @if ($showDiscPdf)
        <tr>
          <td class="totals-label">Subtotal (line items)</td>
          <td class="amount">{{ $fmt($grossPdf) }}</td>
        </tr>
        <tr>
          <td class="totals-label">{{ $invoice->discountPdfLabel() }}</td>
          <td class="amount">−{{ $fmt($discPdf) }}</td>
        </tr>
        <tr>
          <td class="totals-label">Net subtotal (pre-tax)</td>
          <td class="amount">{{ $fmt($subtotal) }}</td>
        </tr>
      @else
      <tr>
        <td class="totals-label">Subtotal</td>
        <td class="amount">{{ $fmt($subtotal) }}</td>
      </tr>
      @endif
      @if ($invoice->isHstExempt())
        <tr>
          <td class="totals-label" colspan="2" style="font-style:italic;color:#4b5563;font-size:9.5pt;">
            No HST charged on this invoice (cash payment).
          </td>
        </tr>
      @else
        <tr>
          <td class="totals-label">{{ $invoice->hstPdfRowLabel() }}</td>
          <td class="amount">{{ $fmt($hst) }}</td>
        </tr>
      @endif
      <tr class="grand">
        <td class="totals-label">Total</td>
        <td class="amount">{{ $fmt($total) }}</td>
      </tr>
    </tfoot>
  </table>
  @endif

  <table class="pay-instructions-table">
    <tr>
      <td class="pay-instructions">
        <p style="margin:0 0 6pt;">
          <strong>Please pay via e-Transfer to:</strong>
          <span class="email">zaninirenovations@gmail.com</span>
        </p>
        <p style="margin:0;font-size:10pt;color:#4b5563;">
          Please include invoice number <strong>{{ $invoice->invoice_number }}</strong>
          in the e-Transfer message so payment is matched to your project.
        </p>
      </td>
    </tr>
  </table>

  <div class="closing">
    <p style="margin-top:14pt;">Thank you for your business — it's a pleasure working with you.</p>
    <p>If you have any questions regarding this invoice, please do not hesitate to reach out.</p>
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
