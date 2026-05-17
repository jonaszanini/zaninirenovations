<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Invoice #'.$invoice->invoice_number.' – '.$project->name]); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>

  <?php if($project->usesInvoiceBillingOverride()): ?>
    <p style="font-size:14px;color:#374151;">
      The attached PDF is addressed to <strong><?php echo e($project->invoiceBillToName()); ?></strong> for billing purposes.
    </p>
  <?php endif; ?>

  <p>
    Please find attached <strong>Invoice #<?php echo e($invoice->invoice_number); ?></strong>
    <?php if(!$invoice->isManual()): ?>
      for the <strong><?php echo e($invoice->installmentTitle()); ?></strong>
    <?php endif; ?>
    on your <strong><?php echo e($project->name); ?></strong> project.
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 6px 12px; background: #f4fafe; border: 1px solid #024768; font-size: 15px;">
        <strong>Total due:</strong> <?php echo e(\App\Models\ClientProject::formatMoney($invoice->total)); ?>

        <?php if($invoice->due_on): ?>
          &nbsp;·&nbsp; <strong>Due:</strong> <?php echo e(\App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)); ?>

        <?php endif; ?>
      </td>
    </tr>
  </table>

  <p>
    <strong>Please pay via e-Transfer to:</strong>
    <a href="mailto:zaninirenovations@gmail.com">zaninirenovations@gmail.com</a><br>
    <span style="color:#4b5563;font-size:14px;">
      Please include invoice number <strong><?php echo e($invoice->invoice_number); ?></strong>
      in the e-Transfer message so I can match the payment to your project.
    </span>
  </p>

  <p>If you have any questions about this invoice, just reply to this email and I'll get right back to you.</p>

  <p>Thank you for your business — it's a pleasure working with you.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_invoice.blade.php ENDPATH**/ ?>