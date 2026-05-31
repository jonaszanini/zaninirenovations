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
    <div class="pdf-diagram">
      <img src="{{ $diagram }}" alt="Layout diagram">
    </div>
  @endif

  <table class="pdf-rows">
    @foreach ($rows as $row)
      <tr>
        <td class="label">{{ $row['label'] }}</td>
        <td class="value">{{ $row['value'] }}</td>
      </tr>
    @endforeach
  </table>
</body>
</html>
