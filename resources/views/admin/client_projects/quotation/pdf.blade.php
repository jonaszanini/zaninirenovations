<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $quotation->typeLabel() }} {{ $quotation->quotation_number }} — {{ $project->name }}</title>
  @php
    /*
     * Normalise font paths to forward slashes. On Windows public_path()
     * returns backslashes (e.g. C:\xampp\htdocs\...), and CSS treats
     * `\x` as a hex escape sequence — that silently corrupts the @font-face
     * URLs AND breaks subsequent rules (including @page). Forward slashes
     * are valid on every OS and dompdf accepts them as filesystem paths.
     */
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));
    $items = $quotation->pdfLineItems();
    $subtotal = $quotation->pdfSubtotal();
    $hst = $quotation->pdfHst();
    $grand = $quotation->pdfGrandTotal();

    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $pdfStandardDisplayTitle = trim((string) ($quotation->standardDisplayTitle() ?? ''));
    $pdfIsStandard = $quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_STANDARD;
    $pdfPhotos = $pdfPhotos ?? $quotation->pdfPhotosForRendering($project);
  @endphp
  <style>
    /* ============================================================
       Page layout — see the Contract PDF template for the full
       explanation of the @page-before-@font-face ordering and the
       DomPDF position:fixed quirk used by the header/footer below.
       ============================================================ */
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

    /*
     * IMPORTANT: do NOT style the <html> element here.
     * In DomPDF, `html { margin: 0 }` overrides the @page margin and
     * silently collapses it back to zero. Only style <body>.
     */
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', 'DejaVu Sans', Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    /* -- Repeating header (logo left, contact right) ----------------- */
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

    /* -- Repeating footer (page number) ------------------------------ */
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
    /* Page numbering is drawn by the <script type="text/php"> block below
       via $pdf->page_script(). DomPDF's pure-CSS `counter(pages)` returns
       0 here because it's evaluated before the second layout pass when
       the footer lives inside a position:fixed table cell. The script
       callback runs AFTER pagination is final, so the count is always
       correct. */

    /* -- Document body ----------------------------------------------- */
    .quote-doc-meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18pt;
    }
    .quote-doc-meta td {
      vertical-align: top;
      padding: 0;
    }
    .quote-doc-meta .left  { width: 60%; }
    .quote-doc-meta .right { width: 40%; text-align: right; }

    .quote-title {
      font-size: 17pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 4pt;
      line-height: 1.15;
      white-space: nowrap;
    }
    .quote-display-title {
      font-size: 17pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 4pt;
      line-height: 1.15;
    }
    .quote-date {
      font-size: 10.5pt;
      color: #4b5563;
      margin: 0;
    }

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

    .quote-intro {
      font-size: 11pt;
      color: #1f2937;
      margin: 6pt 0 12pt;
    }

    .quote-supply-scope-headline {
      margin: 6pt 0 12pt;
      font-size: 11pt;
      font-weight: 400;
      color: #024768;
      line-height: 1.35;
    }
    .quote-supply-scope-headline strong {
      font-weight: 700;
    }

    .quote-work-description {
      margin: 0 0 14pt;
      padding: 10pt 12pt;
      background: #f8fafc;
      border-left: 3pt solid #024768;
    }
    .quote-work-description-title {
      margin: 0 0 4pt;
      font-size: 9.5pt;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #024768;
    }
    .quote-work-description-body {
      margin: 0;
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.45;
    }
    .quote-work-description-body--rich p {
      margin: 0 0 6pt;
    }
    .quote-work-description-body--rich p:last-child {
      margin-bottom: 0;
    }
    .quote-work-description-body--rich ul,
    .quote-work-description-body--rich ol {
      margin: 0 0 6pt 16pt;
      padding: 0;
    }
    .quote-work-description-body--rich li {
      margin: 0 0 2pt;
    }
    .quote-work-description-body--rich h1,
    .quote-work-description-body--rich h2,
    .quote-work-description-body--rich h3,
    .quote-work-description-body--rich h4 {
      margin: 0 0 4pt;
      color: #024768;
      font-weight: 700;
      line-height: 1.3;
    }
    .quote-work-description-body--rich h1 { font-size: 13pt; }
    .quote-work-description-body--rich h2 { font-size: 12pt; }
    .quote-work-description-body--rich h3 { font-size: 11pt; }
    .quote-work-description-body--rich h4 { font-size: 10.5pt; }
    .quote-work-description-body--rich strong { font-weight: 700; }
    .quote-work-description-body--rich em { font-style: italic; }
    .quote-work-description-body--rich u { text-decoration: underline; }
    .quote-work-description-body--rich table {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 6pt;
    }
    .quote-work-description-body--rich table th,
    .quote-work-description-body--rich table td {
      border: 1pt solid #d8dee4;
      padding: 3pt 5pt;
      vertical-align: top;
    }

    .quote-material-selection {
      margin: 0 0 14pt;
      padding: 10pt 12pt;
      background: #fffbeb;
      border: 1pt solid #fcd34d;
      border-radius: 4pt;
    }
    .quote-material-selection-title {
      margin: 0 0 6pt;
      font-size: 9.5pt;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #92400e;
    }
    .quote-material-selection-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10.5pt;
      table-layout: fixed;
    }
    .quote-material-selection-table td {
      padding: 3pt 0;
      vertical-align: top;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    .quote-material-selection-table td.label {
      width: 28%;
      font-weight: 700;
      color: #78350f;
      padding-right: 8pt;
    }

    /* Table wrapper: DomPDF ignores page-break-inside on divs. */
    .quote-payment-schedule-table {
      width: 100%;
      border-collapse: collapse;
      margin: 14pt 0 16pt;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .quote-payment-schedule {
      margin: 0;
      padding: 10pt 12pt;
      background: #f8fafc;
      border-left: 3pt solid #024768;
    }
    .quote-payment-schedule-title {
      margin: 0 0 8pt;
      font-size: 10.5pt;
      font-weight: 700;
      color: #024768;
    }
    .quote-payment-schedule ul {
      margin: 0;
      padding: 0 0 0 16pt;
    }
    .quote-payment-schedule li {
      margin: 0 0 6pt;
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.45;
    }
    .quote-payment-schedule li:last-child {
      margin-bottom: 0;
    }

    .quote-schedule-notice-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 16pt;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .quote-schedule-notice {
      margin: 0;
      padding: 10pt 12pt;
      background: #e8f3f8;
      border: 1.5pt solid #024768;
      color: #0f172a;
      font-size: 10.5pt;
      font-weight: 700;
      line-height: 1.45;
    }

    /* Items table — keep Description/Amount + totals on one page when they fit;
       DomPDF still splits only if the table is taller than a full page. */
    .items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .items tr {
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .items th, .items td {
      border: 1pt solid #d8dee4;
      padding: 4pt 9pt;
      vertical-align: middle;
      font-size: 10.5pt;
      line-height: 1.25;
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
      width: 110pt;
      white-space: nowrap;
    }
    .items th.hst, .items td.hst {
      text-align: center;
      width: 58pt;
      white-space: nowrap;
      font-size: 9.5pt;
    }
    .items td.hst {
      color: #4b5563;
    }
    .items tfoot td.hst {
      color: #4b5563;
      font-weight: 400;
    }
    .items tbody tr:nth-child(even) td {
      background: #f9fafb;
    }
    .items tbody tr.items-heading td {
      background: #e8eef2;
      font-weight: 700;
      color: #024768;
      border-top: 2pt solid #024768;
    }
    .items tbody tr.items-standalone td {
      border-top: 2pt solid #024768;
    }
    .items tbody tr.items-standalone .items-hst-note {
      display: block;
      font-size: 8.5pt;
      color: #6b7280;
      font-weight: 400;
      font-style: italic;
      margin-top: 2pt;
    }
    .items tfoot td {
      background: #ffffff;
      border: 0 none transparent;
      border-top: 1pt solid #d8dee4;
      font-size: 11pt;
      padding: 4pt 9pt;
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
      padding-top: 7pt;
    }

    /* Closing block */
    .closing {
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.55;
      margin-top: 18pt;
      page-break-inside: avoid;
    }
    .closing ul {
      margin: 0 0 12pt 18pt;
      padding: 0;
    }
    .closing li {
      margin-bottom: 4pt;
    }
    .closing p {
      margin: 0 0 8pt;
    }
    .closing .signoff {
      margin-top: 14pt;
    }

    .pdf-photos-section {
      margin: 16pt 0 18pt;
      page-break-inside: avoid;
    }
    .pdf-photos-title {
      font-size: 14pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 6pt;
    }
    .pdf-photos-intro {
      font-size: 10pt;
      color: #4b5563;
      margin: 0 0 14pt;
      line-height: 1.45;
    }
    .pdf-photos-grid {
      width: 100%;
      border-collapse: collapse;
    }
    .pdf-photos-grid td {
      width: 50%;
      vertical-align: top;
      padding: 0 6pt 12pt;
    }
    .pdf-photos-grid td:only-child {
      width: 100%;
    }
    .pdf-photo-frame {
      border: 1pt solid #d8dee4;
      border-radius: 4pt;
      padding: 6pt;
      background: #f9fafb;
      text-align: center;
    }
    .pdf-photo-frame img {
      display: block;
      max-width: 100%;
      max-height: 280pt;
      width: auto;
      height: auto;
      margin: 0 auto;
    }
    .pdf-photo-caption {
      margin: 6pt 0 0;
      font-size: 8.5pt;
      color: #6b7280;
      word-break: break-word;
    }
  </style>
</head>
<body>

  {{-- Repeating header (logo + company contact) --}}
  <div class="pdf-header">
    <table>
      <tr>
        <td class="logo-cell">
          {{-- The GD extension is required by dompdf to embed PNGs.
               Production has it; some local/dev sandboxes don't, in which
               case we silently fall back to a text mark so the PDF still
               renders end-to-end. --}}
          @if (extension_loaded('gd') && file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" alt="Zanini Renovations">
          @else
            <span style="font-weight:700;color:#024768;font-size:14pt;">ZANINI RENOVATIONS</span>
          @endif
        </td>
        <td class="info-cell">
          <p class="info-line">53 Antonakos Dr, Carleton Place, ON K7C 0L1</p>
          <p class="info-line">(647) 834-7570</p>
          <p class="info-line">zaninirenovations@gmail.com</p>
          <p class="info-line">zaninirenovations.ca</p>
        </td>
      </tr>
    </table>
  </div>

  {{-- Repeating footer (brand text on the left only). The right-hand
       "Page X of Y" is drawn separately by the page_script() callback
       below — see the comment in the .pdf-footer CSS for why. --}}
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS — {{ $quotation->typeLabel() }} {{ $quotation->quotation_number }}</td>
        <td class="right">{{-- page text injected by page_script --}}</td>
      </tr>
    </table>
  </div>

  {{-- "Page X of Y" footer text is drawn from the controller AFTER
       Dompdf::render() finishes (see ClientProjectQuotationController::
       renderPdf). It cannot be done from an inline <script type="text/php">
       block here: dompdf evaluates inline scripts during body rendering,
       and CPDF::page_text() resolves the page count IMMEDIATELY (it
       iterates $this->_pages and uses $this->_page_count as it stands at
       call time). So an inline call always sees "Page 1 of 1" and never
       writes anything on the additional pages it later creates. --}}

  {{-- Document title + date + client block --}}
  <table class="quote-doc-meta">
    <tr>
      <td class="left">
        @if ($pdfIsStandard)
          @php
            $pdfHeading = $pdfStandardDisplayTitle !== ''
                ? $pdfStandardDisplayTitle
                : $quotation->typeLabel().' #'.$quotation->quotation_number;
          @endphp
          <p class="quote-display-title">{{ $pdfHeading }}</p>
        @else
          <p class="quote-title">{{ $quotation->typeLabel() }} #{{ $quotation->quotation_number }}</p>
        @endif
        <p class="quote-date">Date: {{ \App\Support\EasternStandardTimeDisplay::todayLongDate() }}</p>
      </td>
      <td class="right">
        <div class="quote-client">
          <p class="label">Prepared for</p>
          <p class="name">{{ $project->client_name ?: 'TBD' }}</p>
          <p class="line">Address: {{ $project->client_address ?: 'TBD' }}</p>
          <p class="line">Phone: {{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: 'TBD' }}</p>
          <p class="line">Email: {{ $project->client_email ?: 'TBD' }}</p>
          @php $pdfJobLabel = trim($quotation->jobLabelForPdf()); @endphp
          @if ($pdfJobLabel !== '')
            <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">
              Job: {{ $pdfJobLabel }}
            </p>
          @endif
        </div>
      </td>
    </tr>
  </table>

  {{-- Supply scope — materials and/or labour included in this price --}}
  @php $supplyScope = $quotation->supplyScopePdfMeta(); @endphp
  @if ($supplyScope['show'] ?? false)
    @include('admin.client_projects._quotation_supply_scope', [
        'quotation' => $quotation,
        'class' => 'quote-supply-scope',
    ])
  @else
    <p class="quote-intro">{{ $quotation->introPhrase() }}</p>
  @endif

  {{-- Optional "Description of the work" — free-form notes about the
       service. Rendered as its own block above the line items table so
       the client sees the scope before the priced lines. --}}
  @php
    $quoteWorkDesc = $quotation->workDescriptionForPdf();
    $quoteWorkDescIsHtml = $quotation->workDescriptionForPdfIsHtml();
    $flooringMaterial = $quotation->flooringMaterialSelectionForPdf();
  @endphp
  @if ($quotation->hasWorkDescriptionForPdf())
    <div class="quote-work-description">
      <p class="quote-work-description-title">Description of the work</p>
      @if ($quoteWorkDescIsHtml)
        <div class="quote-work-description-body quote-work-description-body--rich">{!! $quoteWorkDesc !!}</div>
      @else
        <p class="quote-work-description-body">{!! nl2br(e($quoteWorkDesc)) !!}</p>
      @endif
    </div>
  @endif

  @if ($flooringMaterial)
    <div class="quote-material-selection">
      <p class="quote-material-selection-title">Client material selection</p>
      <table class="quote-material-selection-table" role="presentation">
        @if ($flooringMaterial['name'] !== '')
          <tr>
            <td class="label">Material</td>
            <td>{{ $flooringMaterial['name'] }}</td>
          </tr>
        @endif
        @if ($flooringMaterial['color'] !== '')
          <tr>
            <td class="label">Colour</td>
            <td>{{ $flooringMaterial['color'] }}</td>
          </tr>
        @endif
        @if ($flooringMaterial['cost_per_sqft'] > 0)
          <tr>
            <td class="label">Material cost</td>
            <td>{{ $fmt($flooringMaterial['cost_per_sqft']) }} / sq ft (before tax)</td>
          </tr>
        @endif
        @if (($flooringMaterial['waste_percent'] ?? 0) > 0)
          <tr>
            <td class="label">Waste allowance</td>
            <td>{{ number_format($flooringMaterial['waste_percent'], 1) }}%</td>
          </tr>
        @endif
        @if (($flooringMaterial['delivery_fee'] ?? 0) > 0)
          <tr>
            <td class="label">Delivery</td>
            <td>{{ $fmt($flooringMaterial['delivery_fee']) }} (before tax)</td>
          </tr>
        @endif
        @if (($flooringMaterial['total_pre_tax'] ?? 0) > 0)
          <tr>
            <td class="label">Estimated material total</td>
            <td><strong>{{ $fmt($flooringMaterial['total_pre_tax']) }} (before tax)</strong></td>
          </tr>
        @endif
        @if ($flooringMaterial['url'] !== '')
          <tr>
            <td class="label">Store link</td>
            <td><a href="{{ $flooringMaterial['url'] }}" style="color:#024768;text-decoration:underline;">{{ \Illuminate\Support\Str::limit($flooringMaterial['url'], 55) }}</a></td>
          </tr>
        @endif
      </table>
    </div>
  @endif

  {{-- Items --}}
  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="hst">HST</th>
        <th class="amount">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($items as $row)
        @if (!empty($row['is_heading']))
          <tr class="items-heading">
            <td colspan="3">{{ $row['description'] }}</td>
          </tr>
        @else
        <tr class="{{ ! empty($row['is_standalone']) ? 'items-standalone' : '' }}">
          @php
            $desc = e($row['description']);
            $note = 'Note: Material price is subject to change depending on store price at the time of purchase.';
            if (str_contains($desc, $note)) {
                $desc = str_replace($note, '<span style="font-size: 9pt; color: #6b7280; font-style: italic;">' . $note . '</span>', $desc);
            }
          @endphp
          <td>
            {!! nl2br($desc) !!}
            @if (!empty($row['hst_included']) && empty($row['apply_hst']))
              <span class="items-hst-note">HST included in amount</span>
            @endif
          </td>
          <td class="hst">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
          <td class="amount">{{ $fmt($row['amount']) }}</td>
        </tr>
        @endif
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td class="totals-label" colspan="2">Subtotal (before HST)</td>
        <td class="amount">{{ $fmt($subtotal) }}</td>
      </tr>
      <tr>
        <td class="totals-label" colspan="2">HST (13% on lines marked 13%)</td>
        <td class="amount">{{ $fmt($hst) }}</td>
      </tr>
      <tr class="grand">
        <td class="totals-label" colspan="2">Total with HST</td>
        <td class="amount">{{ $fmt($grand) }}</td>
      </tr>
    </tfoot>
  </table>
  @if ($quotation->hasSubtotalOverrideActive())
    <p style="font-size:9.5pt;color:#4b5563;margin:8pt 0 0;">
      The subtotal replaces the sum of the listed line amounts ({{ $fmt($quotation->lineItemsSubtotal()) }}).
      HST is scaled to keep the same taxable and exempt mix as those lines.
    </p>
  @endif

  @php
    $paymentTerms = \App\Services\QuotationPaymentSchedule::terms($quotation->payment_installments ?? null, $quotation->payment_percentages ?? null);
  @endphp
  <table class="quote-payment-schedule-table">
    <tr>
      <td class="quote-payment-schedule">
        <p class="quote-payment-schedule-title">Payment Schedule &amp; Terms</p>
        <ul>
          @foreach ($paymentTerms as [$title, $detail])
            <li><strong>{{ $title }}:</strong> {{ $detail }}</li>
          @endforeach
        </ul>
      </td>
    </tr>
  </table>

  <table class="quote-schedule-notice-table">
    <tr>
      <td class="quote-schedule-notice">
        Project start dates are approximate and subject to change due to adverse weather conditions, such as rain, or weather related delays on preceding projects. You will be notified promptly of any scheduling updates.
      </td>
    </tr>
  </table>

  @if ($flooringMaterial && !empty($flooringMaterial['local_images']))
    <div class="pdf-photos-section" style="page-break-inside: avoid;">
      <p class="pdf-photos-title">Client material selection images</p>
      <table class="pdf-photos-grid" style="table-layout: fixed; width: 100%;">
        @foreach (array_chunk($flooringMaterial['local_images'], 2) as $row)
          <tr>
            @foreach ($row as $imgPath)
              <td style="width: 50%;">
                <div class="pdf-photo-frame">
                  <img src="{{ $imgPath }}" alt="Material Image" style="max-width: 100%; height: auto;">
                </div>
              </td>
            @endforeach
            @if (count($row) === 1)
              <td style="width: 50%;"></td>
            @endif
          </tr>
        @endforeach
      </table>
    </div>
  @endif

  @php
    $lowDeckDiagrams = null;
    if ($quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_LOW_DECK) {
      $ldSnap = is_array($quotation->source_snapshot) ? $quotation->source_snapshot : [];
      $ldGeometry = $ldSnap['calculation']['geometry'] ?? null;
      $ldShow = (bool) ($ldSnap['include_diagrams_on_pdf'] ?? false);
      if ($ldShow && is_array($ldGeometry)) {
        $lowDeckDiagrams = \App\Support\LowDeckDiagramRenderer::renderForPdf($ldGeometry);
      }
    }
  @endphp
  @if ($lowDeckDiagrams)
    <div class="pdf-photos-section" style="page-break-inside: avoid;">
      <p class="pdf-photos-title">Deck layout diagrams</p>
      <div style="text-align:center; margin-bottom:10pt;">
        <div style="width:70%; margin:0 auto 8pt;">{!! $lowDeckDiagrams['foundations'] !!}</div>
        <div style="width:70%; margin:0 auto 8pt;">{!! $lowDeckDiagrams['framing'] !!}</div>
        <div style="width:70%; margin:0 auto;">{!! $lowDeckDiagrams['decking'] !!}</div>
      </div>
    </div>
  @endif

  @if ($pdfPhotos->isNotEmpty())
    <div class="pdf-photos-section">
      <p class="pdf-photos-title">Reference photos</p>
      <p class="pdf-photos-intro">Site and scope reference images attached to this {{ strtolower($quotation->typeLabel()) }}.</p>
      <table class="pdf-photos-grid">
        @foreach ($pdfPhotos->chunk(2) as $row)
          <tr>
            @foreach ($row as $photo)
              @php $absPath = $photo->absolutePathForPdf(); @endphp
              @if ($absPath)
                <td>
                  <div class="pdf-photo-frame">
                    <img src="{{ $absPath }}" alt="">
                    @if (filled($photo->displayName()))
                      <p class="pdf-photo-caption">{{ $photo->displayName() }}</p>
                    @endif
                  </div>
                </td>
              @endif
            @endforeach
            @if ($row->count() === 1)
              <td></td>
            @endif
          </tr>
        @endforeach
      </table>
    </div>
  @endif

  {{-- Closing block --}}
  <div class="closing">
    <ul>
      <li>Prices are firm for 10 days.</li>
      <li>Zanini Renovations carries a liability insurance.</li>
      <li>Warrantee on workmanship: 1 year.</li>
      <li>Warrantee on workmanship: damages caused by external forces (&ldquo;Act of God&rdquo;) such as weather are not covered under warrantee.</li>
      <li>If you have any questions, please do not hesitate to call.</li>
    </ul>

    <p>We trust this quote meets with your approval and we are favoured with your order.</p>

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
