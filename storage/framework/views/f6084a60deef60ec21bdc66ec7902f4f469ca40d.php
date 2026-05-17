<?php
  $due = $invoice->due_on
    ? \Illuminate\Support\Carbon::parse($invoice->due_on)
    : null;
  $overdue = $due && $due->isPast();
?>

<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Reminder: Invoice #'.$invoice->invoice_number]); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>

  <?php if($project->usesInvoiceBillingOverride()): ?>
    <p style="font-size:14px;color:#374151;">
      That invoice is addressed to <strong><?php echo e($project->invoiceBillToName()); ?></strong> on the PDF.
    </p>
  <?php endif; ?>

  <p>
    Just a friendly reminder that <strong>Invoice #<?php echo e($invoice->invoice_number); ?></strong>
    <?php if(!$invoice->isManual()): ?>
      for the <strong><?php echo e($invoice->installmentTitle()); ?></strong>
    <?php endif; ?>
    on your <strong><?php echo e($project->name); ?></strong> project is
    <?php if($overdue): ?>
      currently <strong style="color:#b91c1c;">past its due date</strong> (<?php echo e(\App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)); ?>).
    <?php elseif($due): ?>
      due on <strong><?php echo e(\App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)); ?></strong>.
    <?php else: ?>
      still outstanding.
    <?php endif; ?>
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 6px 12px; background: #f4fafe; border: 1px solid #024768; font-size: 15px;">
        <strong>Amount due:</strong> <?php echo e(\App\Models\ClientProject::formatMoney($invoice->total)); ?>

      </td>
    </tr>
  </table>

  <p>
    I've reattached the original invoice to this message for your convenience.
    Please pay via e-Transfer to
    <a href="mailto:zaninirenovations@gmail.com"><strong>zaninirenovations@gmail.com</strong></a>
    and include invoice number <strong><?php echo e($invoice->invoice_number); ?></strong>
    in the message.
  </p>

  <p>If the payment has already been sent, please disregard this reminder. If you have any questions or need to discuss, just reply to this email.</p>

  <p>Thank you!</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_invoice_reminder.blade.php ENDPATH**/ ?>