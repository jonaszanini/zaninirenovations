<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contract — {{ $project->name }}</title>
  @php
    /*
     * Normalise font paths to forward slashes. On Windows public_path()
     * returns backslashes (e.g. C:\xampp\htdocs\...), and CSS treats
     * `\x` as a hex escape sequence — that silently corrupts the @font-face
     * URLs AND breaks subsequent rules (including @page). Forward slashes
     * are valid on every OS and dompdf accepts them as filesystem paths.
     */
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));
  @endphp
  <style>
    /* ============================================================
       Page layout
       ------------------------------------------------------------
       A4 = 595 × 842 pt (1 inch = 72 pt).

       NOTE: @page MUST be declared before any @font-face rules.
       DomPDF parses style sheets in source order and a malformed
       font URL (or any earlier rule it cannot resolve) can prevent
       a later @page rule from being applied — which silently
       reverts the page to the default zero margin.

       IMPORTANT — DomPDF v2 quirk:
         `position: fixed` offsets (top/left/right/bottom) are
         measured from the BODY CONTENT origin (i.e. AFTER the
         @page margin), NOT from the page paper edge. To place the
         header inside the @page TOP margin area we therefore need
         a NEGATIVE `top` value, and likewise NEGATIVE `bottom`
         for the footer.

       Margins (printed margins around the body content):
         top    160pt ≈ 56 mm  (room for header + breathing space)
         right   60pt ≈ 21 mm
         bottom  90pt ≈ 32 mm  (room for footer + breathing space)
         left    60pt ≈ 21 mm
       ============================================================ */
    @page {
      margin: 160pt 60pt 90pt 60pt;
    }

    /* ============================================================
       Roboto via @font-face. DomPDF reads local TTF files only
       (it cannot use Google Fonts CDN, which serves WOFF2),
       so the font files are shipped under public/fonts/roboto/.
       ============================================================ */
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

    * {
      box-sizing: border-box;
    }

    /*
     * IMPORTANT: do NOT style the <html> element here.
     * In DomPDF, `html { margin: 0 }` (and friends) overrides the
     * @page margin and silently collapses it back to zero. Only style
     * the <body> element — that keeps @page in control of the printed
     * page margins.
     */
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', 'DejaVu Sans', Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    /* ============================================================
       Repeating page header (logo left, company info right).

       top: -130pt means 130pt ABOVE the body content origin.
       Body origin is at @page margin-top (160pt from page edge),
       so the header sits at 160 - 130 = 30pt from the page edge.
       With height 100pt the header ends at 130pt from page edge,
       leaving a 30pt gap before the body content starts at 160pt.
       ============================================================ */
    .pdf-header {
      position: fixed;
      top: -130pt;
      left: 0;
      right: 0;
      height: 100pt;
      border-bottom: 1pt solid #024768;
      padding-bottom: 6pt;
    }

    .pdf-header table {
      width: 100%;
      border-collapse: collapse;
    }

    .pdf-header td {
      vertical-align: middle;
      padding: 0;
    }

    .pdf-header .logo-cell {
      width: 260px;
    }

    .pdf-header .logo-cell img {
      /* Render at the source asset's native dimensions (254 × 63 px). */
      width: 254px;
      height: 63px;
    }

    .pdf-header .info-cell {
      text-align: right;
      font-size: 9.5pt;
      line-height: 1.45;
      color: #1f2937;
    }

    .pdf-header .info-cell .info-line {
      margin: 0;
    }

    .pdf-header .info-cell .info-line + .info-line {
      margin-top: 1pt;
    }

    /* ============================================================
       Repeating page footer (page numbers).

       bottom: -60pt means 60pt BELOW the body content bottom
       (which is 90pt from the page bottom edge). So the footer
       sits at 90 - 60 = 30pt from the page bottom edge with
       height 30pt → occupies the 30pt-60pt strip above the
       page bottom edge.
       ============================================================ */
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

    .pdf-footer table {
      width: 100%;
      border-collapse: collapse;
    }

    .pdf-footer .left  { text-align: left; }
    .pdf-footer .right { text-align: right; }
    /* Page numbering is drawn by ClientProjectContractController::
       stampPageNumbers() after Dompdf::render(). DomPDF's pure-CSS
       counter(pages) returns 0 inside fixed-position table cells, and
       inline page_text() calls only see the pages laid out so far —
       see the controller for the full explanation. */

    /* ============================================================
       Parties block (first page only).
       ============================================================ */
    .parties-intro {
      font-size: 11pt;
      color: #1f2937;
      margin: 0 0 12pt;
    }

    .parties {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24pt;
    }

    .parties td {
      width: 50%;
      vertical-align: top;
      padding: 12pt 14pt;
      border: 1pt solid #d8dee4;
      background: #f9fafb;
    }

    .parties .party-label {
      font-size: 9pt;
      font-weight: 700;
      color: #024768;
      letter-spacing: 0.08em;
      margin: 0 0 6pt;
      text-transform: uppercase;
    }

    .parties .party-name {
      font-size: 12pt;
      font-weight: 700;
      color: #1f2937;
      margin: 0 0 4pt;
    }

    .parties .party-line {
      font-size: 10pt;
      color: #1f2937;
      margin: 0;
      line-height: 1.45;
    }

    /* ============================================================
       Contract body (rich text from TinyMCE).
       ============================================================ */
    .contract-body {
      font-size: 11pt;
      color: #1f2937;
      line-height: 1.55;
    }

    .contract-body h1,
    .contract-body h2,
    .contract-body h3,
    .contract-body h4 {
      color: #024768;
      font-weight: 700;
      margin-top: 18pt;
      margin-bottom: 8pt;
      page-break-after: avoid;
    }

    .contract-body h1 { font-size: 17pt; }
    .contract-body h2 { font-size: 14pt; }
    .contract-body h3 { font-size: 12pt; }
    .contract-body h4 { font-size: 11pt; }

    .contract-body p {
      margin: 0 0 10pt;
    }

    .contract-body ul,
    .contract-body ol {
      margin: 0 0 12pt 24pt;
      padding: 0;
    }

    .contract-body li {
      margin-bottom: 4pt;
    }

    .contract-body table {
      width: 100%;
      border-collapse: collapse;
      margin: 10pt 0;
    }

    .contract-body table th,
    .contract-body table td {
      border: 1pt solid #d8dee4;
      padding: 6pt 8pt;
      vertical-align: top;
    }

    .contract-body table th {
      background: #eef2f6;
      text-align: left;
    }

    .contract-body strong { font-weight: 700; }
    .contract-body em     { font-style: italic; }
    .contract-body u      { text-decoration: underline; }

    .contract-body img {
      max-width: 100%;
      height: auto;
    }

    /* ============================================================
       Signature block (end of document).
       ============================================================ */
    .signature-section {
      margin-top: 50pt;
      page-break-inside: avoid;
    }

    .signature-table {
      width: 100%;
      border-collapse: collapse;
    }

    /*
     * Note the doubled-up selector: the generic `.contract-body table td`
     * rule above has higher specificity than a plain `.signature-table td`
     * (2 elements + 1 class vs 1 element + 1 class), so we bump our
     * specificity to (2 elements + 2 classes) to actually win the cascade
     * and render the signature cells with no visible border.
     */
    .contract-body table.signature-table td {
      width: 50%;
      vertical-align: top;
      padding: 0 14pt;
      border: 0 none transparent;
    }

    .signature-line {
      border-top: 1pt solid #1f2937;
      width: 100%;
      margin-bottom: 4pt;
    }

    .signature-name {
      font-size: 11pt;
      color: #1f2937;
      margin: 0 0 14pt;
    }

    .signature-date {
      font-size: 11pt;
      color: #1f2937;
      margin: 0;
    }

    .signature-date .date-line {
      display: inline-block;
      border-bottom: 1pt solid #1f2937;
      width: 200pt;
      margin-left: 4pt;
    }

    .entire-agreement {
      margin-top: 36pt;
      padding-top: 14pt;
      font-size: 10pt;
      color: #1f2937;
      line-height: 1.55;
    }
  </style>
</head>
<body>

  {{-- ============================================================
       Repeating page header. Logo left, company contact right.
       ============================================================ --}}
  <div class="pdf-header">
    <table>
      <tr>
        <td class="logo-cell">
          @if (file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" alt="Zanini Renovations">
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

  {{-- ============================================================
       Repeating page footer with page number.
       ============================================================ --}}
  {{-- Repeating footer (brand text on the left only). The right-hand
       "Page X of Y" is stamped by the controller after dompdf renders,
       see ClientProjectContractController::stampPageNumbers(). --}}
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS</td>
        <td class="right">{{-- page text injected by stampPageNumbers --}}</td>
      </tr>
    </table>
  </div>

  {{-- ============================================================
       Document body (flows across as many pages as needed).
       ============================================================ --}}
  <div class="contract-body">

    <p class="parties-intro">This Contract is entered into between:</p>

    <table class="parties">
      <tr>
        <td>
          <p class="party-label">Contractor</p>
          <p class="party-name">Jonas Zanini</p>
          <p class="party-line"><strong>ZANINI RENOVATIONS</strong></p>
          <p class="party-line">53 Antonakos Dr, Carleton Place, ON K7C 0L1</p>
          <p class="party-line">Phone: (647) 834-7570</p>
          <p class="party-line">Email: zaninirenovations@gmail.com</p>
        </td>
        <td>
          <p class="party-label">Client</p>
          <p class="party-name">{{ $project->client_name ?: '—' }}</p>
          <p class="party-line">Phone: {{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</p>
          <p class="party-line">Service Address: {{ $project->client_address ?: '—' }}</p>
          <p class="party-line">Email: {{ $project->client_email ?: '—' }}</p>
        </td>
      </tr>
    </table>

    {{-- Trusted HTML produced by TinyMCE (per requirements, only the
         site owner uses the admin so no server-side sanitization). --}}
    {!! $contract->html_content !!}

    <div class="signature-section">
      <table class="signature-table">
        <tr>
          <td>
            <div class="signature-line"></div>
            <p class="signature-name">Jonas Zanini — ZANINI RENOVATIONS</p>
            <p class="signature-date">Date:<span class="date-line"></span></p>
          </td>
          <td>
            <div class="signature-line"></div>
            <p class="signature-name">{{ $project->client_name }} — Client</p>
            <p class="signature-date">Date:<span class="date-line"></span></p>
          </td>
        </tr>
      </table>

      <p class="entire-agreement">
        This contract represents the entire agreement between the parties and supersedes any prior negotiations or agreements. This contract may only be modified in writing and signed by both parties.
      </p>
    </div>

  </div>

</body>
</html>
