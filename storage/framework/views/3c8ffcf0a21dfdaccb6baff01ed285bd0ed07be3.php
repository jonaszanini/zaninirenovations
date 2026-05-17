

<?php
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit fence quotation '.$quotation->quote_number : 'New fence quotation';
  $formAction = $isEdit
    ? route('admin.quotations.fence.update', $quotation)
    : route('admin.quotations.fence.store');

  $initialSides = $isEdit
    ? ($quotation->sides ?? [])
    : [['length_ft' => null]];
  if (empty($initialSides)) {
    $initialSides = [['length_ft' => null]];
  }

  $initialHeight = $isEdit ? (int) round((float) $quotation->fence_height_ft) : 6;
  $initialStyle = $isEdit ? $quotation->board_style : \App\Models\FenceQuotation::BOARD_STYLE_STANDARD;
  $initialOverlap = $isEdit ? (float) $quotation->overlap_in : 0;
  $initialGates = $isEdit ? (int) $quotation->gate_count : 1;

  // Initial breakdown (if editing, use the stored snapshot so the page
  // renders with real data before the first preview XHR returns).
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;

  $linkProject = $linkProject ?? null;
?>

<?php $__env->startSection('heading', $title); ?>

<?php $__env->startPush('head'); ?>
  <style>
    
    .fq-calc-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, 0.9fr);
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1100px) {
      .fq-calc-layout { grid-template-columns: 1fr; }
    }
    .fq-left-stack {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 0;
    }
    .fq-sides-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    @media (max-width: 640px) {
      .fq-sides-grid { grid-template-columns: 1fr; }
    }
    .fq-side-card {
      border: 1px solid #e0e4ea;
      border-radius: 8px;
      padding: 12px;
      background: #fafbfc;
    }
    .fq-side-card h5 {
      margin: 0 0 8px;
      font-size: 13px;
      font-weight: 600;
      color: #374151;
    }
    .fq-side-meta {
      margin-top: 8px;
      font-size: 12px;
      color: #6b7280;
      min-height: 16px;
    }
    .fq-breakdown-card {
      position: sticky;
      top: 88px;
    }
    .fq-totals {
      margin-top: 14px;
      border-top: 1px solid #e0e4ea;
      padding-top: 12px;
    }
    .fq-totals-row {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
      font-size: 13px;
    }
    .fq-totals-row--grand {
      border-top: 1px solid #d8dee4;
      margin-top: 6px;
      padding-top: 10px;
      font-size: 17px;
      font-weight: 700;
      color: #024768;
    }
    .fq-materials-preview {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
    }
    .fq-materials-preview th,
    .fq-materials-preview td {
      padding: 6px 8px;
      border-bottom: 1px solid #eef0f3;
      text-align: left;
      vertical-align: top;
    }
    .fq-materials-preview th { background: #f5f7f9; font-weight: 600; }
    .fq-materials-preview .num { text-align: right; font-variant-numeric: tabular-nums; }
    .fq-breakdown-note {
      color: #6b7280;
      font-size: 11px;
      display: block;
      margin-top: 2px;
    }
    .fq-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eef5fb;
      color: #024768;
      font-size: 11px;
      font-weight: 600;
    }
    .fq-inline-controls {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      align-items: start;
    }
    .fq-inline-controls .admin-form-group {
      margin: 0;
      min-width: 0;
    }
    .fq-inline-controls .admin-input {
      width: 100%;
    }
    @media (max-width: 1100px) {
      .fq-inline-controls {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 640px) {
      .fq-inline-controls {
        grid-template-columns: 1fr;
      }
    }
    .fq-overlap-wrap { display: none; }
    .fq-overlap-wrap.is-active { display: block; }
    .fq-sides-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .fq-sides-actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    @media (max-width: 640px) {
      .fq-sides-actions {
        width: 100%;
      }
      .fq-sides-actions .admin-btn {
        flex: 1 1 calc(50% - 3px);
      }
    }
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .fq-form-toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }
    .fq-link-project-card {
      border: 1px dashed #c9d3e0;
      background: #fbfcfd;
      border-radius: 10px;
      padding: 18px 20px;
      margin-top: 20px;
    }
    .fq-link-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0;
      margin-top: 14px;
      align-items: start;
    }
    .fq-link-divider {
      border: 0;
      border-top: 1px solid #d7e4ef;
      margin: 16px 0;
    }
    .fq-link-col h5 {
      margin: 0 0 8px;
      font-size: 14px;
      font-weight: 600;
      color: #024768;
    }
    .fq-link-existing-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
    }
    @media (max-width: 640px) {
      .fq-link-existing-row {
        grid-template-columns: 1fr;
      }
      .fq-link-existing-row .admin-btn {
        width: 100%;
        min-height: 46px;
      }
    }
    .fq-create-project-form .admin-form-group {
      margin-bottom: 12px;
    }
    .fq-create-project-form .admin-btn--primary {
      width: 100%;
      margin-top: 4px;
      min-height: 46px;
    }
    .fq-linked-banner {
      display: flex;
      gap: 8px;
      align-items: center;
      background: #e8f4ea;
      color: #0f5132;
      border: 1px solid #bfddc6;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 16px;
    }
    .fq-linked-banner a { color: #0f5132; font-weight: 600; }
  </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  
  <div class="fq-form-toolbar">
    <a href="<?php echo e(route('admin.quotations.fence.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to fence quotations
    </a>
    <div class="fq-form-toolbar-right">
      <a href="<?php echo e(route('admin.quotations.fence.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      <?php if($isEdit): ?>
        <a href="<?php echo e(route('admin.quotations.fence.purchase-list', $quotation)); ?>"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-print"></i> Print Material List
        </a>
      <?php endif; ?>
      <button type="submit" form="fq-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> <?php echo e($isEdit ? 'Save changes' : 'Save quotation'); ?>

      </button>
    </div>
  </div>

  <?php if($isEdit && $quotation->project): ?>
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to project
        <a href="<?php echo e(route('admin.client-projects.show', $quotation->project)); ?>#quotation"><?php echo e($quotation->project->name); ?></a>
        <?php if($quotation->linked_at): ?>
          · <?php echo e(\App\Models\ClientProject::formatDateTime($quotation->linked_at)); ?>

        <?php endif; ?>
      </span>
    </div>
  <?php endif; ?>

  <?php if(!empty($zeroPricedMaterials)): ?>
    <div class="admin-flash admin-flash--warning">
      <strong>Heads up:</strong> these materials have no price set yet — the calculator
      will treat them as $0.00:
      <?php echo e(implode(', ', $zeroPricedMaterials)); ?>.
      <a href="<?php echo e(route('admin.quotations.fence.config')); ?>" style="text-decoration:underline;">
        Open configuration
      </a>.
    </div>
  <?php endif; ?>

  <?php if($errors->any()): ?>
    <div class="admin-flash admin-flash--error">
      <strong>Please fix the following:</strong>
      <ul style="margin:6px 0 0 18px;">
        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li><?php echo e($error); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="fq-calc-layout">
    
    <div class="fq-left-stack">
      <form id="fq-form" method="post" action="<?php echo e($formAction); ?>" class="admin-card">
        <?php echo csrf_field(); ?>
        <?php if($isEdit): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
        <?php if($linkProject): ?>
          <input type="hidden" name="link_client_project_id" value="<?php echo e($linkProject->id); ?>">
        <?php endif; ?>

        
        <div class="admin-form-group">
          <label for="fq-client-name">Client name <span class="admin-required">*</span></label>
          <input id="fq-client-name" type="text" name="client_name"
                 class="admin-input class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_name')]) ?>""
                 value="<?php echo e(old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? ''))); ?>"
                 maxlength="160" required>
        </div>

        
        <div class="fq-inline-controls">
          <div class="admin-form-group">
            <label>Fence height</label>
            <select name="fence_height_ft" id="fq-height" class="admin-input">
              <option value="6" <?php if((int) $initialHeight === 6): echo 'selected'; endif; ?>>6 feet</option>
              <option value="8" <?php if((int) $initialHeight === 8): echo 'selected'; endif; ?>>8 feet</option>
            </select>
            <p class="admin-form-help">6' uses 10' posts + 6' boards. 8' uses 12' + 8'.</p>
          </div>

          <div class="admin-form-group">
            <label for="fq-gates">Gates</label>
            <input id="fq-gates" type="number" min="0" max="10" step="1"
                   name="gate_count" class="admin-input"
                   value="<?php echo e(old('gate_count', $initialGates)); ?>">
            <p class="admin-form-help">Each gate adds 1 post and <?php echo e($settings->gate_rails); ?> rails.</p>
          </div>

          <div class="admin-form-group">
            <label>Board style</label>
            <select name="board_style" id="fq-style" class="admin-input">
              <option value="<?php echo e(\App\Models\FenceQuotation::BOARD_STYLE_STANDARD); ?>"
                      <?php if($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_STANDARD): echo 'selected'; endif; ?>>
                Standard (1/8" gap)
              </option>
              <option value="<?php echo e(\App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING); ?>"
                      <?php if($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING): echo 'selected'; endif; ?>>
                Overlapping
              </option>
            </select>
          </div>

          <div class="admin-form-group fq-overlap-wrap" id="fq-overlap-wrap"
               style="<?php echo e($initialStyle === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING ? 'display:block;' : ''); ?>">
            <label for="fq-overlap">Overlap (inches)</label>
            <input id="fq-overlap" type="number" min="0" max="6" step="0.125"
                   name="overlap_in" class="admin-input"
                   value="<?php echo e(old('overlap_in', $initialOverlap)); ?>">
          </div>
        </div>

        
        <div class="admin-form-group" style="margin-top:10px;">
          <div class="fq-sides-header">
            <div>
              <label style="margin-bottom:0;">Sides (up to 4)</label>
              <p class="admin-form-help" style="margin:2px 0 0;">Enter each side's length in feet.</p>
            </div>
            <div class="fq-sides-actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-add-side">
                <i class="fa fa-plus"></i> Add side
              </button>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="fq-remove-side">
                <i class="fa fa-minus"></i> Remove last
              </button>
            </div>
          </div>
        </div>

        <div id="fq-sides" class="fq-sides-grid"></div>

        <input type="hidden" name="_serialised" id="fq-serialised" value="">
      </form>

      
      <?php if($isEdit && !$quotation->project): ?>
        <?php
          $linkable = $linkableProjects ?? collect();
        ?>
        <div class="admin-card fq-link-project-card">
          <h4 style="margin:0 0 6px;">Connect to a project</h4>
          <p class="admin-form-help" style="margin:0;">
            Add this fence estimate as a line on a project <strong>Quotation</strong> (pre-tax materials + labour — the project quotation adds HST).
          </p>

          <div class="fq-link-grid">
            <div class="fq-link-col">
              <h5><i class="fa fa-link"></i> Existing project</h5>
              <?php if($linkable->isNotEmpty()): ?>
                <form method="post" action="<?php echo e(route('admin.quotations.fence.link', $quotation)); ?>" class="fq-link-existing-form">
                  <?php echo csrf_field(); ?>
                  <div class="fq-link-existing-row">
                    <div class="admin-form-group" style="margin:0;">
                      <label for="fq-link-project">Project</label>
                      <select id="fq-link-project" name="client_project_id" class="admin-input" required>
                        <option value="" disabled selected>Choose a project…</option>
                        <?php $__currentLoopData = $linkable; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <option value="<?php echo e($p->id); ?>"><?php echo e($p->client_name); ?> — <?php echo e($p->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                      </select>
                    </div>
                    <button type="submit" class="admin-btn admin-btn--primary">
                      <i class="fa fa-link"></i> Link
                    </button>
                  </div>
                </form>
              <?php else: ?>
                <p class="admin-form-help" style="margin:0;">No active projects to link. Create a new one below, or finish a rejected/completed project first.</p>
              <?php endif; ?>
            </div>

            <hr class="fq-link-divider">

            <div class="fq-link-col">
              <h5><i class="fa fa-plus-circle"></i> New project</h5>
              <p class="admin-form-help" style="margin:0 0 12px;">
                Always <strong>fixed price</strong>. Only client name is required. No automated client email is sent from here.
              </p>
              <form method="post"
                    action="<?php echo e(route('admin.quotations.fence.create-project', $quotation)); ?>"
                    class="fq-create-project-form">
                <?php echo csrf_field(); ?>
                <div class="admin-form-group">
                  <label for="fq-new-project-name">Project name <span class="admin-form-help">(optional)</span></label>
                  <input type="text" id="fq-new-project-name" name="fp_project_name" class="admin-input" maxlength="255"
                         value="<?php echo e(old('fp_project_name')); ?>"
                         placeholder="Defaults to: Client name — Fence">
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-name">Client name <span class="admin-required">*</span></label>
                  <input type="text" id="fq-new-client-name" name="fp_client_name" class="admin-input <?php $__errorArgs = ['fp_client_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                         maxlength="255" required value="<?php echo e(old('fp_client_name')); ?>">
                  <?php $__errorArgs = ['fp_client_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-email">Email</label>
                  <input type="email" id="fq-new-client-email" name="fp_client_email" class="admin-input <?php $__errorArgs = ['fp_client_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                         maxlength="255" value="<?php echo e(old('fp_client_email')); ?>">
                  <?php $__errorArgs = ['fp_client_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-phone">Phone</label>
                  <input type="text" id="fq-new-client-phone" name="fp_client_phone"
                         class="admin-input cp-phone-input <?php $__errorArgs = ['fp_client_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                         inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx"
                         value="<?php echo e(old('fp_client_phone') ? \App\Models\ClientProject::formatPhone(old('fp_client_phone')) : ''); ?>">
                  <?php $__errorArgs = ['fp_client_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div class="admin-form-group">
                  <label for="fq-new-client-address">Address</label>
                  <input type="text" id="fq-new-client-address" name="fp_client_address" class="admin-input" maxlength="500"
                         value="<?php echo e(old('fp_client_address')); ?>">
                </div>
                <button type="submit" class="admin-btn admin-btn--primary">
                  <i class="fa fa-briefcase"></i> Create project &amp; add to quotation
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    
    <aside class="admin-card fq-breakdown-card">
      <h3 style="margin:0 0 6px;">Live breakdown</h3>
      <p class="admin-form-help" style="margin:0 0 10px;">
        Preview updates as you type. <span class="fq-badge" id="fq-status-badge">Calculating…</span>
      </p>

      <div id="fq-geometry" class="admin-form-help" style="margin-bottom:10px;"></div>

      <div class="admin-table-scroll">
        <table class="fq-materials-preview" id="fq-materials">
          <thead>
            <tr>
              <th>Material</th>
              <th class="num">Qty</th>
              <th class="num">Unit</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="fq-totals">
        <div class="fq-totals-row">
          <span>Materials subtotal</span>
          <span id="fq-t-materials">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>HST (13%)</span>
          <span id="fq-t-hst">CA$0.00</span>
        </div>
        <div class="fq-totals-row">
          <span>Labour <span class="admin-form-help" id="fq-t-labour-rate"></span></span>
          <span id="fq-t-labour">CA$0.00</span>
        </div>
        <div class="fq-totals-row fq-totals-row--grand">
          <span>Grand total</span>
          <span id="fq-t-grand">CA$0.00</span>
        </div>
      </div>
    </aside>
  </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var previewUrl = <?php echo json_encode(route('admin.quotations.fence.preview'), 15, 512) ?>;
  var initialSides = <?php echo json_encode($initialSides, 15, 512) ?>;
  var initialCalc = <?php echo json_encode($initialCalc, 15, 512) ?>;

  var sidesEl = document.getElementById('fq-sides');
  var addBtn = document.getElementById('fq-add-side');
  var removeBtn = document.getElementById('fq-remove-side');
  var styleSelect = document.getElementById('fq-style');
  var overlapWrap = document.getElementById('fq-overlap-wrap');
  var heightSelect = document.getElementById('fq-height');
  var gatesInput = document.getElementById('fq-gates');
  var overlapInput = document.getElementById('fq-overlap');

  var statusBadge = document.getElementById('fq-status-badge');
  var geometryEl = document.getElementById('fq-geometry');
  var materialsBody = document.querySelector('#fq-materials tbody');
  var tMaterials = document.getElementById('fq-t-materials');
  var tHst = document.getElementById('fq-t-hst');
  var tLabour = document.getElementById('fq-t-labour');
  var tLabourRate = document.getElementById('fq-t-labour-rate');
  var tGrand = document.getElementById('fq-t-grand');

  var MAX_SIDES = 4;

  // ---------- Side rows -------------------------------------------------
  function renderSides(sides) {
    sidesEl.innerHTML = '';
    sides.forEach(function (side, i) {
      var card = document.createElement('div');
      card.className = 'fq-side-card';
      card.innerHTML = [
        '<h5>Side ' + (i + 1) + '</h5>',
        '<div class="admin-form-group" style="margin:0;">',
        '  <label for="fq-side-' + i + '">Length (feet)</label>',
        '  <input id="fq-side-' + i + '" type="number" min="0.5" step="0.1"',
        '         name="sides[' + i + '][length_ft]"',
        '         class="admin-input fq-side-length"',
        '         value="' + (side && side.length_ft != null ? side.length_ft : '') + '"',
        '         data-side-index="' + i + '"',
        '         autocomplete="off">',
        '</div>',
        '<div class="fq-side-meta" data-meta="' + i + '"></div>'
      ].join('');
      sidesEl.appendChild(card);
    });
    updateAddRemoveState();
  }

  function updateAddRemoveState() {
    var n = sidesEl.querySelectorAll('.fq-side-card').length;
    addBtn.disabled = n >= MAX_SIDES;
    removeBtn.disabled = n <= 1;
  }

  addBtn.addEventListener('click', function () {
    var current = collectSides();
    if (current.length >= MAX_SIDES) return;
    current.push({ length_ft: null });
    renderSides(current);
    schedulePreview();
  });

  removeBtn.addEventListener('click', function () {
    var current = collectSides();
    if (current.length <= 1) return;
    current.pop();
    renderSides(current);
    schedulePreview();
  });

  function collectSides() {
    return Array.prototype.map.call(
      sidesEl.querySelectorAll('.fq-side-length'),
      function (input) {
        var v = parseFloat(input.value);
        return { length_ft: isFinite(v) ? v : null };
      }
    );
  }

  // ---------- Style toggle (overlap visibility) ------------------------
  function syncStyleVisibility() {
    if (styleSelect.value === <?php echo json_encode(\App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING, 15, 512) ?>) {
      overlapWrap.style.display = 'block';
    } else {
      overlapWrap.style.display = 'none';
    }
  }
  styleSelect.addEventListener('change', function () {
    syncStyleVisibility();
    schedulePreview();
  });

  // ---------- Live preview ---------------------------------------------
  var previewTimer = null;
  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 220);
  }

  function buildPayload() {
    return {
      sides: collectSides(),
      fence_height_ft: parseInt(heightSelect.value, 10) || 6,
      board_style: styleSelect.value,
      overlap_in: parseFloat(overlapInput.value) || 0,
      gate_count: parseInt(gatesInput.value, 10) || 0
    };
  }

  function runPreview() {
    var payload = buildPayload();
    // If no valid sides yet, show a hint but don't hit the server.
    var hasValidSide = payload.sides.some(function (s) { return s.length_ft && s.length_ft > 0; });
    if (!hasValidSide) {
      renderIdle('Enter at least one side length to see the breakdown.');
      return;
    }

    statusBadge.textContent = 'Calculating…';
    statusBadge.style.background = '#fff4d4';
    statusBadge.style.color = '#8a5d00';

    fetch(previewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res.ok) throw new Error('preview failed (' + res.status + ')');
        return res.json();
      })
      .then(renderCalc)
      .catch(function (err) {
        statusBadge.textContent = 'Error';
        statusBadge.style.background = '#fde1e1';
        statusBadge.style.color = '#8a1a1a';
        console.warn('[fence preview]', err);
      });
  }

  function renderIdle(msg) {
    statusBadge.textContent = 'Idle';
    statusBadge.style.background = '#eef1f5';
    statusBadge.style.color = '#556';
    geometryEl.textContent = msg || '';
    materialsBody.innerHTML = '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';
    tMaterials.textContent = 'CA$0.00';
    tHst.textContent = 'CA$0.00';
    tLabour.textContent = 'CA$0.00';
    tGrand.textContent = 'CA$0.00';
    tLabourRate.textContent = '';
  }

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function renderCalc(calc) {
    statusBadge.textContent = 'Up to date';
    statusBadge.style.background = '#e8f4ea';
    statusBadge.style.color = '#0f5132';

    var g = calc.geometry || {};
    geometryEl.innerHTML =
      '<strong>' + (g.total_linear_ft || 0) + ' linear ft</strong> · ' +
      (g.sections_total || 0) + ' section' + ((g.sections_total === 1) ? '' : 's') + ' · ' +
      (g.posts_before_waste || 0) + ' posts (pre-waste)';

    // Per-side section meta.
    (g.sides_breakdown || []).forEach(function (s) {
      var meta = document.querySelector('[data-meta="' + (s.index - 1) + '"]');
      if (meta) {
        var secLen = Number(s.section_length_ft || 0);
        meta.innerHTML =
          s.sections + ' section' + (s.sections === 1 ? '' : 's') +
          ' · ' + secLen.toFixed(2) + ' ft spacing';
      }
    });

    // Materials table.
    var rows = (calc.materials || []).map(function (m) {
      var unit = (m.unit || '');
      var notesHtml = m.notes
        ? '<span class="fq-breakdown-note">' + escapeHtml(m.notes) + '</span>'
        : '';
      return '<tr>' +
        '<td>' + escapeHtml(m.name) + notesHtml + '</td>' +
        '<td class="num">' + formatQty(m.quantity) + ' ' + escapeHtml(unit) + '</td>' +
        '<td class="num">' + money(m.unit_price) + '</td>' +
        '<td class="num">' + money(m.subtotal) + '</td>' +
        '</tr>';
    }).join('');
    materialsBody.innerHTML = rows || '<tr><td colspan="4" style="color:#6b7280;padding:12px;text-align:center;">—</td></tr>';

    var t = calc.totals || {};
    tMaterials.textContent = money(t.materials_subtotal);
    tHst.textContent = money(t.hst);
    tLabour.textContent = money(t.labour_subtotal);
    tGrand.textContent = money(t.grand_total);
    if (t.labour_rate_per_ft) {
      tLabourRate.textContent = '(' + money(t.labour_rate_per_ft) + '/ft, no HST)';
    }
  }

  function formatQty(q) {
    if (typeof q !== 'number') q = Number(q);
    if (!isFinite(q)) return '0';
    return Math.abs(q - Math.round(q)) < 1e-6
      ? String(Math.round(q))
      : q.toFixed(2);
  }

  function escapeHtml(s) {
    s = String(s == null ? '' : s);
    return s.replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c];
    });
  }

  // ---------- Wire up everything ---------------------------------------
  renderSides(initialSides);

  // Any field change re-runs the preview.
  ['input', 'change'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      if (!e.target || !e.target.closest) return;
      if (e.target.matches('#fq-form input, #fq-form select')) {
        schedulePreview();
      }
    });
  });

  syncStyleVisibility();

  if (initialCalc) {
    renderCalc(initialCalc);
  } else {
    schedulePreview();
  }
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\quotations\fence\create.blade.php ENDPATH**/ ?>