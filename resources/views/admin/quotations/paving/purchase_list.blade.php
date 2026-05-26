<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Purchasing list — {{ $quotation->quote_number }}</title>
  <style>
    :root {
      --ink: #111827;
      --muted: #6b7280;
      --border: #e5e7eb;
      --head: #f3f4f6;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 24px 20px 40px;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      font-size: 14px;
      color: var(--ink);
      line-height: 1.45;
    }
    h1 {
      margin: 0 0 6px;
      font-size: 22px;
      font-weight: 700;
    }
    h2 {
      margin: 28px 0 8px;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    h2:first-of-type { margin-top: 0; }
    .meta {
      color: var(--muted);
      font-size: 13px;
      margin-bottom: 18px;
    }
    .meta strong { color: var(--ink); }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }
    th, td {
      text-align: left;
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    th {
      background: var(--head);
      font-size: 11px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #374151;
      font-weight: 600;
    }
    .qty, .unit { white-space: nowrap; width: 1%; font-variant-numeric: tabular-nums; }
    .note { color: var(--muted); font-size: 12px; }
    .empty {
      color: var(--muted);
      padding: 20px 0;
    }
    .toolbar {
      margin-bottom: 20px;
    }
    .toolbar button {
      font: inherit;
      padding: 8px 16px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      background: #fff;
      cursor: pointer;
    }
    .toolbar button:hover { background: #f9fafb; }
    a { color: #1d4ed8; }
    @media print {
      body { padding: 12px; }
      .no-print { display: none !important; }
      th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      a { color: var(--ink); text-decoration: none; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print purchasing list</button>
  </div>

  <h1>Purchasing list</h1>
  <p class="meta">
    <strong>{{ e($quotation->quote_number) }}</strong>
    @if (filled($quotation->client_name))
      · {{ e($quotation->client_name) }}
    @endif
    @if ($quotation->project && filled($quotation->project->client_address))
      <br>{{ e($quotation->project->client_address) }}
    @endif
    <br>
    Quantities reflect the <strong>last saved</strong> quotation breakdown.
  </p>

  @php
    $hasMaterials = ! empty($materialRows);
    $hasRentals = ! empty($rentalRows);
  @endphp

  @if (! $hasMaterials && ! $hasRentals)
    <p class="empty">No line items in the saved breakdown. Save the quotation after entering dimensions so quantities are available.</p>
  @else
    @if ($hasMaterials)
      <h2>Materials to buy</h2>
      <table>
        <thead>
          <tr>
            <th class="qty">Qty</th>
            <th class="unit">Unit</th>
            <th>Item</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($materialRows as $row)
            <tr>
              <td class="qty">{{ e($row['quantity_label']) }}</td>
              <td class="unit">{{ e($row['unit']) }}</td>
              <td>
                @if (filled($row['product_url'] ?? null))
                  <a href="{{ $row['product_url'] }}" target="_blank" rel="noopener noreferrer">{{ e($row['label']) }}</a>
                @else
                  {{ e($row['label']) }}
                @endif
              </td>
              <td class="note">{{ filled($row['note'] ?? '') ? e($row['note']) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if ($hasRentals)
      <h2>Equipment &amp; rentals</h2>
      <table>
        <thead>
          <tr>
            <th class="qty">Qty</th>
            <th class="unit">Unit</th>
            <th>Item</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rentalRows as $row)
            <tr>
              <td class="qty">{{ e($row['quantity_label']) }}</td>
              <td class="unit">{{ e($row['unit']) }}</td>
              <td>{{ e($row['label']) }}</td>
              <td class="note">{{ filled($row['note'] ?? '') ? e($row['note']) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  @endif
</body>
</html>
