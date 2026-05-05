

<?php
  use App\Models\ClientProjectInvoice;
  use App\Services\MoceanSmsService;

  /** @var MoceanSmsService $moceanSms */
  $moceanSms = app(MoceanSmsService::class);

  /** First paid invoice on this project prompts for a project status in the mark-paid modal. */
  $showMarkPaidStatusPicker = $project->invoices->where('status', ClientProjectInvoice::STATUS_PAID)->isEmpty()
      && ! $project->isRejected();
  $moceanReady = $moceanSms->isConfigured();

  // Content/readiness checks shared — email also needs full contact card.
  $contentBlockers = [];
  if ($invoice) {
      $completeItems = array_filter(
          $invoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
      );
      if (count($completeItems) === 0) {
          $contentBlockers[] = 'Add at least one line item with a description and amount.';
      }
      if (empty($invoice->due_on)) {
          $contentBlockers[] = 'Set a payment due date — open the invoice editor and fill in the Due date field.';
      }
      if (!$invoice->isManual() && ! $project->invoiceLineSumMatchesInstallmentLock($invoice)) {
          $contentBlockers[] = 'Line-item subtotal must match the locked installment amount.';
      }
  }

  $contactBlockersForEmail = [];
  if (!$hasContactInfo) {
      $contactBlockersForEmail[] = 'Missing client info: '.implode(', ', $missing);
  }

  $sendBlockers = array_merge($contactBlockersForEmail, $contentBlockers);
  $canSend = empty($sendBlockers);
  $sendTitle = $canSend
    ? 'Generate a fresh PDF and email it to the client.'
    : implode(' ', $sendBlockers);

  $smsBlockers = $contentBlockers;
  if (!$moceanReady) {
      $smsBlockers[] = 'SMS gateway is not configured on the server (MOCEAN_API_TOKEN).';
  }
  if ($invoice && \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) === null) {
      $smsBlockers[] = 'Add a mobile phone number for the client (Edit project → phone).';
  }
  $canSms = empty($smsBlockers);
  $smsTitle = $canSms
    ? 'Text the client a secure link to open this invoice PDF in the browser.'
    : implode(' ', $smsBlockers);

  /** @var list<string> */
  $actionBlockers = array_values(array_unique(array_merge($sendBlockers, $smsBlockers)));
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
  <?php if(!$invoice->isPaid() && (! $canSend || ! $canSms)): ?>
    <div class="cp-contract-warning cp-invoice-send-warning">
      <i class="fa fa-exclamation-triangle"></i>
      <div>
        <strong>Send email and/or SMS may be disabled.</strong>
        Address the items below — then the buttons will activate:
        <ul class="cp-invoice-send-blockers">
          <?php $__currentLoopData = $actionBlockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e($hint); ?></li>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
        <a href="<?php echo e(route('admin.client-projects.invoices.edit', [$project, $invoice])); ?>" class="cp-invoice-send-warning-link">
          Open invoice editor
        </a>
      </div>
    </div>
  <?php endif; ?>
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

      <?php if($canSms): ?>
        <form action="<?php echo e(route('admin.client-projects.invoices.sms', [$project, $invoice])); ?>"
              method="post"
              class="cp-inline-form"
              onsubmit="return confirm('Send an SMS to the client with a secure link to view or download this invoice PDF?');">
          <?php echo csrf_field(); ?>
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="<?php echo e($smsTitle); ?>">
            <i class="fa fa-comment"></i> SMS
          </button>
        </form>
      <?php else: ?>
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                disabled
                title="<?php echo e($smsTitle); ?>">
          <i class="fa fa-comment"></i> SMS
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

    
    <?php if (! ($invoice->isPaid())): ?>
      <?php
        $canMarkPaid = $invoice->hasContent() && (float) $invoice->total > 0;
        $markPaidTitle = $canMarkPaid
          ? ($invoice->isSent()
              ? 'Mark as paid and generate a receipt.'
              : 'Mark this draft invoice as paid (e.g. cash payment) and generate a receipt.')
          : 'Add at least one line item with a description and amount before marking paid.';
      ?>
      <?php if($canMarkPaid): ?>
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm"
                data-cp-markpaid-url="<?php echo e(route('admin.client-projects.invoices.mark-paid', [$project, $invoice])); ?>"
                data-cp-markpaid-amount="<?php echo e(number_format((float) $invoice->total, 2, '.', '')); ?>"
                data-cp-markpaid-label="Invoice #<?php echo e($invoice->invoice_number); ?>"
                data-cp-markpaid-first-paid="<?php echo e($showMarkPaidStatusPicker ? '1' : '0'); ?>"
                title="<?php echo e($markPaidTitle); ?>">
          <i class="fa fa-check"></i> Mark paid
        </button>
      <?php else: ?>
        <button type="button"
                class="admin-btn admin-btn--success admin-btn--sm"
                disabled
                title="<?php echo e($markPaidTitle); ?>">
          <i class="fa fa-check"></i> Mark paid
        </button>
      <?php endif; ?>
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

    
    <?php if (! ($invoice->isPaid())): ?>
      <?php
        if ($invoice->wasEverSent()) {
            $deleteConfirm = "Invoice #{$invoice->invoice_number} has already been emailed to the client. Deleting it here will REMOVE it from our records and let you create a fresh invoice for this installment, but the client will still have the old PDF in their inbox. You should let them know to disregard the old one. Continue?";
            $deleteTitle = 'Delete this sent invoice and start the installment over.';
        } else {
            $deleteConfirm = 'Delete this draft invoice? This cannot be undone.';
            $deleteTitle = 'Delete this draft invoice.';
        }
      ?>
      <form action="<?php echo e(route('admin.client-projects.invoices.destroy', [$project, $invoice])); ?>"
            method="post"
            class="cp-inline-form"
            onsubmit="return confirm(<?php echo e(json_encode($deleteConfirm)); ?>);">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="<?php echo e($deleteTitle); ?>">
          <i class="fa fa-trash"></i>
        </button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_invoice_row_actions.blade.php ENDPATH**/ ?>