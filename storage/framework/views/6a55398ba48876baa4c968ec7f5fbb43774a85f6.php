

<?php
  use App\Services\PavingQuotationCalculator;
  $isEdit = filled($quotation);
  $title = $isEdit ? 'Edit '.$quotation->quote_number : 'New paving stone quotation';
  $formAction = $isEdit ? route('admin.quotations.paving.update', $quotation) : route('admin.quotations.paving.store');
  $inputs = $isEdit ? ($quotation->inputs ?? []) : PavingQuotationCalculator::defaultInputs();
  foreach (PavingQuotationCalculator::defaultInputs() as $k => $v) {
      if (! array_key_exists($k, $inputs)) {
          $inputs[$k] = $v;
      }
  }
  $initialCalc = $isEdit ? ($quotation->calculation ?? null) : null;

  $linkProject = $linkProject ?? null;
?>

<?php $__env->startSection('heading', $title); ?>

<?php $__env->startPush('head'); ?>
  <style>
    /* Toolbar (match fence quotation form — paving omitted these originally) */
    .fq-form-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px 20px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }
    .fq-form-toolbar-right {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: flex-end;
      flex: 1 1 auto;
      min-width: 0;
    }
    @media (max-width: 720px) {
      .fq-form-toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      .fq-form-toolbar > a:first-child {
        width: fit-content;
      }
      .fq-form-toolbar-right {
        justify-content: flex-start;
      }
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

    .pq-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.95fr); gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .pq-layout { grid-template-columns: 1fr; } }
    .pq-nav-tabs {
      list-style: none;
      margin: 0 0 1.1rem;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      border-bottom: 1px solid var(--admin-border);
      gap: 0;
    }
    .pq-nav-tabs .nav-item {
      margin: 0 0 -1px 0;
    }
    .pq-nav-tabs .nav-link {
      display: block;
      margin: 0;
      padding: 0.5rem 1rem;
      border: 1px solid transparent;
      border-top-left-radius: 0.25rem;
      border-top-right-radius: 0.25rem;
      background: transparent;
      color: var(--admin-primary);
      font-weight: 600;
      font-size: 0.9rem;
      font-family: inherit;
      line-height: 1.4;
      cursor: pointer;
      text-align: left;
      text-decoration: none;
      box-shadow: none;
      -webkit-appearance: none;
      appearance: none;
    }
    .pq-nav-tabs .nav-link:hover:not(.active):not(:disabled) {
      color: var(--admin-primary-hover);
      border-color: var(--admin-surface-hover) var(--admin-surface-hover) var(--admin-border);
      background: var(--admin-surface-hover);
    }
    .pq-nav-tabs .nav-link:focus {
      outline: none;
    }
    .pq-nav-tabs .nav-link:focus-visible {
      box-shadow: 0 0 0 2px var(--admin-surface), 0 0 0 4px var(--admin-primary-ring);
    }
    /* Active tab: “connected” strip; bottom edge matches main area background */
    .pq-nav-tabs .nav-link.active {
      color: var(--admin-text);
      background: var(--admin-surface);
      border-color: var(--admin-border) var(--admin-border) var(--admin-bg);
    }
    .pq-nav-tabs--secondary {
      margin-bottom: 1rem;
    }
    .pq-nav-tabs--secondary .nav-link {
      font-size: 0.875rem;
      padding: 0.42rem 0.9rem;
    }
    .pq-panel[hidden] { display: none !important; }
    .pq-sticky { position: sticky; top: 88px; }
    .pq-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #fafbfc; }
    .pq-row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13px; }
    .pq-row--hl { font-weight: 700; color: #024768; font-size: 16px; border-top: 1px solid #d8dee4; margin-top: 8px; padding-top: 10px; }
    .pq-profit-pos { color: #15803d; }
    .pq-profit-neg { color: #b91c1c; }
    .pq-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 700px) { .pq-form-grid { grid-template-columns: 1fr; } }
    .pq-db-wrap { margin-top: 0; }
    .pq-db-subpanel[hidden] { display: none !important; }
    .pq-hint { color: #64748b; font-size: 11px; margin-top: 2px; }
    .pq-marble-box { border: 1px dashed #94a3b8; border-radius: 8px; padding: 14px; margin-top: 12px; background: #f8fafc; }

    /* Price database — stone rows align under table headers */
    .pq-db-stone-table { table-layout: fixed; width: 100%; }
    .pq-db-stone-th-name { width: 38%; }
    .pq-db-stone-th-sq { width: 18%; }
    .pq-db-stone-th-pr { width: 18%; }
    .pq-db-stone-th-actions { width: 26%; }
    /* Headers sit on the column guide; body cells top-align so fields line up with titles */
    .pq-db-stone-table thead th {
      vertical-align: bottom;
      padding: 0.5rem 1.1rem 0.45rem;
      line-height: 1.2;
    }
    .pq-db-stone-cell {
      padding: 0.5rem 1.1rem 0.75rem !important;
      vertical-align: top;
      background: #f8fafc;
    }
    .pq-db-stone-grid {
      display: grid;
      grid-template-columns: minmax(120px, 1.6fr) minmax(88px, 0.75fr) minmax(100px, 0.85fr) auto;
      gap: 10px 14px;
      align-items: start;
    }
    .pq-db-stone-grid--add { margin-top: 4px; }
    .pq-db-stone-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: flex-end;
      padding-top: 1px; /* optically match admin-input top padding / border */
    }
    .pq-db-hidden-form { display: none; }
    @media (max-width: 720px) {
      .pq-db-stone-grid {
        grid-template-columns: 1fr;
      }
      .pq-db-stone-actions {
        justify-content: flex-start;
      }
    }
    .pq-db-rate-inline {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }
    .pq-db-rate-input { max-width: 140px; min-width: 100px; }
    .pq-db-rate-price-cell { vertical-align: middle; }
  </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  <div class="fq-form-toolbar">
    <a href="<?php echo e(route('admin.quotations.paving.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-arrow-left"></i> Back to list
    </a>
    <div class="fq-form-toolbar-right">
      <a href="<?php echo e(route('admin.quotations.paving.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="pq-btn-draft">Save as draft</button>
      <button type="submit" form="pq-main-form" class="admin-btn admin-btn--primary admin-btn--sm" id="pq-btn-publish">
        <i class="fa fa-save"></i> <?php echo e($isEdit ? 'Save quotation' : 'Save quotation'); ?>

      </button>
    </div>
  </div>

  <?php if($isEdit && $quotation->project): ?>
    <div class="fq-linked-banner">
      <i class="fa fa-link"></i>
      <span>
        Linked to
        <a href="<?php echo e(route('admin.client-projects.show', $quotation->project)); ?>#quotation"><?php echo e($quotation->project->name); ?></a>
      </span>
    </div>
  <?php endif; ?>

  <ul class="nav nav-tabs pq-nav-tabs pq-nav-tabs--primary" role="tablist" aria-label="Quotation mode">
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link active" data-pq-tab="calc" role="tab" aria-selected="true" id="pq-tab-calc" aria-controls="pq-panel-calc">Calculator</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" data-pq-tab="db" role="tab" aria-selected="false" id="pq-tab-db" aria-controls="pq-panel-db">Price database</button>
    </li>
  </ul>

  <div id="pq-panel-calc" class="pq-panel">
    <div class="pq-layout">
      <div>
        <form id="pq-main-form" method="post" action="<?php echo e($formAction); ?>">
          <?php echo csrf_field(); ?>
          <?php if($isEdit): ?>
            <?php echo method_field('PUT'); ?>
          <?php endif; ?>
          <input type="hidden" name="save_intent" id="pq-save-intent" value="publish">
          <?php if($linkProject): ?>
            <input type="hidden" name="link_client_project_id" value="<?php echo e($linkProject->id); ?>">
          <?php endif; ?>

          <div class="admin-card">
            <h3 style="margin:0 0 14px;">Section A — Project inputs</h3>
            <div class="admin-form-group">
              <label for="pq-client">Client name <?php if(!$isEdit || !$quotation->is_draft): ?><span class="cp-required">*</span><?php endif; ?></label>
              <input type="text" id="pq-client" name="client_name" class="admin-input"
                     value="<?php echo e(old('client_name', $isEdit ? $quotation->client_name : ($linkProject?->client_name ?? ''))); ?>"
                     maxlength="160" <?php if(!old('client_name', $isEdit ? ($quotation->client_name ?? '') : '') && (!$isEdit || !($quotation->is_draft ?? false))): ?> required <?php endif; ?>>
              <p class="pq-hint">Required for a published quotation; optional for drafts.</p>
            </div>
            <div class="pq-form-grid">
              <div class="admin-form-group">
                <label for="pq-area">Total project area (sq ft) <span class="cp-required">*</span></label>
                <input type="number" id="pq-area" name="total_area_sq_ft" class="admin-input pq-calc-in" required min="1" step="0.01"
                       value="<?php echo e(old('total_area_sq_ft', $inputs['total_area_sq_ft'])); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-stone">Stone type</label>
                <select id="pq-stone" name="paving_stone_type_id" class="admin-input">
                  <option value="">— Custom —</option>
                  <?php $__currentLoopData = $stoneTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($st->id); ?>" data-sq="<?php echo e($st->sq_ft_per_stone); ?>" data-pr="<?php echo e($st->price_per_stone); ?>"
                      <?php if((int) old('paving_stone_type_id', $inputs['paving_stone_type_id'] ?? 0) === (int) $st->id): echo 'selected'; endif; ?>>
                      <?php echo e($st->name); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
              </div>
              <div class="admin-form-group">
                <label for="pq-sqft">Sq ft per stone <span class="cp-required">*</span></label>
                <input type="number" id="pq-sqft" name="stone_sq_ft_per_stone" class="admin-input pq-calc-in" required step="0.0001" min="0.0001"
                       value="<?php echo e(old('stone_sq_ft_per_stone', $inputs['stone_sq_ft_per_stone'])); ?>">
                <p class="pq-hint">Auto from database; editable override (Excel B7).</p>
              </div>
              <div class="admin-form-group">
                <label for="pq-stprice">Stone unit price ($/stone, before tax) <span class="cp-required">*</span></label>
                <input type="text" id="pq-stprice" name="stone_price_per_stone" class="admin-input pq-calc-in cp-money-input" required
                       inputmode="decimal" autocomplete="off" placeholder="0.00"
                       value="<?php echo e(old('stone_price_per_stone', number_format((float) ($inputs['stone_price_per_stone'] ?? 0), 2, '.', ''))); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-helper">Helper hourly rate ($/hr)</label>
                <input type="text" id="pq-helper" name="helper_hourly_rate" class="admin-input pq-calc-in cp-money-input"
                       inputmode="decimal" autocomplete="off" placeholder="0.00"
                       value="<?php echo e(old('helper_hourly_rate', number_format((float) ($inputs['helper_hourly_rate'] ?? 0), 2, '.', ''))); ?>">
                <p class="pq-hint">Set to 0 to disable helper hours (Excel IF(B9&gt;0,…)).</p>
              </div>
              <div class="admin-form-group">
                <label for="pq-owner">Owner hourly rate ($/hr)</label>
                <input type="text" id="pq-owner" name="owner_hourly_rate" class="admin-input pq-calc-in cp-money-input"
                       inputmode="decimal" autocomplete="off" placeholder="0.00"
                       value="<?php echo e(old('owner_hourly_rate', number_format((float) ($inputs['owner_hourly_rate'] ?? 0), 2, '.', ''))); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-mini">Mini skid steer (days)</label>
                <input type="number" id="pq-mini" name="mini_skid_days" class="admin-input pq-calc-in" step="0.1" min="0"
                       value="<?php echo e(old('mini_skid_days', $inputs['mini_skid_days'])); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-plate">Plate compactor (days)</label>
                <input type="number" id="pq-plate" name="plate_compactor_days" class="admin-input pq-calc-in" step="0.1" min="0"
                       value="<?php echo e(old('plate_compactor_days', $inputs['plate_compactor_days'])); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-profit">Profit margin (%)</label>
                <input type="number" id="pq-profit" name="profit_margin" class="admin-input pq-calc-in" step="0.01" min="0" max="100"
                       value="<?php echo e(old('profit_margin', (($inputs['profit_margin'] ?? 0.05) <= 1 ? ($inputs['profit_margin'] ?? 0.05) * 100 : ($inputs['profit_margin'] ?? 5)))); ?>">
                <p class="pq-hint">5 = 5% (applied to the tax-inclusive subtotal before profit, matching Excel D69×B12).</p>
              </div>
              <div class="admin-form-group">
                <label for="pq-waste">Waste factor (%)</label>
                <input type="number" id="pq-waste" name="waste_factor" class="admin-input pq-calc-in" step="0.1" min="0" max="100"
                       value="<?php echo e(old('waste_factor', (($inputs['waste_factor'] ?? 0.05) <= 1 ? ($inputs['waste_factor'] ?? 0.05) * 100 : ($inputs['waste_factor'] ?? 5)))); ?>">
                <p class="pq-hint">5 = 5% waste on stone count (CEILING(area/sqft×(1+waste))).</p>
              </div>
              <div class="admin-form-group">
                <label for="pq-depth">Excavation depth (in)</label>
                <input type="number" id="pq-depth" name="excavation_depth_in" class="admin-input pq-calc-in" step="0.5" min="1" max="48"
                       value="<?php echo e(old('excavation_depth_in', $inputs['excavation_depth_in'])); ?>">
              </div>
              <div class="admin-form-group">
                <label for="pq-hst">HST rate</label>
                <input type="number" id="pq-hst" name="hst_rate" class="admin-input pq-calc-in" step="0.01" min="0" max="30"
                       value="<?php echo e(old('hst_rate', (($inputs['hst_rate'] ?? 0.13) <= 0.5 ? ($inputs['hst_rate'] ?? 0.13) * 100 : ($inputs['hst_rate'] ?? 13)))); ?>">
                <p class="pq-hint">13 = 13% Ontario HST on materials &amp; equipment.</p>
              </div>
            </div>

            <div class="pq-marble-box">
              <label class="admin-form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="marble_enabled" value="1" class="pq-calc-in" id="pq-marble-on"
                  <?php if(old('marble_enabled', $inputs['marble_enabled'] ?? false)): echo 'checked'; endif; ?>>
                <span>Part of this area will use marble / accent stones?</span>
              </label>
              <div class="pq-form-grid" id="pq-marble-fields" style="margin-top:10px;<?php echo e(old('marble_enabled', $inputs['marble_enabled'] ?? false) ? '' : 'display:none;'); ?>">
                <div class="admin-form-group">
                  <label for="pq-marble-stone">Marble stone type</label>
                  <select id="pq-marble-stone" name="marble_stone_type_id" class="admin-input">
                    <option value="">—</option>
                    <?php $__currentLoopData = $stoneTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($st->id); ?>" data-sq="<?php echo e($st->sq_ft_per_stone); ?>" data-pr="<?php echo e($st->price_per_stone); ?>"
                        <?php if((int) old('marble_stone_type_id', $inputs['marble_stone_type_id'] ?? 0) === (int) $st->id): echo 'selected'; endif; ?>><?php echo e($st->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </select>
                </div>
                <div class="admin-form-group">
                  <label for="pq-marble-area">Marble area (sq ft)</label>
                  <input type="number" id="pq-marble-area" name="marble_area_sq_ft" class="admin-input pq-calc-in" min="0" step="0.01"
                         value="<?php echo e(old('marble_area_sq_ft', $inputs['marble_area_sq_ft'] ?? 0)); ?>">
                </div>
                <div class="admin-form-group">
                  <label for="pq-marble-sqft">Marble sq ft / stone</label>
                  <input type="number" id="pq-marble-sqft" name="marble_sq_ft_per_stone" class="admin-input pq-calc-in" step="0.0001" min="0.0001"
                         value="<?php echo e(old('marble_sq_ft_per_stone', $inputs['marble_sq_ft_per_stone'] ?? '')); ?>" placeholder="Auto from type">
                </div>
                <div class="admin-form-group">
                  <label for="pq-marble-price">Marble $/stone</label>
                  <input type="text" id="pq-marble-price" name="marble_price_per_stone" class="admin-input pq-calc-in cp-money-input"
                         inputmode="decimal" autocomplete="off" placeholder="Auto from type"
                         value="<?php echo e(old('marble_price_per_stone', isset($inputs['marble_price_per_stone']) && $inputs['marble_price_per_stone'] !== null && $inputs['marble_price_per_stone'] !== '' ? number_format((float) $inputs['marble_price_per_stone'], 2, '.', '') : '')); ?>">
                </div>
              </div>
            </div>

            <div class="admin-form-group" style="margin-top:16px;">
              <label><input type="checkbox" name="add_polymeric_sand" value="1" class="pq-calc-in" <?php if(old('add_polymeric_sand', $inputs['add_polymeric_sand'] ?? true)): echo 'checked'; endif; ?>> Add polymeric sand</label>
            </div>
            <div class="admin-form-group">
              <label><input type="checkbox" name="include_old_surface_removal" value="1" class="pq-calc-in" <?php if(old('include_old_surface_removal', $inputs['include_old_surface_removal'] ?? false)): echo 'checked'; endif; ?>> Include old surface removal</label>
            </div>
            <div class="admin-form-group">
              <label><input type="checkbox" name="include_geotextile" value="1" class="pq-calc-in" <?php if(old('include_geotextile', $inputs['include_geotextile'] ?? true)): echo 'checked'; endif; ?>> Include geotextile fabric</label>
            </div>

            <div class="admin-form-group" style="margin-top:16px;">
              <label for="pq-manual">Manual price override (CAD)</label>
              <input type="text" id="pq-manual" name="manual_price_override" class="admin-input pq-calc-in cp-money-input"
                     inputmode="decimal" autocomplete="off" placeholder="Blank = use interpolated suggested total"
                     value="<?php echo e(old('manual_price_override', isset($inputs['manual_price_override']) && $inputs['manual_price_override'] !== null && $inputs['manual_price_override'] !== '' ? number_format((float) $inputs['manual_price_override'], 2, '.', '') : '')); ?>">
              <p class="pq-hint">Leave blank to use the pricing-engine suggested total (area × interpolated $/sq ft).</p>
            </div>
          </div>
        </form>

        <?php if($isEdit && !$quotation->isLinked()): ?>
          <div class="fq-link-project-card">
            <h4 style="margin:0 0 8px;">Link to project</h4>
            <div class="fq-link-grid">
              <div>
                <h5>Existing project</h5>
                <form method="post" action="<?php echo e(route('admin.quotations.paving.link', $quotation)); ?>">
                  <?php echo csrf_field(); ?>
                  <div class="fq-link-existing-row">
                    <select name="client_project_id" class="admin-input" required>
                      <option value="">Select project…</option>
                      <?php $__currentLoopData = $linkableProjects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($p->id); ?>"><?php echo e($p->name); ?> — <?php echo e($p->client_name); ?></option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Link</button>
                  </div>
                  <div class="admin-form-group" style="margin-top:10px;">
                    <label>Line description (optional)</label>
                    <input type="text" name="link_line_description" class="admin-input" maxlength="500" placeholder="<?php echo e($quotation->summaryDescription()); ?>">
                  </div>
                </form>
              </div>
              <div class="fq-link-col fq-link-col--stack">
                <h5>Create project &amp; link</h5>
                <form method="post" action="<?php echo e(route('admin.quotations.paving.create-project', $quotation)); ?>" class="fq-create-project-form">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="link_line_description" value="">
                  <div class="admin-form-group">
                    <label>Project name</label>
                    <input type="text" name="fp_project_name" class="admin-input" maxlength="255" placeholder="Defaults to client + Paving">
                  </div>
                  <div class="admin-form-group">
                    <label>Client name <span class="cp-required">*</span></label>
                    <input type="text" name="fp_client_name" class="admin-input" required maxlength="255" value="<?php echo e($quotation->client_name); ?>">
                  </div>
                  <div class="admin-form-group">
                    <label>Email</label>
                    <input type="email" name="fp_client_email" class="admin-input" maxlength="255">
                  </div>
                  <div class="admin-form-group">
                    <label>Phone (10 digits)</label>
                    <input type="text" name="fp_client_phone" class="admin-input" maxlength="14" placeholder="(613) 555-0100">
                  </div>
                  <div class="admin-form-group">
                    <label>Address</label>
                    <input type="text" name="fp_client_address" class="admin-input" maxlength="500">
                  </div>
                  <button type="submit" class="admin-btn admin-btn--primary">Create project &amp; link</button>
                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <aside class="pq-sticky">
        <div class="pq-summary-card">
          <h4 style="margin:0 0 12px;">Live summary</h4>
          <div id="pq-live-root">
            <p class="admin-form-help">Adjust inputs to refresh totals…</p>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <div id="pq-panel-db" class="pq-panel" hidden>
    <?php echo $__env->make('admin.quotations.paving._database_tab', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
  </div>

  <script>
    (function () {
      var previewUrl = <?php echo json_encode(route('admin.quotations.paving.preview'), 15, 512) ?>;
      var token = document.querySelector('meta[name="csrf-token"]');
      var tokenV = token ? token.getAttribute('content') : '';

      document.querySelectorAll('[data-pq-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var key = btn.getAttribute('data-pq-tab');
          document.querySelectorAll('[data-pq-tab]').forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          document.getElementById('pq-panel-calc').hidden = key !== 'calc';
          document.getElementById('pq-panel-db').hidden = key !== 'db';
        });
      });

      var form = document.getElementById('pq-main-form');
      var intent = document.getElementById('pq-save-intent');
      document.getElementById('pq-btn-draft').addEventListener('click', function () {
        intent.value = 'draft';
        form.querySelector('#pq-client').removeAttribute('required');
        form.requestSubmit();
      });
      document.getElementById('pq-btn-publish').addEventListener('click', function () {
        intent.value = 'publish';
        form.querySelector('#pq-client').setAttribute('required', 'required');
      });

      function wireStoneSelect(sel, sqEl, prEl) {
        if (!sel || !sqEl || !prEl) return;
        sel.addEventListener('change', function () {
          var opt = sel.options[sel.selectedIndex];
          if (!opt || !opt.value) return;
          var sq = opt.getAttribute('data-sq');
          var pr = opt.getAttribute('data-pr');
          if (sq) sqEl.value = sq;
          if (pr) {
            prEl.value = pr;
            prEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
      wireStoneSelect(document.getElementById('pq-stone'), document.getElementById('pq-sqft'), document.getElementById('pq-stprice'));
      wireStoneSelect(document.getElementById('pq-marble-stone'), document.getElementById('pq-marble-sqft'), document.getElementById('pq-marble-price'));

      var marbleChk = document.getElementById('pq-marble-on');
      var marbleFields = document.getElementById('pq-marble-fields');
      function syncMarbleFields() {
        if (marbleFields && marbleChk) marbleFields.style.display = marbleChk.checked ? '' : 'none';
      }
      if (marbleChk) marbleChk.addEventListener('change', syncMarbleFields);
      syncMarbleFields();

      var root = document.getElementById('pq-live-root');
      var t = null;
      function fmt(n) {
        return '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function render(c) {
        if (!c || !c.summary) return;
        var s = c.summary;
        var m = c.materials;
        var f = c.fixed_costs;
        var l = c.labour;
        var html = '';
        html += '<div class="pq-row"><span>Materials (w/ HST)</span><strong>' + fmt(m.with_tax) + '</strong></div>';
        html += '<div class="pq-row"><span>Fixed costs (w/ HST)</span><strong>' + fmt(f.with_tax) + '</strong></div>';
        html += '<div class="pq-row"><span>Labour (no HST)</span><strong>' + fmt(l.total) + '</strong></div>';
        html += '<div class="pq-row"><span>Built-up total (Excel D71)</span><strong>' + fmt(s.built_up_grand_total) + '</strong></div>';
        html += '<div class="pq-row"><span>Interpolated $/sq ft</span><strong>' + s.reference_price_per_sq_ft.toFixed(2) + '</strong></div>';
        html += '<div class="pq-row pq-row--hl"><span>Suggested total</span><strong>' + fmt(s.reference_suggested_total) + '</strong></div>';
        html += '<div class="pq-row pq-row--hl"><span>Final quote</span><strong>' + fmt(s.final_quote_total) + '</strong></div>';
        var gp = s.gross_profit;
        html += '<div class="pq-row"><span>Gross profit</span><strong class="' + (gp >= 0 ? 'pq-profit-pos' : 'pq-profit-neg') + '">' + fmt(gp) + '</strong></div>';
        html += '<div class="pq-row"><span>Margin %</span><strong>' + (100 * s.margin_percent).toFixed(1) + '%</strong></div>';
        html += '<div class="pq-row"><span>Effective $/hr (profit)</span><strong>' + s.effective_hourly_profit.toFixed(2) + '</strong></div>';
        html += '<div class="pq-row"><span>Est. working days (2×8h)</span><strong>' + s.working_days + '</strong></div>';
        root.innerHTML = html;
      }
      function runPreview() {
        var fd = new FormData(form);
        fetch(previewUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': tokenV, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(render)
          .catch(function () { root.innerHTML = '<p class="admin-form-help">Could not refresh preview.</p>'; });
      }
      function schedule() {
        clearTimeout(t);
        t = setTimeout(runPreview, 220);
      }
      form.addEventListener('input', schedule);
      form.addEventListener('change', schedule);

      var initial = <?php echo json_encode($initialCalc, 15, 512) ?>;
      if (initial) { render(initial); } else { runPreview(); }
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\quotations\paving\create.blade.php ENDPATH**/ ?>