<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $project_name }} — Room plan</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #373435;
      background: #ffffff;
      margin: 0;
      padding: 20px 24px;
      font-size: 11px;
    }
    .plan-head {
      border-bottom: 2px solid #024768;
      padding-bottom: 10px;
      margin-bottom: 12px;
    }
    .plan-brand {
      width: 100%;
      border-collapse: collapse;
    }
    .plan-brand td { vertical-align: middle; padding: 0; }
    .plan-logo { width: 72px; }
    .plan-logo img { width: 64px; height: auto; display: block; }
    .plan-brand-name {
      font-size: 18px;
      color: #024768;
      margin: 0 0 2px;
      font-weight: 700;
    }
    .plan-meta { color: #5a6570; font-size: 10px; margin: 0; }
    .plan-header-block { margin: 0 0 12px; }
    .plan-header-block h1 {
      font-size: 16px;
      color: #024768;
      margin: 0 0 2px;
    }
    .plan-section-title {
      font-size: 13px;
      color: #024768;
      margin: 12px 0 6px;
    }
    table.plan-rows {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }
    table.plan-rows td {
      padding: 3px 6px;
      border-bottom: 1px solid #d8dee4;
    }
    table.plan-rows td.label { color: #5a6570; width: 55%; }
    table.plan-rows td.value { font-weight: 700; text-align: right; }
    .plan-room {
      page-break-inside: avoid;
      margin: 0 0 14px;
      border: 1px solid #d8dee4;
      padding: 8px 10px 6px;
    }
    .plan-room h3 {
      margin: 0 0 6px;
      font-size: 12px;
      color: #024768;
    }
    .plan-svg { text-align: center; margin: 0 0 6px; }
    .plan-svg svg { width: 480px; max-width: 100%; height: auto; }
    .plan-sign {
      margin-top: 18px;
      page-break-inside: avoid;
      border-top: 2px solid #024768;
      padding-top: 10px;
    }
    .plan-sign__img {
      width: 220px;
      height: 70px;
      border: 1px solid #d8dee4;
      margin: 0 0 6px;
    }
    .plan-sign__line {
      width: 240px;
      border-bottom: 1px solid #373435;
      height: 36px;
      margin: 0 0 4px;
    }
    .plan-sign__label { color: #5a6570; font-size: 10px; margin: 0 0 8px; }
  </style>
</head>
<body>
  <div class="plan-head">
    <table class="plan-brand">
      <tr>
        <td class="plan-logo">
          @if (!empty($logo_data_uri))
            <img src="{{ $logo_data_uri }}" alt="Zanini Renovations">
          @endif
        </td>
        <td>
          <p class="plan-brand-name">{{ $brand }}</p>
          <p class="plan-meta">Room plan · Generated {{ $generated_at }}</p>
        </td>
      </tr>
    </table>
  </div>

  <div class="plan-header-block">
    <h1>{{ $project_name }}</h1>
    <p class="plan-meta">
      @if ($client_name !== '')
        Client: {{ $client_name }}
      @endif
      @if ($client_project_name !== '')
        @if ($client_name !== '') &middot; @endif
        Project: {{ $client_project_name }}
      @endif
    </p>
  </div>

  <h2 class="plan-section-title">Project totals</h2>
  <table class="plan-rows">
    <tr><td class="label">Floor area</td><td class="value">{{ number_format((float) $totals['area_sqft'], 2) }} sq ft</td></tr>
    <tr><td class="label">Perimeter</td><td class="value">{{ number_format((float) $totals['perimeter_lf'], 2) }} lf</td></tr>
    <tr><td class="label">Net wall area</td><td class="value">{{ number_format((float) $totals['net_wall_area_sqft'], 2) }} sq ft</td></tr>
    <tr><td class="label">Flooring with waste</td><td class="value">{{ number_format((float) $totals['flooring_with_waste_sqft'], 2) }} sq ft</td></tr>
    <tr><td class="label">Casing</td><td class="value">{{ number_format((float) $totals['casing_lf'], 2) }} lf</td></tr>
  </table>

  @foreach ($sections as $section)
    <h2 class="plan-section-title">{{ $section['name'] }}</h2>
    <table class="plan-rows">
      <tr><td class="label">Floor area</td><td class="value">{{ number_format((float) $section['totals']['area_sqft'], 2) }} sq ft</td></tr>
      <tr><td class="label">Perimeter</td><td class="value">{{ number_format((float) $section['totals']['perimeter_lf'], 2) }} lf</td></tr>
      <tr><td class="label">Net wall area</td><td class="value">{{ number_format((float) $section['totals']['net_wall_area_sqft'], 2) }} sq ft</td></tr>
      <tr><td class="label">Flooring with waste</td><td class="value">{{ number_format((float) $section['totals']['flooring_with_waste_sqft'], 2) }} sq ft</td></tr>
      <tr><td class="label">Casing</td><td class="value">{{ number_format((float) $section['totals']['casing_lf'], 2) }} lf</td></tr>
    </table>

    @foreach ($section['rooms'] as $room)
      <div class="plan-room">
        <h3>{{ $room['name'] }}</h3>
        @if (!empty($room['svg']))
          <div class="plan-svg">{!! $room['svg'] !!}</div>
        @endif
        <table class="plan-rows">
          <tr><td class="label">Floor area</td><td class="value">{{ number_format((float) $room['metrics']['area_sqft'], 2) }} sq ft</td></tr>
          <tr><td class="label">Perimeter</td><td class="value">{{ number_format((float) $room['metrics']['perimeter_lf'], 2) }} lf</td></tr>
          <tr><td class="label">Net wall area</td><td class="value">{{ number_format((float) $room['metrics']['net_wall_area_sqft'], 2) }} sq ft</td></tr>
          <tr><td class="label">Flooring with waste</td><td class="value">{{ number_format((float) $room['metrics']['flooring_with_waste_sqft'], 2) }} sq ft</td></tr>
          <tr><td class="label">Casing</td><td class="value">{{ number_format((float) $room['metrics']['casing_lf'], 2) }} lf</td></tr>
        </table>
      </div>
    @endforeach
  @endforeach

  <div class="plan-sign">
    <h2 class="plan-section-title">Client signature</h2>
    @if (!empty($signature['image']))
      <img class="plan-sign__img" src="{{ $signature['image'] }}" alt="Client signature">
    @else
      <div class="plan-sign__line"></div>
    @endif
    <p class="plan-sign__label">Signature{{ $signature['name'] !== '' ? ': '.$signature['name'] : '' }}</p>
    <div class="plan-sign__line"></div>
    <p class="plan-sign__label">Date{{ $signature['date'] ? ': '.$signature['date'] : '' }}</p>
  </div>
</body>
</html>
