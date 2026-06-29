<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shopping list — {{ $quotation->quote_number }}</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #111827;
      margin: 0;
      padding: 24px;
      font-size: 16px;
      line-height: 1.4;
    }
    h1 { font-size: 26px; margin: 0 0 6px; }
    .meta { color: #4b5563; margin: 0 0 18px; font-size: 15px; }
    .toolbar { margin-bottom: 18px; }
    .toolbar button {
      background: #024768; color: #fff; border: 0; border-radius: 6px;
      padding: 12px 20px; font-size: 16px; font-weight: 600; cursor: pointer;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px 10px; border-bottom: 1px solid #d1d5db; text-align: left; vertical-align: top; }
    th {
      background: #f3f4f6; font-size: 13px; text-transform: uppercase;
      letter-spacing: 0.04em; color: #374151;
    }
    td.qty, th.qty, td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .check { width: 34px; text-align: center; }
    .check-box {
      display: inline-block; width: 22px; height: 22px;
      border: 2px solid #374151; border-radius: 4px;
    }
    .item-name { font-weight: 700; font-size: 17px; }
    .item-sku { color: #6b7280; font-size: 13px; }
    tfoot td { font-weight: 700; font-size: 17px; border-top: 2px solid #374151; }
    .empty { color: #6b7280; }
    .delivery { margin-top: 26px; }
    .delivery h2 { font-size: 14px; text-transform: uppercase; letter-spacing: 0.04em; color: #374151; margin: 0 0 8px; }
    .delivery p { margin: 0 0 6px; }
    .delivery__proextra { font-weight: 600; }
    @media print {
      body { padding: 12px; font-size: 14px; }
      .no-print { display: none !important; }
      th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print Shopping List</button>
  </div>

  <h1>Shopping list</h1>
  <p class="meta">
    <strong>{{ e($quotation->quote_number) }}</strong>
    @if (filled($quotation->client_name))
      · {{ e($quotation->client_name) }}
    @endif
    · {{ rtrim(rtrim(number_format((float) $quotation->width_ft, 2), '0'), '.') }}' &times; {{ rtrim(rtrim(number_format((float) $quotation->length_ft, 2), '0'), '.') }}'
    ({{ rtrim(rtrim(number_format($quotation->areaSqFt(), 2), '0'), '.') }} sq ft)
    <br>
    Quantities reflect the <strong>last saved</strong> breakdown. Fasteners are rounded up to whole retail boxes. The waste dump fee is a project cost, not listed here.
  </p>

  @if (empty($rows))
    <p class="empty">No matching line items in the saved breakdown. Save the quotation after entering dimensions so quantities are available.</p>
  @else
    @php $listTotal = 0; @endphp
    <table>
      <thead>
        <tr>
          <th class="check"></th>
          <th>Item</th>
          <th class="qty">Qty</th>
          <th class="num">Unit price</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          @php $listTotal += (float) $row['subtotal']; @endphp
          <tr>
            <td class="check"><span class="check-box"></span></td>
            <td>
              <div class="item-name">{{ e($row['name']) }}</div>
              @if (!empty($row['store_sku']))
                <div class="item-sku">SKU {{ e($row['store_sku']) }}</div>
              @endif
            </td>
            <td class="qty">
              {{ rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.') }} {{ e($row['unit']) }}
            </td>
            <td class="num">{{ \App\Models\ClientProject::formatMoney((float) $row['unit_price']) }}</td>
            <td class="num">{{ \App\Models\ClientProject::formatMoney((float) $row['subtotal']) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td></td>
          <td>Materials total (before HST)</td>
          <td></td>
          <td></td>
          <td class="num">{{ \App\Models\ClientProject::formatMoney($listTotal) }}</td>
        </tr>
      </tfoot>
    </table>
  @endif

  @php
    $deliveryAddress = trim((string) ($quotation->project?->client_address ?? ''));
  @endphp
  <section class="delivery">
    <h2>Delivery address</h2>
    @if ($deliveryAddress !== '')
      <p>{{ e($deliveryAddress) }}</p>
    @endif
    <p class="delivery__proextra">ProExtra: 647 834 7570</p>
  </section>
</body>
</html>
