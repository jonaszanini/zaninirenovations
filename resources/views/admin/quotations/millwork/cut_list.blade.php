@php
  $title = trim($quotation->displayTitle());
  $sheetCount = (int) ($cut['sheet_count'] ?? count($layouts));
  $yield = $cut['yield_pct'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Cut list — {{ $quotation->quote_number }}</title>
  <link rel="stylesheet" href="{{ asset('css/millwork-cut-list-print.css') }}?v={{ @filemtime(public_path('css/millwork-cut-list-print.css')) ?: time() }}">
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print cut list</button>
  </div>

  <h1>Cut list</h1>
  <p class="meta">
    <strong>{{ e($quotation->quote_number) }}</strong>
    @if (filled($quotation->client_name))
      · {{ e($quotation->client_name) }}
    @endif
    @if ($title !== '')
      <br>{{ e($title) }}
    @endif
    <br>
    {{ $sheetCount }} sheet(s)
    @if ($yield)
      · {{ $yield }}% yield
    @endif
    @php
      $kerfLabel = \App\Services\CutOptimizer\SvgVisualizer::formatKerfLabel((float) ($cut['kerf_in'] ?? 0.125));
    @endphp
    @if ($kerfLabel !== '')
      · Kerf {{ $kerfLabel }} between cuts
    @endif
    · Each sheet prints on its own page.
  </p>

  @if ($layouts === [])
    <p class="empty">No cut plan on the last saved quotation. Enter piece sizes and save before printing.</p>
  @else
    <div class="mw-cut-plan">
      @foreach ($layouts as $layout)
        @include('admin.quotations.millwork._cut_sheet', ['layout' => $layout])
      @endforeach
    </div>
  @endif
</body>
</html>
