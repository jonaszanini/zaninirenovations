<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Layout diagrams — {{ $quotation->quote_number }}</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #111827;
      margin: 0;
      padding: 24px;
      font-size: 15px;
      line-height: 1.45;
    }
    h1 { font-size: 24px; margin: 0 0 6px; }
    .meta { color: #4b5563; margin: 0 0 20px; }
    .toolbar { margin-bottom: 18px; }
    .toolbar button {
      background: #024768;
      color: #fff;
      border: 0;
      border-radius: 6px;
      padding: 10px 18px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
    }
    .diagrams-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 28px;
    }
    .diagram-panel {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 14px;
      background: #fff;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .diagram-panel h2 {
      margin: 0 0 10px;
      font-size: 15px;
      font-weight: 700;
      color: #374151;
    }
    .ld-diagram-svg {
      width: 100%;
      height: auto;
      display: block;
    }
    .empty { color: #6b7280; }
    @media print {
      body { padding: 10px; }
      .no-print { display: none !important; }
      .diagram-panel { border-color: #d1d5db; }
      .ld-diagram-svg,
      .diagram-panel {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print layout diagrams</button>
  </div>

  <h1>Layout diagrams</h1>
  <p class="meta">
    <strong>{{ e($quotation->quote_number) }}</strong>
    @if (filled($quotation->client_name))
      · {{ e($quotation->client_name) }}
    @endif
    · {{ rtrim(rtrim(number_format((float) $quotation->width_ft, 2), '0'), '.') }}' &times; {{ rtrim(rtrim(number_format((float) $quotation->length_ft, 2), '0'), '.') }}'
    ({{ rtrim(rtrim(number_format($quotation->areaSqFt(), 2), '0'), '.') }} sq ft)
    @if ($quotation->project && filled($quotation->project->client_address))
      <br>{{ e($quotation->project->client_address) }}
    @endif
  </p>

  @if (! $hasDiagrams)
    <p class="empty">No layout geometry in the saved breakdown. Save the quotation after entering dimensions.</p>
  @else
    <div class="diagrams-grid">
      <section class="diagram-panel">
        <h2>1. Foundations</h2>
        {!! $diagrams['foundations'] !!}
      </section>
      <section class="diagram-panel">
        <h2>2. Framing layout</h2>
        {!! $diagrams['framing'] !!}
      </section>
      <section class="diagram-panel">
        <h2>3. Surface decking</h2>
        {!! $diagrams['decking'] !!}
      </section>
    </div>
  @endif
</body>
</html>
