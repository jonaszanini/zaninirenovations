<div class="pq-db-wrap">
  <ul class="nav nav-tabs pq-nav-tabs pq-nav-tabs--secondary" role="tablist" aria-label="Price database sections">
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link active" data-pq-db="stones" role="tab" aria-selected="true" id="pq-db-tab-stones" aria-controls="pq-db-panel-stones">Stone types</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" data-pq-db="rates" role="tab" aria-selected="false" id="pq-db-tab-rates" aria-controls="pq-db-panel-rates">Fixed costs &amp; equipment</button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" data-pq-db="tiers" role="tab" aria-selected="false" id="pq-db-tab-tiers" aria-controls="pq-db-panel-tiers">Pricing engine</button>
    </li>
  </ul>

  <div id="pq-db-panel-stones" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-stones">
    <div class="admin-card">
      <h4 style="margin:0 0 12px;">Stone types</h4>
      <p class="admin-form-help">Used by the main and marble stone dropdowns.</p>
      <table class="admin-table pq-db-stone-table" style="margin-top:12px;">
        <thead>
          <tr>
            <th class="pq-db-stone-th-name">Name</th>
            <th class="pq-db-stone-th-sq">Sq ft / stone</th>
            <th class="pq-db-stone-th-pr">$/stone (CAD)</th>
            <th class="pq-db-stone-th-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $stoneTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="pq-db-stone-tr">
              <td colspan="4" class="pq-db-stone-cell">
                <form method="post" action="<?php echo e(route('admin.quotations.paving.database.stones.update', $st)); ?>" class="pq-db-stone-form">
                  <?php echo csrf_field(); ?>
                  <?php echo method_field('PUT'); ?>
                  <div class="pq-db-stone-grid">
                    <input type="text" name="name" value="<?php echo e($st->name); ?>" class="admin-input" required maxlength="120" aria-label="Stone name">
                    <input type="number" name="sq_ft_per_stone" value="<?php echo e($st->sq_ft_per_stone); ?>" step="0.0001" min="0.0001" class="admin-input" required aria-label="Sq ft per stone">
                    <input type="text" name="price_per_stone" inputmode="decimal" autocomplete="off"
                           value="<?php echo e(number_format((float) $st->price_per_stone, 2, '.', '')); ?>"
                           class="admin-input cp-money-input" required aria-label="Price per stone CAD">
                    <div class="pq-db-stone-actions">
                      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save</button>
                      <?php if($stoneTypes->count() > 1): ?>
                        <button type="submit" form="pq-del-stone-<?php echo e($st->id); ?>" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                      <?php endif; ?>
                    </div>
                  </div>
                </form>
                <?php if($stoneTypes->count() > 1): ?>
                  <form id="pq-del-stone-<?php echo e($st->id); ?>" method="post" action="<?php echo e(route('admin.quotations.paving.database.stones.destroy', $st)); ?>"
                        class="pq-db-hidden-form" onsubmit="return confirm('Delete this stone type?');">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
      <form method="post" action="<?php echo e(route('admin.quotations.paving.database.stones.store')); ?>" class="admin-card pq-db-add-stone" style="margin-top:16px;padding:16px;border:1px dashed #cbd5e1;">
        <?php echo csrf_field(); ?>
        <h5 style="margin:0 0 10px;">Add stone type</h5>
        <div class="pq-db-stone-grid pq-db-stone-grid--add">
          <input type="text" name="name" placeholder="Name" class="admin-input" required maxlength="120" aria-label="New stone name">
          <input type="number" name="sq_ft_per_stone" placeholder="Sq ft/stone" step="0.0001" min="0.0001" class="admin-input" required aria-label="Sq ft per stone">
          <input type="text" name="price_per_stone" placeholder="0.00" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" required aria-label="Price per stone CAD">
          <div class="pq-db-stone-actions">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div id="pq-db-panel-rates" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-rates" hidden>
    <div class="admin-card">
      <h4 style="margin:0 0 12px;">Fixed costs &amp; equipment</h4>
      <table class="admin-table pq-db-rates-table">
        <thead>
          <tr><th>Item</th><th>Unit</th><th>Price before tax (CAD)</th><th></th></tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $materialRates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e($rate->label); ?><br><span class="admin-form-help"><?php echo e($rate->rate_key); ?></span></td>
              <td><?php echo e($rate->unit ?: '—'); ?></td>
              <td class="pq-db-rate-price-cell">
                <form method="post" action="<?php echo e(route('admin.quotations.paving.database.rates.update', $rate)); ?>" class="pq-db-rate-form">
                  <?php echo csrf_field(); ?>
                  <?php echo method_field('PUT'); ?>
                  <div class="pq-db-rate-inline">
                    <input type="text" name="price_before_tax" inputmode="decimal" autocomplete="off"
                           value="<?php echo e(number_format((float) $rate->price_before_tax, 2, '.', '')); ?>"
                           class="admin-input cp-money-input pq-db-rate-input" required aria-label="Price <?php echo e($rate->label); ?>">
                    <button type="submit" class="admin-btn admin-btn--sm admin-btn--ghost">Update</button>
                  </div>
                </form>
              </td>
              <td></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="pq-db-panel-tiers" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-tiers" hidden>
    <div class="admin-card pq-db-tiers">
      <h4 style="margin:0 0 12px;">Pricing engine — $/sq ft by area</h4>
      <p class="admin-form-help">Interpolated linearly between rows (matches Excel reference table). Keep rows sorted by area.</p>
      <form method="post" action="<?php echo e(route('admin.quotations.paving.database.tiers.sync')); ?>">
        <?php echo csrf_field(); ?>
        <div class="admin-table-scroll">
          <table class="admin-table">
            <thead>
              <tr><th>#</th><th>Area (sq ft)</th><th>Price / sq ft</th></tr>
            </thead>
            <tbody id="pq-tier-rows">
              <?php $__currentLoopData = $pricingTiers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $tier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                  <td><?php echo e($idx + 1); ?></td>
                  <td><input type="number" name="tiers[<?php echo e($idx); ?>][area_sq_ft]" value="<?php echo e($tier->area_sq_ft); ?>" step="0.01" min="1" class="admin-input" required aria-label="Tier area"></td>
                  <td>
                    <input type="text"
                           name="tiers[<?php echo e($idx); ?>][price_per_sq_ft]"
                           value="<?php echo e(number_format((float) $tier->price_per_sq_ft, 4, '.', '')); ?>"
                           class="admin-input cp-money-input"
                           data-cp-money-places="4"
                           inputmode="decimal"
                           autocomplete="off"
                           required
                           aria-label="Price per sq ft (CAD)">
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
        <p class="admin-form-help" style="margin-top:10px;">
          Use <strong>Add row</strong> for extra breakpoints, then save. Areas should stay sorted ascending for clearest interpolation.
        </p>
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="margin-top:12px;">
          <i class="fa fa-save"></i> Save tier table
        </button>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" style="margin-top:12px;margin-left:8px;" id="pq-tier-add-row">
          <i class="fa fa-plus"></i> Add row
        </button>
      </form>
    </div>
  </div>
</div>
<script>
  (function () {
    function showDbSubtab(key) {
      var map = { stones: 'pq-db-panel-stones', rates: 'pq-db-panel-rates', tiers: 'pq-db-panel-tiers' };
      document.querySelectorAll('[data-pq-db]').forEach(function (b) {
        var k = b.getAttribute('data-pq-db');
        var active = k === key;
        b.classList.toggle('active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
        var id = map[k];
        if (id) {
          var panel = document.getElementById(id);
          if (panel) panel.hidden = !active;
        }
      });
    }

    document.querySelectorAll('[data-pq-db]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        showDbSubtab(btn.getAttribute('data-pq-db'));
      });
    });

    var tierBtn = document.getElementById('pq-tier-add-row');
    var tb = document.getElementById('pq-tier-rows');
    if (tierBtn && tb) {
      tierBtn.addEventListener('click', function () {
        var n = tb.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + (n + 1) + '</td>' +
          '<td><input type="number" name="tiers[' + n + '][area_sq_ft]" value="900" step="0.01" min="1" class="admin-input" required aria-label="Tier area"></td>' +
          '<td><input type="text" name="tiers[' + n + '][price_per_sq_ft]" value="25" class="admin-input cp-money-input" data-cp-money-places="4" inputmode="decimal" autocomplete="off" required aria-label="Price per sq ft (CAD)"></td>';
        tb.appendChild(tr);
        var newPrice = tr.querySelector('input[name*="[price_per_sq_ft]"]');
        if (window.cpMoneyMask && newPrice) {
          window.cpMoneyMask.wire(newPrice);
        }
      });
    }
  })();
</script>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\quotations\paving\_database_tab.blade.php ENDPATH**/ ?>