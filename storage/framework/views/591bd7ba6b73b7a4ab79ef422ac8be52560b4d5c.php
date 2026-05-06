
<?php if (! ($legacy)): ?>
  <?php if($quotation->hasContent()): ?>
    <div class="cp-quotation-subtotal-override" style="margin:12px 0;padding:12px;background:#f9fafb;border-radius:6px;border:1px solid #e5e7eb;">
      <form method="post"
            action="<?php echo e(route('admin.client-projects.quotation.subtotal-override', [$project, $quotation])); ?>"
            class="cp-inline-form cp-quotation-subtotal-override__form">
        <?php echo csrf_field(); ?>
        <label for="cp-q-sub-override-<?php echo e($quotation->id); ?>" class="admin-form-help" style="display:block;margin-bottom:4px;">
          Subtotal override (pre-tax)
        </label>
        <div class="cp-quotation-subtotal-override__row" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
          <input type="text"
                 id="cp-q-sub-override-<?php echo e($quotation->id); ?>"
                 name="subtotal_override"
                 class="admin-input cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 style="max-width:12rem;width:100%;"
                 placeholder="0.00"
                 value="<?php echo e(old('subtotal_override', $quotation->hasSubtotalOverrideActive() ? number_format((float) $quotation->subtotal_override, 2, '.', '') : '')); ?>">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-save"></i> Save override
          </button>
        </div>
        <p class="admin-form-help" style="margin:8px 0 0;">
          Same calculator-style CAD entry as elsewhere (digits add from the right; thousands show with commas).
          Leave blank or zero to use the sum of line items
          (<strong><?php echo e(\App\Models\ClientProject::formatMoney($quotation->lineItemsSubtotal())); ?></strong>).
          Any other amount replaces that subtotal; HST stays proportional to which lines are taxable.
        </p>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_quotation_subtotal_override_form.blade.php ENDPATH**/ ?>