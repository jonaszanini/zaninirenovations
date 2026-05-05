

<?php
  use App\Models\ClientProjectQuotation;

  $quotationRows = $project->quotations->sortBy('id');
  $acceptedGrand = \App\Models\ClientProjectQuotation::acceptedGrandTotalForProject($project);
  $missingDelivery = $project->missingQuotationDeliveryChannels();
  $canDeliverByEmail = filled(trim((string) $project->client_email));
  $canDeliverBySms = \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) !== null;
  $quotationHub = route('admin.quotations.index', ['project' => $project->id]);
?>

<div class="admin-card" style="margin-bottom:1rem;">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
    <div>
      <h3 style="margin:0 0 0.25rem 0;">Quotations</h3>
      <p class="admin-form-help" style="margin:0;">
        Total of <strong>accepted</strong> quotations (excludes draft / sent / rejected):
        <strong><?php echo e(\App\Models\ClientProject::formatMoney($acceptedGrand)); ?></strong>
        <?php if($quotationRows->isNotEmpty()): ?>
          <span class="cp-contract-version">· <?php echo e($quotationRows->count()); ?> document<?php echo e($quotationRows->count() === 1 ? '' : 's'); ?></span>
        <?php endif; ?>
      </p>
      <?php if($quotationRows->count() > 1): ?>
        <p class="admin-form-help" style="margin:8px 0 0;">
          Each quotation is collapsed to a short summary — expand one to view the full PDF-style preview and workflow status.
        </p>
      <?php endif; ?>
    </div>
    <a href="<?php echo e($quotationHub); ?>"
       class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> Create quotation
    </a>
  </div>
</div>

<?php if($quotationRows->isEmpty()): ?>
  <div class="admin-card cp-contract-empty">
    <h3>Quotation</h3>
    <p class="admin-form-help">
      Create a quotation or estimate from the Fence, Standard, or Paving calculators.
      The first time you save, it attaches to this project automatically.
    </p>
    <div class="cp-contract-empty-action">
      <a href="<?php echo e($quotationHub); ?>"
         class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-th"></i> Choose quotation type
      </a>
    </div>
  </div>
<?php else: ?>
  <?php
    $useQuotationAccordion = $quotationRows->count() > 1;
  ?>
  <?php $__currentLoopData = $quotationRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quotation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $hasContent = $quotation->hasContent();
      $canSend = $hasContent && ($canDeliverByEmail || $canDeliverBySms);
      $sendConfirmMessage = $canDeliverByEmail
          ? 'Email this document to '.$project->clientEmailRecipientsLabel().'?'
          : 'Send an SMS with a secure PDF link to '.\App\Models\ClientProject::formatPhone($project->client_phone).'?';
      $typeLabel = $quotation->typeLabel();
      $title = $quotation->title ?: $typeLabel;
      $legacy = $quotation->isLegacyManualSource();
      $deleteConfirm = 'Delete '.$title.' ('.$quotation->quotation_number.') from this project? This also removes the calculator quotation from the Quotations hub.';
      $items = $hasContent ? $quotation->cleanItems() : [];
      $subtotal = $hasContent ? $quotation->subtotal() : 0.0;
      $hst = $hasContent ? $quotation->hst() : 0.0;
      $grand = $hasContent ? $quotation->grandTotal() : 0.0;
      $summDesc = $title;
      if ($hasContent && count($items) > 0) {
          $firstDesc = trim((string) ($items[0]['description'] ?? ''));
          if ($firstDesc !== '') {
              $summDesc = \Illuminate\Support\Str::limit($firstDesc, 160);
          }
      }
      if ($summDesc === $title) {
          $wd = trim((string) ($quotation->work_description ?? ''));
          if ($wd !== '') {
              $summDesc = \Illuminate\Support\Str::limit($wd, 160);
          }
      }
      if (! $hasContent) {
          $summDesc = 'No line items yet — open the calculator to add scope.';
      }
      $previewWorkDesc = trim((string) ($quotation->work_description ?? ''));
    ?>

    <?php if($useQuotationAccordion): ?>
      
      <details class="cp-quotation-accordion admin-card cp-contract-card">
        <summary class="cp-quotation-accordion__summary">
          <span class="cp-quotation-accordion__chevron" aria-hidden="true"><i class="fa fa-chevron-right"></i></span>
          <div class="cp-quotation-accordion__core">
            <div class="cp-quotation-accordion__title-row">
              <h3 class="cp-quotation-accordion__title">
                <?php echo e($title); ?>

                <span class="cp-quotation-number"><?php echo e($quotation->quotation_number); ?></span>
              </h3>
              <div class="cp-quotation-accordion__total-block">
                <?php if($hasContent): ?>
                  <span class="cp-quotation-accordion__total-hint">Total w/ HST</span>
                  <span class="cp-quotation-accordion__total"><?php echo e(\App\Models\ClientProject::formatMoney($grand)); ?></span>
                <?php else: ?>
                  <span class="cp-quotation-accordion__total cp-quotation-accordion__total--muted">—</span>
                <?php endif; ?>
              </div>
            </div>
            <p class="cp-quotation-accordion__desc"><?php echo e($summDesc); ?></p>
            <div class="cp-quotation-accordion__badges">
              <?php if($legacy): ?>
                <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy</span>
              <?php endif; ?>
              <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;"><?php echo e($quotation->statusLabel()); ?></span>
              <?php if($quotation->isSent()): ?>
                <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
                  <i class="fa fa-check"></i> Sent
                </span>
              <?php else: ?>
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Not emailed</span>
              <?php endif; ?>
              <span class="cp-contract-version"><?php echo e($typeLabel); ?></span>
            </div>
          </div>
          <div class="cp-quotation-accordion__actions" onclick="event.stopPropagation();">
            <?php if (! ($legacy)): ?>
              <a href="<?php echo e(route('admin.client-projects.quotation.edit', [$project, $quotation])); ?>"
                 class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-pencil"></i> Edit
              </a>
            <?php endif; ?>
            <?php if($legacy): ?>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Read-only legacy document.">
                <i class="fa fa-ban"></i>
              </button>
            <?php endif; ?>
            <?php if($hasContent): ?>
              <a href="<?php echo e(route('admin.client-projects.quotation.pdf', [$project, $quotation])); ?>"
                 target="_blank" rel="noopener"
                 class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-download"></i> PDF
              </a>
            <?php else: ?>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before PDF is available.">
                <i class="fa fa-download"></i> PDF
              </button>
            <?php endif; ?>
            <?php if($canSend && ! $legacy): ?>
              <form action="<?php echo e(route('admin.client-projects.quotation.send', [$project, $quotation])); ?>"
                    method="post" class="cp-inline-form"
                    onsubmit='return confirm(<?php echo json_encode($sendConfirmMessage, 15, 512) ?>);'>
                <?php echo csrf_field(); ?>
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-paper-plane"></i> <?php echo e($quotation->isSent() ? 'Resend' : 'Send'); ?>

                </button>
              </form>
            <?php else: ?>
              <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
                      title="<?php echo e($legacy ? 'Not sendable.' : (($canDeliverByEmail || $canDeliverBySms) ? 'Add line items first.' : 'Add a delivery channel.')); ?>">
                <i class="fa fa-paper-plane"></i>
              </button>
            <?php endif; ?>
            <?php if (! ($legacy)): ?>
              <form action="<?php echo e(route('admin.client-projects.quotation.destroy', [$project, $quotation])); ?>"
                    method="post" class="cp-inline-form"
                    onsubmit='return confirm(<?php echo json_encode($deleteConfirm, 15, 512) ?>);'>
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Delete quotation">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </summary>

        <div class="cp-quotation-accordion__panel">
          <div class="cp-contract-meta-row cp-quotation-accordion__panel-meta">
            <?php if($quotation->isSent()): ?>
              <span class="cp-contract-version">Sent <?php echo e(\App\Models\ClientProject::formatDateTime($quotation->sent_at)); ?> · Version <?php echo e($quotation->version); ?></span>
            <?php endif; ?>
            <span class="cp-contract-version">Last saved <?php echo e(\App\Models\ClientProject::formatDateTime($quotation->updated_at)); ?></span>
          </div>

          <div class="cp-quotation-accordion__status-row">
            <form method="post"
                  action="<?php echo e(route('admin.client-projects.quotation.status', [$project, $quotation])); ?>"
                  class="cp-inline-form"
                  style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <?php echo csrf_field(); ?>
              <label for="cp-q-status-<?php echo e($quotation->id); ?>" class="admin-form-help" style="margin:0;">Workflow status</label>
              <select id="cp-q-status-<?php echo e($quotation->id); ?>" name="status" class="admin-input" style="width:auto;min-width:160px;" onchange="this.form.submit()">
                <option value="<?php echo e(ClientProjectQuotation::STATUS_DRAFT); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_DRAFT): echo 'selected'; endif; ?>>Draft</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_SENT); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_SENT): echo 'selected'; endif; ?>>Sent</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_ACCEPTED); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_ACCEPTED): echo 'selected'; endif; ?>>Accepted</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_REJECTED); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_REJECTED): echo 'selected'; endif; ?>>Rejected</option>
              </select>
            </form>
          </div>

          <?php if(!$canDeliverByEmail && !$canDeliverBySms && ! $legacy): ?>
            <div class="cp-contract-warning">
              <i class="fa fa-exclamation-triangle"></i>
              <div>
                <strong>Cannot send until at least one delivery channel exists (email or SMS-capable phone).</strong>
                <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project</a>
              </div>
            </div>
          <?php endif; ?>

          <?php if($hasContent): ?>
            <div class="cp-contract-preview cp-quotation-preview">
              <div class="cp-quotation-doc-meta">
                <div>
                  <div class="cp-quotation-doc-title"><?php echo e($typeLabel); ?> #<?php echo e($quotation->quotation_number); ?></div>
                  <div class="cp-quotation-doc-date">Date: <?php echo e(\App\Support\EasternStandardTimeDisplay::todayLongDate()); ?></div>
                </div>
                <div class="cp-quotation-doc-client">
                  <div class="cp-party-label">CLIENT</div>
                  <div class="cp-party-name"><?php echo e($project->client_name ?: '—'); ?></div>
                  <div><?php echo e($project->client_address ?: '—'); ?></div>
                  <div><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone) ?: '—'); ?></div>
                  <div><?php echo e($project->client_email ?: '—'); ?></div>
                </div>
              </div>

              <p class="cp-quotation-intro"><?php echo e($quotation->introPhrase()); ?></p>

              <?php if($previewWorkDesc !== ''): ?>
                <div class="cp-quotation-work-description">
                  <div class="cp-quotation-work-description-title">Description of the work</div>
                  <div class="cp-quotation-work-description-body"><?php echo nl2br(e($previewWorkDesc)); ?></div>
                </div>
              <?php endif; ?>

              <table class="cp-quotation-items">
                <thead>
                  <tr>
                    <th>Description</th>
                    <th class="cp-quotation-hst-col">HST</th>
                    <th class="amount">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                      <td><?php echo nl2br(e($row['description'])); ?></td>
                      <td class="cp-quotation-hst-col"><?php echo e(!empty($row['apply_hst']) ? '13%' : '—'); ?></td>
                      <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($row['amount'])); ?></td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="totals-label" colspan="2">Subtotal</td>
                    <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($subtotal)); ?></td>
                  </tr>
                  <tr>
                    <td class="totals-label" colspan="2">HST (13% on marked lines)</td>
                    <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($hst)); ?></td>
                  </tr>
                  <tr class="grand-row">
                    <td class="totals-label" colspan="2">Total with HST</td>
                    <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($grand)); ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php else: ?>
            <div class="cp-quotation-accordion__empty-body">
              <?php if($legacy): ?>
                <p class="admin-form-help">Legacy document with no line items on file.</p>
                <a href="<?php echo e($quotationHub); ?>" class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-th"></i> Create quotation
                </a>
              <?php else: ?>
                <p class="admin-form-help">No line items yet.</p>
                <a href="<?php echo e(route('admin.client-projects.quotation.edit', [$project, $quotation])); ?>"
                   class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit in calculator
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </details>
    <?php elseif($hasContent): ?>
      <div class="admin-card cp-contract-card" style="margin-bottom:1.25rem;">
        <div class="cp-contract-header">
          <div class="cp-contract-meta">
            <h3>
              <?php echo e($title); ?>

              <span class="cp-quotation-number"><?php echo e($quotation->quotation_number); ?></span>
            </h3>
            <div class="cp-contract-meta-row">
              <?php if($legacy): ?>
                <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy (read-only)</span>
              <?php endif; ?>
              <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;"><?php echo e($quotation->statusLabel()); ?></span>
              <?php if($quotation->isSent()): ?>
                <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
                  <i class="fa fa-check"></i> Sent <?php echo e(\App\Models\ClientProject::formatDateTime($quotation->sent_at)); ?>

                </span>
                <span class="cp-contract-version">Version <?php echo e($quotation->version); ?></span>
              <?php else: ?>
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Not emailed yet</span>
              <?php endif; ?>
              <span class="cp-contract-version">
                Last saved <?php echo e(\App\Models\ClientProject::formatDateTime($quotation->updated_at)); ?>

              </span>
            </div>
          </div>

          <div class="cp-contract-actions">
            <?php if (! ($legacy)): ?>
              <a href="<?php echo e(route('admin.client-projects.quotation.edit', [$project, $quotation])); ?>"
                 class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-pencil"></i> Edit
              </a>
            <?php endif; ?>

            <?php if($legacy): ?>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Archived — open a new quotation from Quotations instead.">
                <i class="fa fa-ban"></i> Read-only
              </button>
            <?php endif; ?>

            <a href="<?php echo e(route('admin.client-projects.quotation.pdf', [$project, $quotation])); ?>"
               target="_blank"
               rel="noopener"
               class="admin-btn admin-btn--ghost admin-btn--sm">
              <i class="fa fa-download"></i> PDF
            </a>

            <?php if($canSend && ! $legacy): ?>
              <form action="<?php echo e(route('admin.client-projects.quotation.send', [$project, $quotation])); ?>"
                    method="post"
                    class="cp-inline-form"
                    onsubmit='return confirm(<?php echo json_encode($sendConfirmMessage, 15, 512) ?>);'>
                <?php echo csrf_field(); ?>
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-paper-plane"></i>
                  <?php echo e($quotation->isSent() ? 'Resend' : 'Send'); ?>

                </button>
              </form>
            <?php else: ?>
              <button type="button"
                      class="admin-btn admin-btn--primary admin-btn--sm"
                      disabled
                      title="<?php echo e($legacy ? 'Legacy document — not sendable from this flow.' : (($canDeliverByEmail || $canDeliverBySms) ? 'Add at least one line item first.' : 'Add at least one delivery channel: '.implode(', ', $missingDelivery))); ?>">
                <i class="fa fa-paper-plane"></i> Send
              </button>
            <?php endif; ?>

            <?php if (! ($legacy)): ?>
              <form action="<?php echo e(route('admin.client-projects.quotation.destroy', [$project, $quotation])); ?>"
                    method="post"
                    class="cp-inline-form"
                    onsubmit='return confirm(<?php echo json_encode($deleteConfirm, 15, 512) ?>);'>
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Remove this document from the project and delete the linked calculator quote.">
                  <i class="fa fa-trash"></i> Delete
                </button>
              </form>
            <?php endif; ?>
          </div>

          <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;">
            <form method="post"
                  action="<?php echo e(route('admin.client-projects.quotation.status', [$project, $quotation])); ?>"
                  class="cp-inline-form"
                  style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <?php echo csrf_field(); ?>
              <label for="cp-q-status-<?php echo e($quotation->id); ?>" class="admin-form-help" style="margin:0;">Workflow status</label>
              <select id="cp-q-status-<?php echo e($quotation->id); ?>" name="status" class="admin-input" style="width:auto;min-width:160px;" onchange="this.form.submit()">
                <option value="<?php echo e(ClientProjectQuotation::STATUS_DRAFT); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_DRAFT): echo 'selected'; endif; ?>>Draft</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_SENT); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_SENT): echo 'selected'; endif; ?>>Sent</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_ACCEPTED); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_ACCEPTED): echo 'selected'; endif; ?>>Accepted</option>
                <option value="<?php echo e(ClientProjectQuotation::STATUS_REJECTED); ?>" <?php if($quotation->status === ClientProjectQuotation::STATUS_REJECTED): echo 'selected'; endif; ?>>Rejected</option>
              </select>
            </form>
          </div>

          <?php if(!$canDeliverByEmail && !$canDeliverBySms && ! $legacy): ?>
            <div class="cp-contract-warning">
              <i class="fa fa-exclamation-triangle"></i>
              <div>
                <strong>Cannot send until at least one delivery channel exists (email or SMS-capable phone).</strong>
                <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project</a>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="cp-contract-preview cp-quotation-preview">
          <div class="cp-quotation-doc-meta">
            <div>
              <div class="cp-quotation-doc-title"><?php echo e($typeLabel); ?> #<?php echo e($quotation->quotation_number); ?></div>
              <div class="cp-quotation-doc-date">Date: <?php echo e(\App\Support\EasternStandardTimeDisplay::todayLongDate()); ?></div>
            </div>
            <div class="cp-quotation-doc-client">
              <div class="cp-party-label">CLIENT</div>
              <div class="cp-party-name"><?php echo e($project->client_name ?: '—'); ?></div>
              <div><?php echo e($project->client_address ?: '—'); ?></div>
              <div><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone) ?: '—'); ?></div>
              <div><?php echo e($project->client_email ?: '—'); ?></div>
            </div>
          </div>

          <p class="cp-quotation-intro"><?php echo e($quotation->introPhrase()); ?></p>

          <?php if($previewWorkDesc !== ''): ?>
            <div class="cp-quotation-work-description">
              <div class="cp-quotation-work-description-title">Description of the work</div>
              <div class="cp-quotation-work-description-body"><?php echo nl2br(e($previewWorkDesc)); ?></div>
            </div>
          <?php endif; ?>

          <table class="cp-quotation-items">
            <thead>
              <tr>
                <th>Description</th>
                <th class="cp-quotation-hst-col">HST</th>
                <th class="amount">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                  <td><?php echo nl2br(e($row['description'])); ?></td>
                  <td class="cp-quotation-hst-col"><?php echo e(!empty($row['apply_hst']) ? '13%' : '—'); ?></td>
                  <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($row['amount'])); ?></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="totals-label" colspan="2">Subtotal</td>
                <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($subtotal)); ?></td>
              </tr>
              <tr>
                <td class="totals-label" colspan="2">HST (13% on marked lines)</td>
                <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($hst)); ?></td>
              </tr>
              <tr class="grand-row">
                <td class="totals-label" colspan="2">Total with HST</td>
                <td class="amount"><?php echo e(\App\Models\ClientProject::formatMoney($grand)); ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="admin-card cp-contract-empty" style="margin-bottom:1rem;">
        <h3><?php echo e($title); ?></h3>
        <?php if($legacy): ?>
          <p class="admin-form-help">Legacy document with no line items on file. Open a new quotation from the hub if you need to replace it.</p>
          <a href="<?php echo e($quotationHub); ?>" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-th"></i> Create quotation
          </a>
        <?php else: ?>
          <p class="admin-form-help">No line items yet.</p>
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
            <a href="<?php echo e(route('admin.client-projects.quotation.edit', [$project, $quotation])); ?>"
               class="admin-btn admin-btn--primary admin-btn--sm">
              <i class="fa fa-pencil"></i> Edit in calculator
            </a>
            <?php if (! ($legacy)): ?>
              <form action="<?php echo e(route('admin.client-projects.quotation.destroy', [$project, $quotation])); ?>"
                    method="post"
                    class="cp-inline-form"
                    onsubmit='return confirm(<?php echo json_encode($deleteConfirm, 15, 512) ?>);'>
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                  <i class="fa fa-trash"></i> Delete
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_quotation.blade.php ENDPATH**/ ?>