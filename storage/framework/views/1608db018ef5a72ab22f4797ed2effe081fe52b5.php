
<?php
  /** @var \App\Models\ClientProjectQuotation $quotation */
  $sqDisplay = $quotation->standardDisplayTitle();
  $isStd = $quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_STANDARD;
?>
<?php if($isStd): ?>
  <h3 class="<?php echo \Illuminate\Support\Arr::toCssClasses([
      'cp-quotation-primary-heading',
      'cp-quotation-primary-heading--standard',
      $headingClass => filled($headingClass),
  ]) ?>">
    <?php if(filled($sqDisplay)): ?>
      <span class="cp-quotation-primary-heading__display-title"><?php echo e($sqDisplay); ?></span>
    <?php endif; ?>
    <span class="cp-quotation-primary-heading__kind-line">
      <span class="cp-quotation-primary-heading__kind"><?php echo e($title); ?></span>
      <span class="cp-quotation-number"><?php echo e($quotation->quotation_number); ?></span>
    </span>
  </h3>
<?php else: ?>
  <h3 class="<?php echo \Illuminate\Support\Arr::toCssClasses([
      'cp-quotation-primary-heading',
      $headingClass => filled($headingClass),
  ]) ?>">
    <?php echo e($title); ?>

    <span class="cp-quotation-number"><?php echo e($quotation->quotation_number); ?></span>
  </h3>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\client_projects\_quotation_heading_primary.blade.php ENDPATH**/ ?>