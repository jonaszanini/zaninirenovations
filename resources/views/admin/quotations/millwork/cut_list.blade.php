@php
  $title = trim($quotation->displayTitle());
  $sheetCount = (int) ($cut['sheet_count'] ?? count($layouts));
  $yield = $cut['yield_pct'] ?? null;
  $kerfLabel = \App\Services\CutOptimizer\SvgVisualizer::formatKerfLabel((float) ($cut['kerf_in'] ?? 0.125));
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

  @if ($layouts === [])
    <h1>Cut list</h1>
    <p class="empty">No cut plan on the last saved quotation. Enter piece sizes and save before printing.</p>
  @else
    <div class="mw-cut-print-drawings">
      @foreach ($layouts as $i => $layout)
        <section class="mw-cut-print-drawing" aria-label="Sheet {{ $i + 1 }} drawing">
          <div class="mw-cut-svg-wrap">{!! $layout['svg'] ?? '' !!}</div>
        </section>
      @endforeach
    </div>

    <section class="mw-cut-print-details">
      <h1>Details</h1>
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
        @if ($kerfLabel !== '')
          · Kerf {{ $kerfLabel }} between cuts
        @endif
      </p>

      @foreach ($layouts as $i => $layout)
        @php $stats = is_array($layout['stats'] ?? null) ? $layout['stats'] : []; @endphp
        <article class="mw-cut-print-sheet-details">
          <h2>
            Sheet {{ $i + 1 }}:
            {{ $stats['label'] ?? 'Sheet' }}
            Used {{ $stats['used_pct'] ?? 0 }}% · waste {{ $stats['waste_pct'] ?? 0 }}%
          </h2>
          @if (! empty($layout['legend']))
            <ul class="mw-cut-legend">
              @foreach ($layout['legend'] as $row)
                <li>
                  <span class="mw-cut-legend__n">{{ $row['n'] ?? '' }}</span>
                  <span>{{ $row['label'] ?? 'Part' }} · {{ $row['size'] ?? '' }}@if (! empty($row['rotated'])) · rotated @endif</span>
                </li>
              @endforeach
            </ul>
          @endif
        </article>
      @endforeach
    </section>
  @endif
</body>
</html>
