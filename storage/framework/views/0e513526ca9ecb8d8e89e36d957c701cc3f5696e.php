

<?php
  // Build a list of reasons the invoice can't be sent yet. The server
  // enforces these too, but surfacing them up-front stops the user from
  // clicking into the editor just to see the error.
  $sendBlockers = [];
  if (!$hasContactInfo) {
      $sendBlockers[] = 'Missing client info: '.implode(', ', $missing);
  }
  if ($invoice) {
      $completeItems = array_filter(
          $invoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
      );
      if (count($completeItems) === 0) {
          $sendBlockers[] = 'Add at least one line item with a description and amount.';
      }
      if (empty($invoice->due_on)) {
          $sendBlockers[] = 'Set a due date in the editor.';
      }
  }

  $canSend = empty($sendBlockers);
  $sendTitle = $canSend
    ? 'Generate a fresh PDF and email it to the client.'
    : implode(' ', $sendBlockers);
?>

<?php if(!$invoice): ?>
  
  <form action="<?php echo e(route('admin.client-projects.invoices.create', $project)); ?>"
        method="post"
        class="cp-inline-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="mode" value="installment">
    <input type="hidden" name="installment_number" value="<?php echo e($installmentNumber); ?>">
    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> Create Invoice
    </button>
  </form>
<?php else: ?>
  <div class="cp-invoice-actions">
    
    <?php if (! ($invoice->isPaid())): ?>
      <a href="<?php echo e(route('admin.client-projects.invoices.edit', [$project, $invoice])); ?>"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         title="Open the invoice editor">
        <i class="fa fa-pencil"></i> Edit
      </a>
    <?php endif; ?>

    
    <?php if($invoice->hasContent() || $invoice->total > 0): ?>
      <a href="<?php echo e(route('admin.client-projects.invoices.pdf', [$project, $invoice])); ?>"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         title="Open the invoice PDF">
        <i class="fa fa-file-pdf-o"></i> PDF
      </a>
    <?php endif; ?>

    
    <?php if (! ($invoice->isPaid())): ?>
      <?php if($canSend): ?>
        <form action="<?php echo e(route('admin.client-projects.invoices.send', [$project, $invoice])); ?>"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('This will generate a new PDF, email it to the client, and save it on the server. Continue?');">
          <?php echo csrf_field(); ?>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" title="<?php echo e($sendTitle); ?>">
            <i class="fa fa-paper-plane"></i>
            <?php echo e($invoice->wasEverSent() ? 'Resend' : 'Send'); ?>

          </button>
        </form>
      <?php else: ?>
        <button type="button"
                class="admin-btn admin-btn--primary admin-btn--sm"
                disabled
                title="<?php echo e($sendTitle); ?>">
          <i class="fa fa-paper-plane"></i> Send
        </button>
      <?php endif; ?>
    <?php endif; ?>

    
    <?php if($invoice->isSent()): ?>
      <?php if($hasContactInfo): ?>
        <form action="<?php echo e(route('admin.client-projects.invoices.reminder', [$project, $invoice])); ?>"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('Email a payment reminder to the client now?');">
          <?php echo csrf_field(); ?>
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Email a payment reminder to the client.">
            <i class="fa fa-bell-o"></i> Reminder
          </button>
        </form>
      <?php else: ?>
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                disabled
                title="<?php echo e($sendTitle); ?>">
          <i class="fa fa-bell-o"></i> Reminder
        </button>
      <?php endif; ?>
    <?php endif; ?>

    
    <?php if($invoice->isSent()): ?>
      <button type="button"
              class="admin-btn admin-btn--success admin-btn--sm"
              data-cp-markpaid-url="<?php echo e(route('admin.client-projects.invoices.mark-paid', [$project, $invoice])); ?>"
              data-cp-markpaid-amount="<?php echo e(number_format((float) $invoice->total, 2, '.', '')); ?>"
              data-cp-markpaid-label="Invoice #<?php echo e($invoice->invoice_number); ?>"
              title="Mark as paid and generate a receipt.">
        <i class="fa fa-check"></i> Mark paid
      </button>
    <?php endif; ?>

    
    <?php if($invoice->isPaid()): ?>
      <a href="<?php echo e(route('admin.client-projects.invoices.receipt', [$project, $invoice])); ?>"
         target="_blank"
         rel="noopener"
         class="admin-btn admin-btn--success admin-btn--sm"
         title="Open the receipt PDF.">
        <i class="fa fa-download"></i> Receipt
      </a>
    <?php endif; ?>

    
    <?php if($invoice->isDraft() && !$invoice->wasEverSent()): ?>
      <form action="<?php echo e(route('admin.client-projects.invoices.destroy', [$project, $invoice])); ?>"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm('Delete this draft invoice? This cannot be undone.');">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Delete this draft invoice.">
          <i class="fa fa-trash"></i>
        </button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_invoice_row_actions.blade.php ENDPATH**/ ?>