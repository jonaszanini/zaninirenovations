<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #373435;
      margin: 0;
      padding: 28px 32px;
      font-size: 12px;
    }
    .pdf-head {
      border-bottom: 2px solid #024768;
      padding-bottom: 10px;
      margin-bottom: 18px;
    }
    .pdf-title {
      font-size: 20px;
      color: #024768;
      margin: 0 0 2px;
    }
    .pdf-meta { color: #5a6570; font-size: 10px; margin: 0; }
    .pdf-summary {
      font-size: 13px;
      margin: 0 0 18px;
      line-height: 1.5;
    }
    .pdf-diagram {
      text-align: center;
      margin: 0 0 20px;
    }
    .pdf-diagram img { max-width: 100%; }
    table.pdf-rows {
      width: 60%;
      border-collapse: collapse;
    }
    table.pdf-rows td {
      padding: 7px 10px;
      border-bottom: 1px solid #d8dee4;
    }
    table.pdf-rows td.label { color: #5a6570; width: 55%; }
    table.pdf-rows td.value { font-weight: 700; text-align: right; }

    .pdf-section-title {
      font-size: 14px;
      color: #024768;
      margin: 22px 0 8px;
    }
    .pdf-section-note { color: #5a6570; font-size: 10px; margin: 0 0 8px; }
    table.pdf-marks {
      width: 100%;
      border-collapse: collapse;
      page-break-inside: auto;
    }
    table.pdf-marks thead { display: table-header-group; }
    table.pdf-marks tr { page-break-inside: avoid; }
    table.pdf-marks th,
    table.pdf-marks td {
      padding: 5px 8px;
      border: 1px solid #d8dee4;
      text-align: left;
      font-size: 11px;
    }
    table.pdf-marks th {
      background: #f4f6f8;
      color: #5a6570;
      text-transform: uppercase;
      font-size: 9px;
      letter-spacing: 0.04em;
    }
    table.pdf-marks td.num { width: 36px; text-align: center; font-weight: 700; }
  </style>
</head>
<body>
  <div class="pdf-head">
    <h1 class="pdf-title">{{ $title }}</h1>
    <p class="pdf-meta">Zanini Renovations &middot; Generated {{ $generatedAt }}</p>
  </div>

  @if (!empty($summary))
    <p class="pdf-summary">{{ $summary }}</p>
  @endif

  @if (!empty($diagram))
    <h2 class="pdf-section-title">Layout diagram</h2>
    <div class="pdf-diagram">
      <img src="{{ $diagram }}" alt="Layout diagram">
    </div>
  @endif

  <h2 class="pdf-section-title">Results</h2>
  <table class="pdf-rows">
    @foreach ($rows as $row)
      <tr>
        <td class="label">{{ $row['label'] }}</td>
        <td class="value">{{ $row['value'] }}</td>
      </tr>
    @endforeach
  </table>

  @if (!empty($marks))
    <h2 class="pdf-section-title">Tape-measure marks</h2>
    <p class="pdf-section-note">Measured from the start of the run. Mark each pillar's near and far edge.</p>
    <table class="pdf-marks">
      <thead>
        <tr>
          <th class="num">#</th>
          <th>Near edge</th>
          <th>Far edge</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($marks as $mark)
          <tr>
            <td class="num">{{ $mark['n'] }}</td>
            <td>{{ $mark['start'] }}</td>
            <td>{{ $mark['end'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
