<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contract — {{ $project->name }}</title>
  <style>
    /* ============================================================
       Roboto via @font-face. DomPDF reads local TTF files only
       (it cannot use Google Fonts CDN, which serves WOFF2),
       so the font files are shipped under public/fonts/roboto/.
       The url() must be an absolute filesystem path for dompdf
       to load it reliably across servers.
       ============================================================ */
    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 400;
      src: url("{{ public_path('fonts/roboto/Roboto-Regular.ttf') }}") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 700;
      src: url("{{ public_path('fonts/roboto/Roboto-Bold.ttf') }}") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 400;
      src: url("{{ public_path('fonts/roboto/Roboto-Italic.ttf') }}") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 700;
      src: url("{{ public_path('fonts/roboto/Roboto-BoldItalic.ttf') }}") format('truetype');
    }

    /* ============================================================
       Page layout
       ------------------------------------------------------------
       A4 = 595 × 842 pt. Margins reserve room for the fixed
       header / footer below; everything inside @page (i.e. the
       body content) automatically respects these margins.

       1 inch = 72 pt. So:
         top    150pt ≈ 53 mm  (logo + 5 lines of contact + breathing room)
         right   60pt ≈ 21 mm  (~1 inch readable line length)
         bottom 100pt ≈ 35 mm  (footer + breathing room)
         left    60pt ≈ 21 mm
       ============================================================ */
    @page {
      margin: 150pt 60pt 100pt 60pt;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', 'DejaVu Sans', Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    /* ============================================================
       Repeating page header (logo left, company info right).
       Sits inside the top page margin via the negative `top`
       trick that DomPDF supports.
       ============================================================ */
    .pdf-header {
      position: fixed;
      top: -120pt;       /* lifts header up into the @page top margin */
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
      width: 200pt;
    }

    .pdf-header .logo-cell img {
      height: 80pt;
      width: auto;
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
       ============================================================ */
    .pdf-footer {
      position: fixed;
      bottom: -70pt;
      left: 0;
      right: 0;
      height: 50pt;
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

    .pdf-pagenum:before   { content: counter(page); }
    .pdf-pagecount:before { content: counter(pages); }

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

    .signature-table td {
      width: 50%;
      vertical-align: top;
      padding: 0 14pt;
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
          <p class="info-line">53 Antonakos Dr</p>
          <p class="info-line">Carleton Place, ON K7C 0L1</p>
          <p class="info-line">(647) 834-7570</p>
          <p class="info-line">zaninirenovations@gmail.com</p>
          <p class="info-line">zaninirenovation.ca</p>
        </td>
      </tr>
    </table>
  </div>

  {{-- ============================================================
       Repeating page footer with page number.
       ============================================================ --}}
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS</td>
        <td class="right">
          Page <span class="pdf-pagenum"></span> of <span class="pdf-pagecount"></span>
        </td>
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
          <p class="party-line">Phone: {{ $project->client_phone ?: '—' }}</p>
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
