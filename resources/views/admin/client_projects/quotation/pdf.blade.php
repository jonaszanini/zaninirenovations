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
    $items = $quotation->cleanItems();
    $subtotal = $quotation->subtotal();
    $hst = $quotation->hst();
    $grand = $quotation->grandTotal();

    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $pdfStandardDisplayTitle = trim((string) ($quotation->standardDisplayTitle() ?? ''));
    $pdfIsStandard = $quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_STANDARD;
  @endphp
  <style>
    /* ============================================================
       Page layout — see the Contract PDF template for the full
       explanation of the @page-before-@font-face ordering and the
       DomPDF position:fixed quirk used by the header/footer below.
       ============================================================ */
    @page {
      margin: 160pt 60pt 90pt 60pt;
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
      top: -130pt;
      left: 0;
      right: 0;
      height: 100pt;
      border-bottom: 1pt solid #024768;
      padding-bottom: 6pt;
    }
    .pdf-header table { width: 100%; border-collapse: collapse; }
    .pdf-header td { vertical-align: middle; padding: 0; }
    .pdf-header .logo-cell { width: 260px; }
    .pdf-header .logo-cell img { width: 254px; height: 63px; }
    .pdf-header .info-cell {
      text-align: right;
      font-size: 9.5pt;
      line-height: 1.45;
      color: #1f2937;
    }
    .pdf-header .info-cell .info-line { margin: 0; }
    .pdf-header .info-cell .info-line + .info-line { margin-top: 1pt; }

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
    .items th.hst, .items td.hst {
      text-align: center;
      width: 72pt;
      white-space: nowrap;
    }
    /* DomPDF: keep header text white (joint rule above also matches body cells). */
    .items thead th.hst {
      background: #024768;
      color: #ffffff;
    }
    .items th.amount, .items td.amount {
      text-align: right;
      width: 110pt;
      white-space: nowrap;
    }
    .items tbody tr:nth-child(even) td {
      background: #f9fafb;
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
          <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">
            Project: {{ $project->name }}
          </p>
        </div>
      </td>
    </tr>
  </table>

  {{-- Intro phrase --}}
  <p class="quote-intro">{{ $quotation->introPhrase() }}</p>

  {{-- Optional "Description of the work" — free-form notes about the
       service. Rendered as its own block above the line items table so
       the client sees the scope before the priced lines. --}}
  @php
    $quoteWorkDesc = $quotation->workDescriptionForPdf();
  @endphp
  @if ($quoteWorkDesc !== '')
    <div class="quote-work-description">
      <p class="quote-work-description-title">Description of the work</p>
      <p class="quote-work-description-body">{!! nl2br(e($quoteWorkDesc)) !!}</p>
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
        <tr>
          <td>{!! nl2br(e($row['description'])) !!}</td>
          <td class="hst">{{ !empty($row['apply_hst']) ? '13%' : '—' }}</td>
          <td class="amount">{{ $fmt($row['amount']) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td class="totals-label" colspan="2">Subtotal</td>
        <td class="amount">{{ $fmt($subtotal) }}</td>
      </tr>
      <tr>
        <td class="totals-label" colspan="2">HST (13%)</td>
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

  {{-- Closing block --}}
  <div class="closing">
    <ul>
      <li>Prices are firm for 10 days.</li>
      <li>Work schedule is based on weather permitting days (if outside work).</li>
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
