<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice <?php echo e($invoice->invoice_number); ?> — <?php echo e($project->name); ?></title>
  <?php
    // Normalise font paths to forward slashes — see quotation/pdf for why.
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));
    $items = $invoice->cleanItems();
    $hourlyPdf = ($isHourlyManual ?? false);
    $labourRows = $hourlyPdf ? array_values(array_filter($items, fn ($r) => ($r['line_kind'] ?? 'labour') !== 'materials')) : $items;
    $materialsRows = $hourlyPdf ? array_values(array_filter($items, fn ($r) => ($r['line_kind'] ?? '') === 'materials')) : [];

    $subtotal = (float) $invoice->subtotal;
    $hst = (float) $invoice->hst;
    $total = (float) $invoice->total;

    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $grossPdf = $invoice->grossLineSubtotal();
    $discPdf = $invoice->effectiveDiscountAmount();
    $showDiscPdf = !$invoice->isManual()
      && $invoice->installment_number !== null
      && $discPdf > 0.0001;
    $dateStr = \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($invoice->sent_at ?: now());
    $dueStr = $invoice->due_on
      ? \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)
      : \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate(
          \Illuminate\Support\Carbon::now(\App\Support\EasternStandardTimeDisplay::easternTimezone())
              ->addDays(\App\Models\ClientProjectInvoice::DEFAULT_DUE_DAYS)
      );
  ?>
  <style>
    @page {
      margin: 160pt 60pt 90pt 60pt;
    }

    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 400;
      src: url("<?php echo e($fontDir); ?>/Roboto-Regular.ttf") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: normal;
      font-weight: 700;
      src: url("<?php echo e($fontDir); ?>/Roboto-Bold.ttf") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 400;
      src: url("<?php echo e($fontDir); ?>/Roboto-Italic.ttf") format('truetype');
    }
    @font-face {
      font-family: 'Roboto';
      font-style: italic;
      font-weight: 700;
      src: url("<?php echo e($fontDir); ?>/Roboto-BoldItalic.ttf") format('truetype');
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', 'DejaVu Sans', Arial, Helvetica, sans-serif;
      font-size: 11pt;
      line-height: 1.5;
      color: #1f2937;
    }

    /* -- Repeating header (logo left, contact right) ---------------- */
    .pdf-header {
      position: fixed;
      top: -130pt;
      left: 0;
      right: 0;
      height: 100pt;
      border-bottom: 1pt solid #024768;
      padding-bottom: 6pt;
    }
    .pdf-header table { width: 100%; border-collapse: collapse; }
    .pdf-header td { vertical-align: middle; padding: 0; }
    .pdf-header .logo-cell { width: 260px; }
    .pdf-header .logo-cell img { width: 254px; height: 63px; }
    .pdf-header .info-cell {
      text-align: right;
      font-size: 9.5pt;
      line-height: 1.45;
      color: #1f2937;
    }
    .pdf-header .info-cell .info-line { margin: 0; }
    .pdf-header .info-cell .info-line + .info-line { margin-top: 1pt; }

    /* -- Repeating footer ------------------------------------------- */
    .pdf-footer {
      position: fixed;
      bottom: -60pt;
      left: 0;
      right: 0;
      height: 30pt;
      border-top: 1pt solid #d8dee4;
      padding-top: 6pt;
      font-size: 9pt;
      color: #6b7280;
    }
    .pdf-footer table { width: 100%; border-collapse: collapse; }
    .pdf-footer .left  { text-align: left; }
    .pdf-footer .right { text-align: right; }

    /* -- Document body ---------------------------------------------- */
    .doc-meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18pt;
    }
    .doc-meta td { vertical-align: top; padding: 0; }
    .doc-meta .left  { width: 60%; }
    .doc-meta .right { width: 40%; text-align: right; }

    .quote-title {
      font-size: 18pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 3pt;
      line-height: 1.15;
      white-space: nowrap;
    }
    .quote-subtitle {
      font-size: 11pt;
      font-weight: 400;
      color: #1f2937;
      margin: 0 0 4pt;
      line-height: 1.3;
    }
    .quote-dates {
      font-size: 10.5pt;
      color: #4b5563;
      margin: 0;
    }
    .quote-dates strong { color: #1f2937; }

    .quote-client {
      display: inline-block;
      text-align: left;
      font-size: 10pt;
      line-height: 1.45;
      background: #f9fafb;
      border: 1pt solid #d8dee4;
      padding: 10pt 14pt;
      min-width: 220pt;
    }
    .quote-client .label {
      font-size: 8.5pt;
      font-weight: 700;
      letter-spacing: 0.08em;
      color: #024768;
      text-transform: uppercase;
      margin: 0 0 4pt;
    }
    .quote-client .name {
      font-size: 12pt;
      font-weight: 700;
      margin: 0 0 4pt;
      color: #1f2937;
    }
    .quote-client .line {
      margin: 0;
      font-size: 10pt;
      color: #1f2937;
    }

    .section-intro {
      font-size: 11pt;
      color: #1f2937;
      margin: 6pt 0 12pt;
    }

    /* Items table */
    .items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
    }
    .items th, .items td {
      border: 1pt solid #d8dee4;
      padding: 8pt 10pt;
      vertical-align: top;
      font-size: 10.5pt;
    }
    .items thead th {
      background: #024768;
      color: #ffffff;
      font-weight: 700;
      text-align: left;
      letter-spacing: 0.04em;
    }
    .items th.amount, .items td.amount {
      text-align: right;
      width: 130pt;
      white-space: nowrap;
    }
    .items tbody tr:nth-child(even) td {
      background: #f9fafb;
    }
    .items tfoot td {
      background: #ffffff;
      border: 0 none transparent;
      border-top: 1pt solid #d8dee4;
      font-size: 11pt;
      padding: 6pt 10pt;
    }
    .items tfoot td.totals-label {
      text-align: right;
      color: #4b5563;
      font-weight: 400;
    }
    .items tfoot td.amount {
      font-weight: 700;
      color: #1f2937;
    }
    .items tfoot tr.grand td {
      border-top: 2pt solid #024768;
      font-size: 13pt;
      font-weight: 700;
      color: #024768;
      padding-top: 10pt;
    }

    .pay-instructions {
      margin-top: 14pt;
      padding: 12pt 14pt;
      border: 1.5pt solid #024768;
      background: #f4fafe;
      border-radius: 3pt;
      font-size: 11pt;
      color: #1f2937;
    }
    .pay-instructions strong {
      color: #024768;
    }
    .pay-instructions .email {
      font-weight: 700;
      color: #024768;
    }

    .closing {
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.55;
      margin-top: 16pt;
      page-break-inside: avoid;
    }
    .closing p { margin: 0 0 8pt; }
    .closing .signoff { margin-top: 12pt; }
  </style>
</head>
<body>

  
  <div class="pdf-header">
    <table>
      <tr>
        <td class="logo-cell">
          <?php if(extension_loaded('gd') && file_exists(public_path('images/logo.png'))): ?>
            <img src="<?php echo e(public_path('images/logo.png')); ?>" alt="Zanini Renovations">
          <?php else: ?>
            <span style="font-weight:700;color:#024768;font-size:14pt;">ZANINI RENOVATIONS</span>
          <?php endif; ?>
        </td>
        <td class="info-cell">
          <p class="info-line">53 Antonakos Dr, Carleton Place, ON K7C 0L1</p>
          <p class="info-line">(647) 834-7570</p>
          <p class="info-line">zaninirenovations@gmail.com</p>
          <p class="info-line">zaninirenovations.ca</p>
        </td>
      </tr>
    </table>
  </div>

  
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS — Invoice <?php echo e($invoice->invoice_number); ?></td>
        <td class="right"></td>
      </tr>
    </table>
  </div>

  
  <table class="doc-meta">
    <tr>
      <td class="left">
        <p class="quote-title">Invoice #<?php echo e($invoice->invoice_number); ?></p>
        <?php if($invoice->installmentTitle()): ?>
          <p class="quote-subtitle"><?php echo e($invoice->installmentTitle()); ?></p>
        <?php endif; ?>
        <p class="quote-dates">
          <strong>Date:</strong> <?php echo e($dateStr); ?><br>
          <strong>Due date:</strong> <?php echo e($dueStr); ?>

        </p>
      </td>
      <td class="right">
        <div class="quote-client">
          <p class="label">Bill to</p>
          <p class="name"><?php echo e($project->invoiceBillToName() ?: '—'); ?></p>
          <p class="line"><?php echo nl2br(e($project->invoiceBillToAddress() !== '' ? $project->invoiceBillToAddress() : '—')); ?></p>
          <p class="line"><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone) ?: '—'); ?></p>
          <p class="line"><?php echo e($project->client_email ?: '—'); ?></p>
          <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">
            Project: <?php echo e($project->name); ?>

          </p>
        </div>
      </td>
    </tr>
  </table>

  <p class="section-intro">Invoice for the following:</p>

  <?php if(!empty($hourlyPdf)): ?>
    <?php if(count($labourRows) > 0): ?>
      <p style="margin:0 0 6pt;font-size:10.5pt;font-weight:700;color:#024768;letter-spacing:0.06em;text-transform:uppercase;">Labour (HST applies)</p>
      <table class="items" style="margin-bottom:16pt;">
        <thead>
          <tr>
            <th>Description</th>
            <th class="amount">Amount (pre-tax)</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $labourRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e($row['description']); ?></td>
              <td class="amount"><?php echo e($fmt($row['amount'])); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if(count($materialsRows) > 0): ?>
      <p style="margin:0 0 6pt;font-size:10.5pt;font-weight:700;color:#024768;letter-spacing:0.06em;text-transform:uppercase;">Materials (amounts include HST)</p>
      <p style="margin:0 0 8pt;font-size:9.5pt;color:#4b5563;font-style:italic;">No additional HST is charged on materials — amounts below are the final client prices (tax-included).</p>
      <table class="items" style="margin-bottom:12pt;">
        <thead>
          <tr>
            <th>Description</th>
            <th class="amount">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $materialsRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e($row['description']); ?></td>
              <td class="amount"><?php echo e($fmt($row['amount'])); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if(count($labourRows) === 0 && count($materialsRows) === 0): ?>
      <table class="items">
        <tbody>
          <tr>
            <td colspan="2" style="font-style:italic;color:#6b7280;text-align:center;">No line items</td>
          </tr>
        </tbody>
      </table>
    <?php endif; ?>

    <table class="items">
      <tfoot>
        <tr>
          <td class="totals-label"><?php echo e($invoice->isHstExempt() ? 'Labour subtotal' : 'Labour subtotal (pre-HST)'); ?></td>
          <td class="amount"><?php echo e($fmt($hourlyLabourTotal ?? $subtotal)); ?></td>
        </tr>
        <?php if($invoice->isHstExempt()): ?>
          <tr>
            <td class="totals-label" colspan="2" style="font-style:italic;color:#4b5563;font-size:9.5pt;">
              No HST charged on this invoice (cash payment).
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td class="totals-label">HST (13% on labour only)</td>
            <td class="amount"><?php echo e($fmt($hst)); ?></td>
          </tr>
        <?php endif; ?>
        <?php if(($hourlyMaterialsTotal ?? 0) > 0.0001): ?>
          <tr>
            <td class="totals-label">Materials (tax-included)</td>
            <td class="amount"><?php echo e($fmt($hourlyMaterialsTotal)); ?></td>
          </tr>
        <?php endif; ?>
        <tr class="grand">
          <td class="totals-label">Total due</td>
          <td class="amount"><?php echo e($fmt($total)); ?></td>
        </tr>
      </tfoot>
    </table>
  <?php else: ?>
  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="amount">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td><?php echo e($row['description']); ?></td>
          <td class="amount"><?php echo e($fmt($row['amount'])); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="2" style="font-style:italic;color:#6b7280;text-align:center;">
            <?php echo e($invoice->installmentTitle() ?: 'Services rendered'); ?>

          </td>
        </tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <?php if($showDiscPdf): ?>
        <tr>
          <td class="totals-label">Subtotal (line items)</td>
          <td class="amount"><?php echo e($fmt($grossPdf)); ?></td>
        </tr>
        <tr>
          <td class="totals-label"><?php echo e($invoice->discountPdfLabel()); ?></td>
          <td class="amount">−<?php echo e($fmt($discPdf)); ?></td>
        </tr>
        <tr>
          <td class="totals-label">Net subtotal (pre-tax)</td>
          <td class="amount"><?php echo e($fmt($subtotal)); ?></td>
        </tr>
      <?php else: ?>
      <tr>
        <td class="totals-label">Subtotal</td>
        <td class="amount"><?php echo e($fmt($subtotal)); ?></td>
      </tr>
      <?php endif; ?>
      <?php if($invoice->isHstExempt()): ?>
        <tr>
          <td class="totals-label" colspan="2" style="font-style:italic;color:#4b5563;font-size:9.5pt;">
            No HST charged on this invoice (cash payment).
          </td>
        </tr>
      <?php else: ?>
        <tr>
          <td class="totals-label">HST (13%)</td>
          <td class="amount"><?php echo e($fmt($hst)); ?></td>
        </tr>
      <?php endif; ?>
      <tr class="grand">
        <td class="totals-label">Total</td>
        <td class="amount"><?php echo e($fmt($total)); ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <div class="pay-instructions">
    <p style="margin:0 0 6pt;">
      <strong>Please pay via e-Transfer to:</strong>
      <span class="email">zaninirenovations@gmail.com</span>
    </p>
    <p style="margin:0;font-size:10pt;color:#4b5563;">
      Please include invoice number <strong><?php echo e($invoice->invoice_number); ?></strong>
      in the e-Transfer message so payment is matched to your project.
    </p>
  </div>

  <div class="closing">
    <p style="margin-top:14pt;">Thank you for your business — it's a pleasure working with you.</p>
    <p>If you have any questions regarding this invoice, please do not hesitate to reach out.</p>
    <div class="signoff">
      <p>Best regards,</p>
      <p>
        <strong>Jonas Zanini</strong><br>
        Tel: (647) 834-7570<br>
        Zanini Renovations<br>
        E-mail: zaninirenovations@gmail.com<br>
        Website: www.zaninirenovations.ca
      </p>
    </div>
  </div>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/invoice/pdf.blade.php ENDPATH**/ ?>