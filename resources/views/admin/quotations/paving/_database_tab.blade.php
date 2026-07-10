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
      <h4 class="cp-form-help-spaced">Stone types</h4>
      <p class="admin-form-help">Used by the paving stone dropdowns. Enter length × width (in), click <strong>Fill</strong> (or tab out of width) to compute sq ft per stone, or enter sq ft manually. Provide either <strong>Price/sq ft</strong> or both <strong>Sq ft/stone</strong> and <strong>$/stone</strong>.</p>
      <div class="admin-table-scroll">
        <div class="pq-db-stone-list" style="margin-top:12px;">
          @foreach ($stoneTypes as $st)
            <div class="pq-db-stone-card">
              <form method="post" action="{{ route('admin.quotations.paving.database.stones.update', $st) }}" class="pq-db-stone-form pq-db-stone-editor" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="pq-db-stone-card-header">
                  <div class="pq-db-stone-thumb-wrap pq-db-stone-preview">
                    @if (filled($st->image_url))
                      <img src="{{ $st->image_url }}" alt="Stone preview" class="pq-db-stone-preview-img">
                    @else
                      <i class="fa fa-cubes pq-db-stone-preview-placeholder" aria-hidden="true"></i>
                    @endif
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Name</span>
                    <input class="admin-input" type="text" name="name" value="{{ $st->name }}" required maxlength="120" aria-label="Stone name">
                  </div>
                  @include('admin.quotations.paving._database_stone_image', ['imageUrl' => $st->image_url])
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Product link</span>
                    <div class="pq-db-stone-link-wrap">
                      <input class="admin-input" type="url" name="product_url" value="{{ $st->product_url }}" maxlength="500" placeholder="https://…" aria-label="Product link">
                      @if (filled($st->product_url))
                        <a href="{{ $st->product_url }}" class="pq-db-stone-open-link" target="_blank" rel="noopener noreferrer" title="Open product page">
                          <i class="fa fa-external-link" aria-hidden="true"></i>
                        </a>
                      @endif
                    </div>
                  </div>
                  <div class="pq-db-stone-actions">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save</button>
                    @if ($stoneTypes->count() > 1)
                      <button type="submit" form="pq-del-stone-{{ $st->id }}" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                    @endif
                  </div>
                </div>

                <div class="pq-db-stone-card-body">
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Length (in)</span>
                    <input class="admin-input pq-db-stone-len" type="number" name="length_in" value="{{ $st->length_in }}" step="0.01" min="0.01" max="999" placeholder="—" aria-label="Length in inches">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Width (in)</span>
                    <input class="admin-input pq-db-stone-wid" type="number" name="width_in" value="{{ $st->width_in }}" step="0.01" min="0.01" max="999" placeholder="—" aria-label="Width in inches">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Height (in)</span>
                    <input class="admin-input pq-db-stone-hgt" type="number" name="height_in" value="{{ $st->height_in }}" step="0.01" min="0.01" max="999" placeholder="—" aria-label="Height in inches">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Sq ft / stone</span>
                    <div class="pq-db-stone-sq-wrap">
                      <input class="admin-input pq-db-stone-sq" type="number" name="sq_ft_per_stone" value="{{ $st->sq_ft_per_stone }}" step="0.0001" min="0.0001" placeholder="—" aria-label="Sq ft per stone">
                      <button type="button" class="admin-btn admin-btn--ghost admin-btn--xs pq-db-stone-fill-sq" title="Fill sq ft from length × width ÷ 144">Fill</button>
                    </div>
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">$/stone (CAD)</span>
                    <input type="text" name="price_per_stone" inputmode="decimal" autocomplete="off"
                           value="{{ $st->price_per_stone !== null ? number_format((float) $st->price_per_stone, 2, '.', '') : '' }}"
                           class="admin-input cp-money-input pq-db-stone-price" placeholder="—" aria-label="Price per stone CAD">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Price/sq ft</span>
                    <input type="text" name="price_per_sq_ft" inputmode="decimal" autocomplete="off"
                           value="{{ $st->price_per_sq_ft !== null ? number_format((float) $st->price_per_sq_ft, 4, '.', '') : '' }}"
                           class="admin-input cp-money-input pq-db-stone-price-sqft" placeholder="Auto" aria-label="Price per sq ft CAD">
                  </div>
                </div>

                <div class="pq-db-stone-card-footer">
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Delivery Cost (CAD)</span>
                    <input type="text" name="delivery_cost" inputmode="decimal" autocomplete="off"
                           value="{{ $st->delivery_cost !== null ? number_format((float) $st->delivery_cost, 2, '.', '') : '' }}"
                           class="admin-input cp-money-input" placeholder="0.00" aria-label="Delivery cost">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Additional Cost (CAD)</span>
                    <input type="text" name="additional_cost" inputmode="decimal" autocomplete="off"
                           value="{{ $st->additional_cost !== null ? number_format((float) $st->additional_cost, 2, '.', '') : '' }}"
                           class="admin-input cp-money-input" placeholder="0.00" aria-label="Additional cost">
                  </div>
                  <div class="pq-db-field-group">
                    <span class="pq-db-field-label">Additional Cost Description</span>
                    <input type="text" name="additional_cost_description" value="{{ $st->additional_cost_description }}"
                           class="admin-input" placeholder="e.g. Pallet deposit" maxlength="255" aria-label="Additional cost description">
                  </div>
                </div>
              </form>
              @if ($stoneTypes->count() > 1)
                <form id="pq-del-stone-{{ $st->id }}" method="post" action="{{ route('admin.quotations.paving.database.stones.destroy', $st) }}"
                      class="pq-db-hidden-form" onsubmit="return confirm('Delete this stone type?');">
                  @csrf
                  @method('DELETE')
                </form>
              @endif
            </div>
          @endforeach
        </div>
      </div>
      
      @php
        $addStoneOpen = old('add_stone') === '1' || $errors->has('price_per_sq_ft') || $errors->has('image') || $errors->has('name');
      @endphp
      <form method="post" action="{{ route('admin.quotations.paving.database.stones.store') }}" id="pq-add-stone-form"
            class="admin-card pq-db-add-stone pq-db-stone-editor{{ $addStoneOpen && $errors->any() ? ' pq-db-add-stone--error' : '' }}"
            style="margin-top:24px;padding:16px;border:1px dashed #cbd5e1;background:#f8fafc;" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="add_stone" value="1">
        <h5 class="cp-breakdown-intro" style="margin-bottom:16px;">Add new stone type</h5>
        @error('price_per_sq_ft')
          <p class="admin-form-error" style="margin-bottom:12px;">{{ $message }}</p>
        @enderror
        @error('image')
          <p class="admin-form-error" style="margin-bottom:12px;">{{ $message }}</p>
        @enderror
        
        <div class="pq-db-stone-card-header">
          <div class="pq-db-stone-thumb-wrap pq-db-stone-preview">
            <img src="" alt="" class="pq-db-stone-preview-img" style="display:none;">
            <i class="fa fa-cubes pq-db-stone-preview-placeholder" aria-hidden="true"></i>
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Name</span>
            <input type="text" name="name" placeholder="Name" class="admin-input" required maxlength="120" aria-label="New stone name"
                   value="{{ old('add_stone') === '1' ? old('name') : '' }}">
          </div>
          @include('admin.quotations.paving._database_stone_image', ['imageUrl' => old('add_stone') === '1' ? old('image_url') : ''])
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Product link</span>
            <div class="pq-db-stone-link-wrap">
              <input type="url" name="product_url" placeholder="https://…" class="admin-input" maxlength="500" aria-label="Product link"
                     value="{{ old('add_stone') === '1' ? old('product_url') : '' }}">
            </div>
          </div>
          <div class="pq-db-stone-actions">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add</button>
          </div>
        </div>

        <div class="pq-db-stone-card-body">
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Length (in)</span>
            <input type="number" name="length_in" placeholder="—" step="0.01" min="0.01" max="999" class="admin-input pq-db-stone-len" aria-label="Length in inches"
                   value="{{ old('add_stone') === '1' ? old('length_in') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Width (in)</span>
            <input type="number" name="width_in" placeholder="—" step="0.01" min="0.01" max="999" class="admin-input pq-db-stone-wid" aria-label="Width in inches"
                   value="{{ old('add_stone') === '1' ? old('width_in') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Height (in)</span>
            <input type="number" name="height_in" placeholder="—" step="0.01" min="0.01" max="999" class="admin-input pq-db-stone-hgt" aria-label="Height in inches"
                   value="{{ old('add_stone') === '1' ? old('height_in') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Sq ft / stone</span>
            <div class="pq-db-stone-sq-wrap">
              <input type="number" name="sq_ft_per_stone" placeholder="—" step="0.0001" min="0.0001" class="admin-input pq-db-stone-sq" aria-label="Sq ft per stone"
                     value="{{ old('add_stone') === '1' ? old('sq_ft_per_stone') : '' }}">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--xs pq-db-stone-fill-sq" title="Fill sq ft from length × width ÷ 144">Fill</button>
            </div>
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">$/stone (CAD)</span>
            <input type="text" name="price_per_stone" placeholder="—" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input pq-db-stone-price" aria-label="Price per stone CAD"
                   value="{{ old('add_stone') === '1' ? old('price_per_stone') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Price/sq ft</span>
            <input type="text" name="price_per_sq_ft" placeholder="Auto" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input pq-db-stone-price-sqft" aria-label="Price per sq ft CAD"
                   value="{{ old('add_stone') === '1' ? old('price_per_sq_ft') : '' }}">
          </div>
        </div>

        <div class="pq-db-stone-card-footer" style="background:transparent;padding:0;border:none;">
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Delivery Cost (CAD)</span>
            <input type="text" name="delivery_cost" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" placeholder="0.00" aria-label="Delivery cost"
                   value="{{ old('add_stone') === '1' ? old('delivery_cost') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Additional Cost (CAD)</span>
            <input type="text" name="additional_cost" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" placeholder="0.00" aria-label="Additional cost"
                   value="{{ old('add_stone') === '1' ? old('additional_cost') : '' }}">
          </div>
          <div class="pq-db-field-group">
            <span class="pq-db-field-label">Additional Cost Description</span>
            <input type="text" name="additional_cost_description" class="admin-input" placeholder="e.g. Pallet deposit" maxlength="255" aria-label="Additional cost description"
                   value="{{ old('add_stone') === '1' ? old('additional_cost_description') : '' }}">
          </div>
        </div>
      </form>
    </div>
  </div>

  <div id="pq-db-panel-rates" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-rates" hidden>
    <div class="admin-card">
      <h4 style="margin:0 0 8px;">Fixed costs &amp; equipment</h4>
      <p class="admin-form-help" style="margin:0 0 16px;">Unit prices used by the calculator. Equipment day rates pair with rental days on the Calculator tab.</p>
      <div class="pq-db-rates-groups">
        @foreach ($materialRateGroups as $category => $rates)
          <section class="pq-db-rates-group">
            <h5 class="pq-db-rates-group-title">{{ $category }}</h5>
            <div class="pq-db-rates-list">
              @foreach ($rates as $rate)
                <div class="pq-db-rate-row">
                  <div class="pq-db-rate-info">
                    <span class="pq-db-rate-name">{{ $rate->label }}</span>
                    @if ($rate->unit)
                      <span class="pq-db-rate-unit">per {{ $rate->unit }}</span>
                    @endif
                  </div>
                  <form method="post" action="{{ route('admin.quotations.paving.database.rates.update', $rate) }}" class="pq-db-rate-form">
                    @csrf
                    @method('PUT')
                    <div class="pq-db-rate-inline">
                      <label class="pq-db-rate-price-label">
                        <span class="pq-db-rate-price-label-text">CAD</span>
                        <input class="admin-input" type="text" name="price_before_tax" inputmode="decimal" autocomplete="off"
                               value="{{ number_format((float) $rate->price_before_tax, 2, '.', '') }}"
                               class="admin-input cp-money-input pq-db-rate-input" required aria-label="Price {{ $rate->label }} before tax">
                      </label>
                      <button type="submit" class="admin-btn admin-btn--sm admin-btn--primary">Save</button>
                    </div>
                  </form>
                </div>
              @endforeach
            </div>
          </section>
        @endforeach
      </div>
    </div>
  </div>

  <div id="pq-db-panel-tiers" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-tiers" hidden>
    <div class="admin-card pq-db-tiers">
      <h4 class="cp-form-help-spaced">Pricing engine — $/sq ft by area</h4>
      <p class="admin-form-help">Interpolated linearly between rows (matches Excel reference table). Keep rows sorted by area.</p>
      <form method="post" action="{{ route('admin.quotations.paving.database.tiers.sync') }}">
        @csrf
        <div class="admin-table-scroll">
          <table class="admin-table">
            <thead>
              <tr><th>#</th><th>Area (sq ft)</th><th>Price / sq ft</th></tr>
            </thead>
            <tbody id="pq-tier-rows">
              @foreach ($pricingTiers as $idx => $tier)
                <tr>
                  <td>{{ $idx + 1 }}</td>
                  <td><input class="admin-input" type="number" name="tiers[{{ $idx }}][area_sq_ft]" value="{{ $tier->area_sq_ft }}" step="0.01" min="1" class="admin-input" required aria-label="Tier area"></td>
                  <td>
                    <input class="admin-input" type="text"
                           name="tiers[{{ $idx }}][price_per_sq_ft]"
                           value="{{ number_format((float) $tier->price_per_sq_ft, 4, '.', '') }}"
                           class="admin-input cp-money-input"
                           data-cp-money-places="4"
                           inputmode="decimal"
                           autocomplete="off"
                           required
                           aria-label="Price per sq ft (CAD)">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="admin-form-help cp-form-group-spaced">
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

    function sqFtFromStoneDimensions(lenInput, widInput) {
      if (!lenInput || !widInput) return null;
      var len = parseFloat(lenInput.value);
      var wid = parseFloat(widInput.value);
      if (!isFinite(len) || !isFinite(wid) || len <= 0 || wid <= 0) return null;
      return Math.round((len * wid / 144) * 10000) / 10000;
    }

    function stoneEditorRoot(el) {
      return el && el.closest ? el.closest('.pq-db-stone-editor') : null;
    }

    function fillSqFtFromRow(row) {
      if (!row) return false;
      var lenInput = row.querySelector('.pq-db-stone-len');
      var widInput = row.querySelector('.pq-db-stone-wid');
      var sqInput = row.querySelector('.pq-db-stone-sq');
      var sq = sqFtFromStoneDimensions(lenInput, widInput);
      if (sq !== null && sqInput) {
        sqInput.value = String(sq);
        sqInput.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
      }
      return false;
    }

    function autoCalcPriceSqFt(row) {
      if (!row) return;
      var sqInput = row.querySelector('.pq-db-stone-sq');
      var priceInput = row.querySelector('.pq-db-stone-price');
      var priceSqFtInput = row.querySelector('.pq-db-stone-price-sqft');
      
      if (!sqInput || !priceInput || !priceSqFtInput) return;
      
      var sq = parseFloat(sqInput.value);
      var price = parseFloat(priceInput.value.replace(/,/g, ''));
      
      if (isFinite(sq) && sq > 0 && isFinite(price)) {
        var calc = price / sq;
        priceSqFtInput.value = (Math.round(calc * 10000) / 10000).toFixed(4);
        if (window.cpMoneyMask) window.cpMoneyMask.wire(priceSqFtInput);
      }
    }

    document.querySelectorAll('.pq-db-stone-fill-sq').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var row = stoneEditorRoot(btn);
        if (!fillSqFtFromRow(row)) {
          btn.title = 'Enter length and width (in) first';
          btn.classList.add('pq-db-fill-missed');
          setTimeout(function () { btn.classList.remove('pq-db-fill-missed'); }, 1200);
        } else {
          autoCalcPriceSqFt(row);
        }
      });
    });

    document.querySelectorAll('.pq-db-stone-len, .pq-db-stone-wid').forEach(function (input) {
      input.addEventListener('blur', function () {
        var row = stoneEditorRoot(input);
        if (row && row.querySelector('.pq-db-stone-sq') && !row.querySelector('.pq-db-stone-sq').value) {
          fillSqFtFromRow(row);
          autoCalcPriceSqFt(row);
        }
      });
    });

    document.querySelectorAll('.pq-db-stone-sq, .pq-db-stone-price').forEach(function (input) {
      input.addEventListener('input', function () {
        autoCalcPriceSqFt(stoneEditorRoot(input));
      });
    });

    function syncStonePreview(editor) {
      if (!editor) return;
      var wrap = editor.querySelector('.pq-db-stone-preview');
      if (!wrap) return;
      var img = wrap.querySelector('.pq-db-stone-preview-img');
      var placeholder = wrap.querySelector('.pq-db-stone-preview-placeholder');
      var urlInput = editor.querySelector('.pq-db-stone-image-url');
      var fileInput = editor.querySelector('.pq-db-stone-image-file');
      var src = urlInput ? (urlInput.value || '').trim() : '';
      if (fileInput && fileInput.files && fileInput.files[0]) {
        src = URL.createObjectURL(fileInput.files[0]);
      }
      if (src && img) {
        img.src = src;
        img.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
      } else if (img) {
        img.style.display = 'none';
        img.removeAttribute('src');
        if (placeholder) placeholder.style.display = 'block';
      }
    }

    document.querySelectorAll('.pq-db-stone-editor').forEach(function (editor) {
      var urlInput = editor.querySelector('.pq-db-stone-image-url');
      var fileInput = editor.querySelector('.pq-db-stone-image-file');
      if (urlInput) {
        urlInput.addEventListener('input', function () { syncStonePreview(editor); });
        urlInput.addEventListener('change', function () { syncStonePreview(editor); });
      }
      if (fileInput) {
        fileInput.addEventListener('change', function () { syncStonePreview(editor); });
      }
      syncStonePreview(editor);
    });

    var addForm = document.getElementById('pq-add-stone-form');
    if (addForm && (addForm.classList.contains('pq-db-add-stone--error') || (addForm.querySelector('[name="name"]') && addForm.querySelector('[name="name"]').value))) {
      addForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      showDbSubtab('stones');
    }

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
