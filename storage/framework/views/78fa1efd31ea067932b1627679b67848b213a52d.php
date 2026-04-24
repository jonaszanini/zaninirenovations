<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Purchasing list — <?php echo e($quotation->quote_number); ?></title>
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
    .qty { white-space: nowrap; width: 1%; font-variant-numeric: tabular-nums; }
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
    @media print {
      body { padding: 12px; }
      .no-print { display: none !important; }
      th { background: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print Material List</button>
  </div>

  <h1>Purchasing list</h1>
  <p class="meta">
    <strong><?php echo e(e($quotation->quote_number)); ?></strong>
    <?php if(filled($quotation->client_name)): ?>
      · <?php echo e(e($quotation->client_name)); ?>

    <?php endif; ?>
    <br>
    Quantities reflect the <strong>last saved</strong> quotation breakdown. Nails and gate hardware are not listed.
  </p>

  <?php if(empty($rows)): ?>
    <p class="empty">No matching line items in the saved breakdown. Save the quotation after entering dimensions so quantities are available.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th class="qty">Quantity</th>
          <th>Long name</th>
          <th>SKU</th>
          <th>Model</th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td class="qty"><?php echo e(e($row['quantity_label'])); ?></td>
            <td><?php echo $row['long_name_html']; ?></td>
            <td><?php echo $row['store_sku_html'] !== null && $row['store_sku_html'] !== '' ? $row['store_sku_html'] : '—'; ?></td>
            <td><?php echo $row['model_html'] !== null && $row['model_html'] !== '' ? $row['model_html'] : '—'; ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/quotations/fence/purchase_list.blade.php ENDPATH**/ ?>