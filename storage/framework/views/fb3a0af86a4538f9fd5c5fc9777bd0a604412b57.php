
<?php if (! ($legacy)): ?>
  <?php if($quotation->hasContent()): ?>
    <div class="cp-quotation-subtotal-override" style="margin:12px 0;padding:12px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
      <form method="post"
            action="<?php echo e(route('admin.client-projects.quotation.subtotal-override', [$project, $quotation])); ?>"
            class="cp-inline-form cp-quotation-subtotal-override__form">
        <?php echo csrf_field(); ?>
        <div class="admin-form-group" style="margin:0;">
          <label for="cp-q-sub-override-<?php echo e($quotation->id); ?>" style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">
            Subtotal override (pre-tax)
          </label>
          <div class="cp-quotation-subtotal-override__row" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
            <input type="text"
                   id="cp-q-sub-override-<?php echo e($quotation->id); ?>"
                   name="subtotal_override"
                   class="admin-input cp-money-input"
                   inputmode="decimal"
                   autocomplete="off"
                   style="min-width:10rem;max-width:14rem;flex:1;"
                   placeholder="0.00"
                   value="<?php echo e(old('subtotal_override', $quotation->hasSubtotalOverrideActive() ? number_format((float) $quotation->subtotal_override, 2, '.', '') : '')); ?>">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="flex-shrink:0;">
              <i class="fa fa-save"></i> Save override
            </button>
          </div>
        </div>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\client_projects\_quotation_subtotal_override_form.blade.php ENDPATH**/ ?>