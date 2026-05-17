<?php
  use App\Models\ClientProjectQuotation;
  /** @var \App\Models\ClientProjectQuotation $quotation */
  $sqDisplay = $quotation->standardDisplayTitle();
  $isStd = $quotation->source_type === ClientProjectQuotation::SOURCE_STANDARD;
  $docKindLabel = $isStd ? ($quotation->title ?: $typeLabel) : $typeLabel;
?>
<?php if($isStd): ?>
  <?php if(filled($sqDisplay)): ?>
    <div class="cp-quotation-doc-display-title"><?php echo e($sqDisplay); ?></div>
  <?php endif; ?>
  <div class="cp-quotation-doc-title cp-quotation-doc-title--standard">
    <?php echo e($docKindLabel); ?> #<?php echo e($quotation->quotation_number); ?>

  </div>
<?php else: ?>
  <div class="cp-quotation-doc-title"><?php echo e($typeLabel); ?> #<?php echo e($quotation->quotation_number); ?></div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\client_projects\_quotation_preview_doc_heading.blade.php ENDPATH**/ ?>