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
      padding: 22px 28px;
      font-size: 12px;
    }
    .pdf-head {
      border-bottom: 2px solid #024768;
      padding-bottom: 8px;
      margin-bottom: 12px;
    }
    .pdf-title {
      font-size: 20px;
      color: #024768;
      margin: 0 0 2px;
    }
    .pdf-meta { color: #5a6570; font-size: 10px; margin: 0; }
    .pdf-summary {
      font-size: 12px;
      margin: 0 0 10px;
      line-height: 1.4;
    }
    .pdf-section-title {
      font-size: 13px;
      color: #024768;
      margin: 12px 0 6px;
    }
    .pdf-diagram {
      text-align: center;
      margin: 0 0 8px;
      page-break-inside: avoid;
    }
    .pdf-diagram img { width: 390px; max-width: 100%; height: auto; }
    table.pdf-rows {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    table.pdf-rows td {
      padding: 4px 8px;
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
    .pdf-photos { page-break-before: always; }
    .pdf-photo-grid {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .pdf-photo-grid tr { page-break-inside: avoid; }
    .pdf-photo-grid td {
      width: 50%;
      vertical-align: top;
      padding: 0 10px 16px 0;
    }
    .pdf-photo-grid img {
      display: block;
      width: 100%;
      max-width: none;
      height: auto;
      border: 1px solid #d8dee4;
    }
    .pdf-photo-caption {
      font-size: 10px;
      color: #5a6570;
      margin: 4px 0 0;
    }
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
      <img src="{{ $diagram }}" alt="Room plan" width="390">
    </div>
  @endif

  <h2 class="pdf-section-title">Finish</h2>
  <table class="pdf-rows">
    @php
      $finishRows = collect($rows ?? [])->filter(function ($row) {
        $label = strtolower((string) ($row['label'] ?? ''));
        return str_contains($label, 'ceiling')
          || str_contains($label, 'net wall')
          || str_contains($label, 'flooring')
          || str_contains($label, 'waste')
          || str_contains($label, 'box')
          || str_contains($label, 'casing');
      })->values();
    @endphp
    @forelse ($finishRows as $row)
      <tr>
        <td class="label">{{ $row['label'] }}</td>
        <td class="value">{{ $row['value'] }}</td>
      </tr>
    @empty
      @php $finish = $finish ?? []; @endphp
      @if (!empty($finish['ceiling_height_ft']))
        <tr><td class="label">Ceiling height</td><td class="value">{{ rtrim(rtrim(number_format((float) $finish['ceiling_height_ft'], 2, '.', ''), '0'), '.') }} ft</td></tr>
      @endif
      @if (array_key_exists('flooring_waste_percent', $finish) && $finish['flooring_waste_percent'] !== null)
        <tr><td class="label">Flooring waste</td><td class="value">{{ rtrim(rtrim(number_format((float) $finish['flooring_waste_percent'], 2, '.', ''), '0'), '.') }}%</td></tr>
      @endif
      @if (array_key_exists('flooring_box_sqft', $finish) && $finish['flooring_box_sqft'] !== null)
        <tr><td class="label">Box size</td><td class="value">{{ rtrim(rtrim(number_format((float) $finish['flooring_box_sqft'], 2, '.', ''), '0'), '.') }} sq ft</td></tr>
      @endif
    @endforelse
  </table>

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

  @if (!empty($photos))
    <div class="pdf-photos">
      <h2 class="pdf-section-title">Site photos</h2>
      <table class="pdf-photo-grid">
        @foreach (array_chunk($photos, 2) as $row)
          <tr>
            @foreach ($row as $photo)
              <td>
                <img src="{{ $photo['src'] }}" alt="{{ $photo['caption'] ?: 'Site photo' }}">
                @if (!empty($photo['caption']))
                  <p class="pdf-photo-caption">{{ $photo['caption'] }}</p>
                @endif
              </td>
            @endforeach
            @if (count($row) === 1)
              <td></td>
            @endif
          </tr>
        @endforeach
      </table>
    </div>
  @endif
</body>
</html>
