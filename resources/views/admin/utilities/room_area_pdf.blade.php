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
    .pdf-section-title {
      font-size: 14px;
      color: #024768;
      margin: 22px 0 8px;
    }
    .pdf-diagram {
      text-align: center;
      margin: 0 0 16px;
      page-break-inside: avoid;
    }
    .pdf-diagram img { width: 480px; max-width: 100%; height: auto; }
    table.pdf-rows {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    table.pdf-rows td {
      padding: 7px 10px;
      border-bottom: 1px solid #d8dee4;
    }
    table.pdf-rows td.label { color: #5a6570; width: 55%; }
    table.pdf-rows td.value { font-weight: 700; text-align: right; }
    table.pdf-objects {
      width: 100%;
      border-collapse: collapse;
      page-break-inside: auto;
    }
    table.pdf-objects thead { display: table-header-group; }
    table.pdf-objects tr { page-break-inside: avoid; }
    table.pdf-objects th,
    table.pdf-objects td {
      padding: 5px 8px;
      border: 1px solid #d8dee4;
      text-align: left;
      font-size: 11px;
    }
    table.pdf-objects th {
      background: #f4f6f8;
      color: #5a6570;
      text-transform: uppercase;
      font-size: 9px;
      letter-spacing: 0.04em;
    }
    table.pdf-objects td.num { text-align: right; }
  </style>
</head>
<body>
  <div class="pdf-head">
    <h1 class="pdf-title">{{ $title }}</h1>
    <p class="pdf-meta">
      Zanini Renovations &middot; Generated {{ $generatedAt }}
      @if (!empty($clientProjectName))
        &middot; {{ $clientProjectName }}
      @endif
    </p>
  </div>

  @if (!empty($summary))
    <p class="pdf-summary">{{ $summary }}</p>
  @endif

  @if (!empty($diagram))
    <h2 class="pdf-section-title">Room plan</h2>
    <div class="pdf-diagram">
      <img src="{{ $diagram }}" alt="Room plan">
    </div>
  @endif

  <h2 class="pdf-section-title">Wall metrics</h2>
  <table class="pdf-rows">
    @foreach ($rows as $row)
      <tr>
        <td class="label">{{ $row['label'] }}</td>
        <td class="value">{{ $row['value'] }}</td>
      </tr>
    @endforeach
  </table>

  @if (!empty($objects))
    <h2 class="pdf-section-title">Doors, windows, and openings</h2>
    <table class="pdf-objects">
      <thead>
        <tr>
          <th>Type</th>
          <th>Style</th>
          <th>Width</th>
          <th>Offset</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($objects as $object)
          <tr>
            <td>{{ ucfirst((string) ($object['type'] ?? '')) }}</td>
            <td>{{ $object['subtype'] !== '' ? ucfirst((string) $object['subtype']) : '—' }}</td>
            <td class="num">{{ isset($object['width']) ? rtrim(rtrim(number_format((float) $object['width'], 2, '.', ''), '0'), '.').' in' : '—' }}</td>
            <td class="num">{{ isset($object['offset']) ? rtrim(rtrim(number_format((float) $object['offset'], 2, '.', ''), '0'), '.').' in' : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
