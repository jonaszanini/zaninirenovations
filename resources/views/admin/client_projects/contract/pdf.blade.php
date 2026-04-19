<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contract — {{ $project->name }}</title>
  <style>
    /* ============================================================
       DomPDF requires inline @page rules for repeating header/footer.
       Margins reserve space for the fixed-position bars below.
       A4 = 595 x 842pt. We use generous margins for a print-ready
       look.
       ============================================================ */
    @page {
      margin: 130px 50px 110px 50px;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    /* ---------- Page header (repeats on every page) ---------- */
    .pdf-header {
      position: fixed;
      top: -110px;
      left: 0;
      right: 0;
      height: 90px;
      border-bottom: 2px solid #024768;
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
      width: 160px;
    }

    .pdf-header .logo-cell img {
      height: 70px;
      width: auto;
    }

    .pdf-header .title-cell {
      text-align: right;
      color: #024768;
    }

    .pdf-header .title-cell .title {
      font-size: 18pt;
      font-weight: bold;
      letter-spacing: 0.04em;
      margin: 0;
    }

    .pdf-header .title-cell .subtitle {
      font-size: 10pt;
      color: #5a6570;
      margin-top: 2px;
    }

    /* ---------- Page footer (repeats on every page) ---------- */
    .pdf-footer {
      position: fixed;
      bottom: -90px;
      left: 0;
      right: 0;
      height: 70px;
      border-top: 1px solid #d8dee4;
      padding-top: 8px;
      font-size: 9pt;
      color: #6b7280;
    }

    .pdf-footer table {
      width: 100%;
      border-collapse: collapse;
    }

    .pdf-footer td {
      vertical-align: top;
      padding: 0;
    }

    .pdf-footer .left {
      text-align: left;
    }

    .pdf-footer .right {
      text-align: right;
    }

    .pdf-pagenum:before {
      content: counter(page);
    }

    .pdf-pagecount:before {
      content: counter(pages);
    }

    /* ---------- Parties block (first page) ---------- */
    .parties-intro {
      font-size: 11pt;
      color: #1f2937;
      margin-bottom: 14px;
    }

    .parties {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }

    .parties td {
      width: 50%;
      vertical-align: top;
      padding: 12px 14px;
      border: 1px solid #d8dee4;
      background: #f9fafb;
    }

    .parties .party-label {
      font-size: 9pt;
      font-weight: bold;
      color: #024768;
      letter-spacing: 0.08em;
      margin: 0 0 6px;
      text-transform: uppercase;
    }

    .parties .party-name {
      font-size: 12pt;
      font-weight: bold;
      color: #1f2937;
      margin: 0 0 4px;
    }

    .parties .party-line {
      font-size: 10pt;
      color: #1f2937;
      margin: 0;
      line-height: 1.45;
    }

    /* ---------- Contract body (rich text from TinyMCE) ---------- */
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
      margin-top: 18px;
      margin-bottom: 8px;
      page-break-after: avoid;
    }

    .contract-body h1 { font-size: 17pt; }
    .contract-body h2 { font-size: 14pt; }
    .contract-body h3 { font-size: 12pt; }
    .contract-body h4 { font-size: 11pt; }

    .contract-body p {
      margin: 0 0 10px;
    }

    .contract-body ul,
    .contract-body ol {
      margin: 0 0 12px 24px;
      padding: 0;
    }

    .contract-body li {
      margin-bottom: 4px;
    }

    .contract-body table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
    }

    .contract-body table th,
    .contract-body table td {
      border: 1px solid #d8dee4;
      padding: 6px 8px;
      vertical-align: top;
    }

    .contract-body table th {
      background: #eef2f6;
      text-align: left;
    }

    .contract-body strong { font-weight: bold; }
    .contract-body em { font-style: italic; }
    .contract-body u { text-decoration: underline; }

    .contract-body img {
      max-width: 100%;
      height: auto;
    }

    /* ---------- Signature block (end of document) ---------- */
    .signature-section {
      margin-top: 50px;
      page-break-inside: avoid;
    }

    .signature-table {
      width: 100%;
      border-collapse: collapse;
    }

    .signature-table td {
      width: 50%;
      vertical-align: top;
      padding: 0 14px;
    }

    .signature-line {
      border-top: 1px solid #1f2937;
      width: 100%;
      margin-bottom: 4px;
    }

    .signature-name {
      font-size: 11pt;
      color: #1f2937;
      margin: 0 0 14px;
    }

    .signature-date {
      font-size: 11pt;
      color: #1f2937;
      margin: 0;
    }

    .signature-date .date-line {
      display: inline-block;
      border-bottom: 1px solid #1f2937;
      width: 200px;
      margin-left: 4px;
    }

    /* ---------- Closing paragraph ---------- */
    .entire-agreement {
      margin-top: 36px;
      padding-top: 14px;
      font-size: 10pt;
      color: #1f2937;
      line-height: 1.55;
    }
  </style>
</head>
<body>

  {{-- ============================================================
       Repeating page header. Logo top-left, "CONTRACT" top-right.
       ============================================================ --}}
  <div class="pdf-header">
    <table>
      <tr>
        <td class="logo-cell">
          @if (file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" alt="Zanini Renovations">
          @endif
        </td>
        <td class="title-cell">
          <div class="title">CONTRACT</div>
          <div class="subtitle">{{ $project->name }}</div>
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
        <td class="left">
          ZANINI RENOVATIONS · 53 Antonakos Dr, Carleton Place, ON K7C 0L1<br>
          647 834 7570 · jonaszanini@gmail.com
        </td>
        <td class="right">
          Page <span class="pdf-pagenum"></span> of <span class="pdf-pagecount"></span>
        </td>
      </tr>
    </table>
  </div>

  {{-- ============================================================
       Document content (flows across as many pages as needed).
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
          <p class="party-line">Phone: 647 834 7570</p>
          <p class="party-line">Email: jonaszanini@gmail.com</p>
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

    {{-- The HTML produced by TinyMCE. We trust it because only the
         site owner uses the admin (per requirements). --}}
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
