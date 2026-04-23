<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fence Quotation <?php echo e($quotation->quote_number); ?> — <?php echo e($quotation->client_name); ?></title>
  <?php
    /*
     * Normalise font paths to forward slashes (DomPDF + Windows issue,
     * see contract/quotation PDF templates for the full explanation).
     */
    $fontDir = str_replace('\\', '/', public_path('fonts/roboto'));

    $calc = is_array($quotation->calculation) ? $quotation->calculation : [];
    $materials = $calc['materials'] ?? [];
    $totals = $calc['totals'] ?? [];
    $geom = $calc['geometry'] ?? [];
    $inputs = $calc['inputs'] ?? [];

    $heightFt = (int) round((float) ($inputs['fence_height_ft'] ?? $quotation->fence_height_ft));
    $boardStyle = $inputs['board_style'] ?? $quotation->board_style;
    $overlap = (float) ($inputs['overlap_in'] ?? $quotation->overlap_in);
    $gateCount = (int) ($inputs['gate_count'] ?? $quotation->gate_count);
    $totalFt = (float) ($geom['total_linear_ft'] ?? collect($quotation->sides ?? [])->sum(fn ($s) => (float) ($s['length_ft'] ?? 0)));

    $fmt = fn ($n) => 'CA$'.number_format((float) $n, 2, '.', ',');
    $qty = function ($n) {
        $v = (float) $n;
        return abs($v - round($v)) < 1e-6
            ? (string) (int) round($v)
            : number_format($v, 2, '.', ',');
    };
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

    /* Repeating header (logo left, contact right) */
    .pdf-header {
      position: fixed;
      top: -130pt;
      left: 0; right: 0;
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

    /* Footer */
    .pdf-footer {
      position: fixed;
      bottom: -60pt;
      left: 0; right: 0;
      height: 30pt;
      border-top: 1pt solid #d8dee4;
      padding-top: 6pt;
      font-size: 9pt;
      color: #6b7280;
    }
    .pdf-footer table { width: 100%; border-collapse: collapse; }
    .pdf-footer .left  { text-align: left; }
    .pdf-footer .right { text-align: right; }

    /* Document body */
    .quote-doc-meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18pt;
    }
    .quote-doc-meta td { vertical-align: top; padding: 0; }
    .quote-doc-meta .left  { width: 60%; }
    .quote-doc-meta .right { width: 40%; text-align: right; }

    .quote-title {
      font-size: 17pt;
      font-weight: 700;
      color: #024768;
      margin: 0 0 4pt;
      line-height: 1.15;
      white-space: nowrap;
    }
    .quote-date {
      font-size: 10.5pt;
      color: #4b5563;
      margin: 0;
    }

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

    .quote-intro {
      font-size: 11pt;
      color: #1f2937;
      margin: 6pt 0 6pt;
    }
    .quote-spec {
      font-size: 10pt;
      color: #4b5563;
      margin: 0 0 14pt;
    }
    .quote-spec strong { color: #1f2937; }

    /* Materials table */
    .items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
    }
    .items th, .items td {
      border: 1pt solid #d8dee4;
      padding: 6pt 8pt;
      vertical-align: top;
      font-size: 10pt;
    }
    .items thead th {
      background: #024768;
      color: #ffffff;
      font-weight: 700;
      text-align: left;
      letter-spacing: 0.04em;
    }
    .items .num { text-align: right; white-space: nowrap; }
    .items .col-qty { width: 60pt; }
    .items .col-unit { width: 70pt; }
    .items .col-sub { width: 80pt; }
    .items tbody tr:nth-child(even) td { background: #f9fafb; }
    .items tfoot td {
      background: #ffffff;
      border: 0 none transparent;
      border-top: 1pt solid #d8dee4;
      font-size: 11pt;
      padding: 6pt 8pt;
    }
    .items tfoot td.totals-label {
      text-align: right;
      color: #4b5563;
      font-weight: 400;
    }
    .items tfoot td.amount {
      text-align: right;
      font-weight: 700;
      color: #1f2937;
      white-space: nowrap;
    }
    .items tfoot tr.grand td {
      border-top: 2pt solid #024768;
      font-size: 13pt;
      font-weight: 700;
      color: #024768;
      padding-top: 10pt;
    }

    .sides-summary {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 14pt;
      font-size: 10pt;
    }
    .sides-summary th, .sides-summary td {
      border: 1pt solid #e0e4ea;
      padding: 5pt 8pt;
    }
    .sides-summary thead th {
      background: #eef5fb;
      color: #024768;
      text-align: left;
    }

    .closing {
      font-size: 10.5pt;
      color: #1f2937;
      line-height: 1.55;
      margin-top: 18pt;
      page-break-inside: avoid;
    }
    .closing ul { margin: 0 0 12pt 18pt; padding: 0; }
    .closing li { margin-bottom: 4pt; }
    .closing p { margin: 0 0 8pt; }
    .closing .signoff { margin-top: 14pt; }

    .notes {
      font-size: 9pt;
      color: #6b7280;
      display: block;
      margin-top: 2pt;
    }
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
          <p class="info-line">zaninirenovation.ca</p>
        </td>
      </tr>
    </table>
  </div>

  
  <div class="pdf-footer">
    <table>
      <tr>
        <td class="left">ZANINI RENOVATIONS — Fence Quotation <?php echo e($quotation->quote_number); ?></td>
        <td class="right"></td>
      </tr>
    </table>
  </div>

  
  <table class="quote-doc-meta">
    <tr>
      <td class="left">
        <p class="quote-title">Fence Quotation #<?php echo e($quotation->quote_number); ?></p>
        <p class="quote-date">Date: <?php echo e(now()->format('F j, Y')); ?></p>
      </td>
      <td class="right">
        <div class="quote-client">
          <p class="label">Prepared for</p>
          <p class="name"><?php echo e($quotation->client_name ?: '—'); ?></p>
          <?php if($quotation->project): ?>
            <p class="line"><?php echo e($quotation->project->client_address ?: '—'); ?></p>
            <p class="line"><?php echo e(\App\Models\ClientProject::formatPhone($quotation->project->client_phone) ?: '—'); ?></p>
            <p class="line"><?php echo e($quotation->project->client_email ?: '—'); ?></p>
            <p class="line" style="margin-top:4pt;font-style:italic;color:#4b5563;">
              Project: <?php echo e($quotation->project->name); ?>

            </p>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  </table>

  
  <p class="quote-intro">
    Thank you for the opportunity to quote on your fence project. The following breakdown
    includes all structural materials and labour required to build a pressure-treated
    wood fence according to Ontario Building Code conventions.
  </p>
  <p class="quote-spec">
    <strong>Specification:</strong>
    <?php echo e($heightFt); ?>' high pressure-treated wood fence,
    <?php echo e(rtrim(rtrim(number_format($totalFt, 2), '0'), '.')); ?> total linear feet
    across <?php echo e(count($quotation->sides ?? [])); ?> <?php echo e(\Illuminate\Support\Str::plural('side', count($quotation->sides ?? []))); ?>,
    <?php echo e($gateCount); ?> <?php echo e(\Illuminate\Support\Str::plural('gate', $gateCount)); ?>,
    <?php if($boardStyle === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING): ?>
      overlapping boards (<?php echo e(rtrim(rtrim(number_format($overlap, 3), '0'), '.')); ?>" overlap).
    <?php else: ?>
      standard 1/8" gap between boards.
    <?php endif; ?>
  </p>

  
  <?php if(!empty($geom['sides_breakdown'])): ?>
    <table class="sides-summary">
      <thead>
        <tr>
          <th>Side</th>
          <th>Length (ft)</th>
          <th>Sections</th>
          <th>Section length (ft)</th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $geom['sides_breakdown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td>Side <?php echo e($s['index']); ?></td>
            <td><?php echo e(rtrim(rtrim(number_format((float) $s['length_ft'], 2), '0'), '.')); ?></td>
            <td><?php echo e($s['sections']); ?></td>
            <td><?php echo e(number_format((float) $s['section_length_ft'], 2)); ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  <?php endif; ?>

  
  <table class="items">
    <thead>
      <tr>
        <th>Material</th>
        <th class="num col-qty">Qty</th>
        <th class="num col-unit">Unit price</th>
        <th class="num col-sub">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $materials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td>
            <?php echo e($m['name'] ?? '—'); ?>

            <?php if(!empty($m['notes'])): ?>
              <span class="notes"><?php echo e($m['notes']); ?></span>
            <?php endif; ?>
          </td>
          <td class="num">
            <?php echo e($qty($m['quantity'] ?? 0)); ?> <?php echo e($m['unit'] ?? ''); ?>

          </td>
          <td class="num"><?php echo e($fmt($m['unit_price'] ?? 0)); ?></td>
          <td class="num"><?php echo e($fmt($m['subtotal'] ?? 0)); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" class="totals-label">Materials subtotal</td>
        <td class="amount"><?php echo e($fmt($totals['materials_subtotal'] ?? $quotation->materials_subtotal)); ?></td>
      </tr>
      <tr>
        <td colspan="3" class="totals-label">HST (13%) — on materials only</td>
        <td class="amount"><?php echo e($fmt($totals['hst'] ?? $quotation->hst)); ?></td>
      </tr>
      <tr>
        <td colspan="3" class="totals-label">
          Labour — <?php echo e($fmt($totals['labour_rate_per_ft'] ?? 0)); ?>/ft
          <span style="font-weight:400;color:#6b7280;">(no HST on labour)</span>
        </td>
        <td class="amount"><?php echo e($fmt($totals['labour_subtotal'] ?? $quotation->labour_total)); ?></td>
      </tr>
      <tr class="grand">
        <td colspan="3" class="totals-label">Total</td>
        <td class="amount"><?php echo e($fmt($totals['grand_total'] ?? $quotation->grand_total)); ?></td>
      </tr>
    </tfoot>
  </table>

  
  <div class="closing">
    <ul>
      <li>Prices are firm for 10 days.</li>
      <li>Work schedule is based on weather permitting days.</li>
      <li>All work is performed according to O.Reg. 332/12 (Ontario Building Code).</li>
      <li>Zanini Renovations carries $3,000,000 liability insurance.</li>
      <li>Warrantee on workmanship: 1 year. Damages caused by external forces (&ldquo;Act of God&rdquo;) such as weather are not covered.</li>
      <li>If you have any questions, please do not hesitate to call.</li>
    </ul>

    <p>We trust this quote meets with your approval and we are favoured with your order.</p>

    <div class="signoff">
      <p>Best regards,</p>
      <p>
        <strong>Jonas Zanini</strong><br>
        Tel: (647) 834-7570<br>
        Zanini Renovations<br>
        E-mail: zaninirenovations@gmail.com<br>
        Website: www.zaninirenovation.ca
      </p>
    </div>
  </div>

</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/quotations/fence/pdf.blade.php ENDPATH**/ ?>