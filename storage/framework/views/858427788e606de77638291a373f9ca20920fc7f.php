<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Receipt — Invoice #'.$invoice->invoice_number]); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>

  <p>
    Thank you for your prompt payment on your <strong><?php echo e($project->name); ?></strong> project!
    Please find attached the official receipt for <strong>Invoice #<?php echo e($invoice->invoice_number); ?></strong>
    <?php if(!$invoice->isManual()): ?>
      (<?php echo e($invoice->installmentTitle()); ?>)
    <?php endif; ?>.
  </p>

  <table role="presentation" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
      <td style="padding: 10px 14px; background: #e8f8ef; border: 1px solid #22c55e; font-size: 15px; color:#15803d;">
        <strong>Amount received:</strong>
        <?php echo e(\App\Models\ClientProject::formatMoney($invoice->paid_amount ?? $invoice->total)); ?>

        <?php if($invoice->paid_at): ?>
          &nbsp;·&nbsp; <strong>Received on:</strong> <?php echo e(\App\Support\EasternStandardTimeDisplay::formatInstantLongDate($invoice->paid_at)); ?>

        <?php endif; ?>
      </td>
    </tr>
  </table>

  <p>
    If you need the receipt in a different format, or have any questions, just reply
    to this email and I'll be happy to help.
  </p>

  <p>Thanks again for trusting Zanini Renovations with your project.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_invoice_receipt.blade.php ENDPATH**/ ?>