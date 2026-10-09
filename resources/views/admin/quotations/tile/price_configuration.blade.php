@extends('admin.layouts.app')

@section('heading', 'Tile Configuration')

@push('head')
  <style>
    .config-card {
      background: var(--admin-surface);
      border-radius: 8px;
      border: 1px solid var(--admin-border);
      padding: 20px;
      margin-bottom: 24px;
      color: var(--admin-text);
    }
    .config-card h3 {
      margin: 0 0 16px;
      color: var(--admin-heading);
    }
    .table-container {
      overflow-x: auto;
      margin-bottom: 16px;
      -webkit-overflow-scrolling: touch;
    }
    .config-card table.admin-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }
    .config-card table.admin-table th,
    .config-card table.admin-table td {
      padding: 8px 12px;
      border-bottom: 1px solid var(--admin-border);
      text-align: left;
      vertical-align: middle;
    }
    .config-card table.admin-table th {
      font-size: 12px;
      text-transform: uppercase;
      color: var(--admin-muted);
      font-weight: 600;
    }
    .config-card table.admin-table .admin-input {
      min-width: 80px;
    }
  </style>
@endpush

@section('content')
  @php $qparam = ! empty($qpid) ? ['project' => $qpid] : []; @endphp
  <div class="admin-toolbar fq-list-toolbar" style="margin-bottom:16px;">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.tile.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
    </div>
  </div>

  @include('admin.quotations._shared_price_banner', ['consumer' => 'tile'])

  @if (session('success'))
    <div class="admin-banner admin-banner--success" style="margin-bottom:16px;">
      <i class="fa fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="admin-banner admin-banner--danger" style="margin-bottom:16px;">
      <i class="fa fa-exclamation-triangle"></i> Please check the form for errors.
      <ul style="margin-top: 8px; margin-bottom: 0;">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.quotations.tile.price-configuration.update') }}{{ $qpid ? '?project='.$qpid : '' }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.partials.quotation_return_quote_hidden')

    <div class="config-card">
      <h3>Mortar Types</h3>
      <div class="table-container">
        <table class="admin-table" id="mortar-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Bag (lbs)</th>
              <th>Cost ($)</th>
              <th style="width:60px;">Sort</th>
              <th style="width:70px;">Active</th>
              <th style="width:50px; text-align:center;">Del</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($mortarTypes as $index => $m)
              <tr>
                <td>
                  <input type="hidden" name="mortars[{{ $index }}][id]" value="{{ $m->id }}">
                  <input type="text" name="mortars[{{ $index }}][name]" class="admin-input" value="{{ $m->name }}" required>
                </td>
                <td><input type="number" step="0.01" name="mortars[{{ $index }}][bag_weight_lbs]" class="admin-input" value="{{ (float)$m->bag_weight_lbs }}" required></td>
                <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           name="mortars[{{ $index }}][cost_price]" class="admin-input cp-money-input"
                           value="{{ number_format((float) $m->cost_price, 4, '.', '') }}" required></td>
                <td><input type="number" name="mortars[{{ $index }}][sort_order]" class="admin-input" value="{{ $m->sort_order }}"></td>
                <td style="text-align:center;">
                  <input type="checkbox" name="mortars[{{ $index }}][is_active]" value="1" class="admin-checkbox__input--standalone" {{ $m->is_active ? 'checked' : '' }}>
                </td>
                <td style="text-align:center;">
                  <label class="admin-checkbox admin-text-danger" style="margin:0; display:inline-flex;">
                    <input type="checkbox" name="delete_mortar_ids[]" value="{{ $m->id }}" class="admin-checkbox__input">
                    <span class="admin-checkbox__mark"></span>
                  </label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addMortarRow()">+ Add Mortar</button>
    </div>

    <div class="config-card">
      <h3>Grout Types</h3>
      <div class="table-container">
        <table class="admin-table" id="grout-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Bag (lbs)</th>
              <th>Density Factor</th>
              <th>Cost ($)</th>
              <th style="width:60px;">Sort</th>
              <th style="width:70px;">Active</th>
              <th style="width:50px; text-align:center;">Del</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($groutTypes as $index => $g)
              <tr>
                <td>
                  <input type="hidden" name="grouts[{{ $index }}][id]" value="{{ $g->id }}">
                  <input type="text" name="grouts[{{ $index }}][name]" class="admin-input" value="{{ $g->name }}" required>
                </td>
                <td><input type="number" step="0.01" name="grouts[{{ $index }}][bag_weight_lbs]" class="admin-input" value="{{ (float)$g->bag_weight_lbs }}" required></td>
                <td><input type="number" step="0.0001" name="grouts[{{ $index }}][density_factor]" class="admin-input" value="{{ (float)$g->density_factor }}" required></td>
                <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           name="grouts[{{ $index }}][cost_price]" class="admin-input cp-money-input"
                           value="{{ number_format((float) $g->cost_price, 4, '.', '') }}" required></td>
                <td><input type="number" name="grouts[{{ $index }}][sort_order]" class="admin-input" value="{{ $g->sort_order }}"></td>
                <td style="text-align:center;">
                  <input type="checkbox" name="grouts[{{ $index }}][is_active]" value="1" class="admin-checkbox__input--standalone" {{ $g->is_active ? 'checked' : '' }}>
                </td>
                <td style="text-align:center;">
                  <label class="admin-checkbox admin-text-danger" style="margin:0; display:inline-flex;">
                    <input type="checkbox" name="delete_grout_ids[]" value="{{ $g->id }}" class="admin-checkbox__input">
                    <span class="admin-checkbox__mark"></span>
                  </label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addGroutRow()">+ Add Grout</button>
    </div>

    <div class="config-card">
      <h3>Tile Products</h3>
      <div class="table-container">
        <table class="admin-table" id="product-table">
          <thead>
            <tr>
              <th>Name / Model</th>
              <th>Supplier URL</th>
              <th>Image</th>
              <th>Length (in)</th>
              <th>Width (in)</th>
              <th>Thickness (in)</th>
              <th>Cost / sqft ($)</th>
              <th style="width:60px;">Sort</th>
              <th style="width:70px;">Active</th>
              <th style="width:50px; text-align:center;">Del</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $index => $p)
              <tr>
                <td>
                  <input type="hidden" name="products[{{ $index }}][id]" value="{{ $p->id }}">
                  <input type="text" name="products[{{ $index }}][name]" class="admin-input" value="{{ $p->name }}" required>
                </td>
                <td><input type="text" name="products[{{ $index }}][url]" class="admin-input" value="{{ $p->url }}" placeholder="https://..." inputmode="url" autocomplete="url"></td>
                <td>
                  @if ($imageUrl = $p->publicImageUrl())
                    <div style="margin-bottom:4px;">
                      <a href="{{ $imageUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $imageUrl }}" alt="" style="max-height:40px; border-radius:4px;">
                      </a>
                    </div>
                  @elseif ($p->image_path)
                    <p class="admin-form-help" style="margin:0 0 4px;color:#b45309;">Image missing on server — re-upload.</p>
                  @endif
                  <input type="file" name="products[{{ $index }}][image]" class="admin-input" accept="image/*" style="font-size:12px;">
                </td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][length_inches]" class="admin-input" value="{{ (float)$p->length_inches }}" required></td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][width_inches]" class="admin-input" value="{{ (float)$p->width_inches }}" required></td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][thickness_inches]" class="admin-input" value="{{ (float)$p->thickness_inches }}" required></td>
                <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           name="products[{{ $index }}][cost_per_sqft]" class="admin-input cp-money-input"
                           value="{{ number_format((float) $p->cost_per_sqft, 4, '.', '') }}" required></td>
                <td><input type="number" name="products[{{ $index }}][sort_order]" class="admin-input" value="{{ $p->sort_order }}"></td>
                <td style="text-align:center;">
                  <input type="checkbox" name="products[{{ $index }}][is_active]" value="1" class="admin-checkbox__input--standalone" {{ $p->is_active ? 'checked' : '' }}>
                </td>
                <td style="text-align:center;">
                  <label class="admin-checkbox admin-text-danger" style="margin:0; display:inline-flex;">
                    <input type="checkbox" name="delete_product_ids[]" value="{{ $p->id }}" class="admin-checkbox__input">
                    <span class="admin-checkbox__mark"></span>
                  </label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addProductRow()">+ Add Tile Product</button>
    </div>

    <div class="config-card">
      <h3>Tile Edge</h3>
      <p class="admin-form-help" style="margin-top:-8px; margin-bottom:16px;">
        Sold in standard 8&nbsp;ft pieces. Quotation rounds linear feet up to whole pieces (e.g. 9&nbsp;LF → 2 pcs).
      </p>
      <div class="table-container">
        <table class="admin-table" id="edge-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>URL</th>
              <th>Thickness</th>
              <th>Color</th>
              <th>Unit price ($ / 8&nbsp;ft pc)</th>
              <th style="width:60px;">Sort</th>
              <th style="width:70px;">Active</th>
              <th style="width:50px; text-align:center;">Del</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($edges as $index => $e)
              <tr>
                <td>
                  <input type="hidden" name="edges[{{ $index }}][id]" value="{{ $e->id }}">
                  <input type="text" name="edges[{{ $index }}][name]" class="admin-input" value="{{ $e->name }}" required>
                </td>
                <td><input type="text" name="edges[{{ $index }}][url]" class="admin-input" value="{{ $e->url }}" placeholder="https://..." inputmode="url" autocomplete="url"></td>
                <td><input type="text" name="edges[{{ $index }}][thickness]" class="admin-input" value="{{ $e->thickness }}" placeholder="5/16"></td>
                <td><input type="text" name="edges[{{ $index }}][color]" class="admin-input" value="{{ $e->color }}" placeholder="White"></td>
                <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           name="edges[{{ $index }}][unit_price]" class="admin-input cp-money-input"
                           value="{{ number_format((float) $e->unit_price, 4, '.', '') }}" required></td>
                <td><input type="number" name="edges[{{ $index }}][sort_order]" class="admin-input" value="{{ $e->sort_order }}"></td>
                <td style="text-align:center;">
                  <input type="checkbox" name="edges[{{ $index }}][is_active]" value="1" class="admin-checkbox__input--standalone" {{ $e->is_active ? 'checked' : '' }}>
                </td>
                <td style="text-align:center;">
                  <label class="admin-checkbox admin-text-danger" style="margin:0; display:inline-flex;">
                    <input type="checkbox" name="delete_edge_ids[]" value="{{ $e->id }}" class="admin-checkbox__input">
                    <span class="admin-checkbox__mark"></span>
                  </label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addEdgeRow()">+ Add Tile Edge</button>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <a href="{{ $qpid ? route('admin.quotations.tile.create', ['project' => $qpid]) : route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost">Back</a>
      <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save><i class="fa fa-save"></i> Save Configuration</button>
    </div>
  </form>

  <script>
    let mIdx = 0;
    function addMortarRow() {
      let tbody = document.querySelector('#mortar-table tbody');
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" name="new_mortars[${mIdx}][name]" class="admin-input" placeholder="Name"></td>
        <td><input type="number" step="0.01" name="new_mortars[${mIdx}][bag_weight_lbs]" class="admin-input" value="50"></td>
        <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                   name="new_mortars[${mIdx}][cost_price]" class="admin-input cp-money-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      if (window.cpMoneyMask) {
        window.cpMoneyMask.wire(tr.querySelectorAll('.cp-money-input'));
      }
      mIdx++;
    }

    let gIdx = 0;
    function addGroutRow() {
      let tbody = document.querySelector('#grout-table tbody');
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" name="new_grouts[${gIdx}][name]" class="admin-input" placeholder="Name"></td>
        <td><input type="number" step="0.01" name="new_grouts[${gIdx}][bag_weight_lbs]" class="admin-input" value="10"></td>
        <td><input type="number" step="0.0001" name="new_grouts[${gIdx}][density_factor]" class="admin-input" value="1.6"></td>
        <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                   name="new_grouts[${gIdx}][cost_price]" class="admin-input cp-money-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      if (window.cpMoneyMask) {
        window.cpMoneyMask.wire(tr.querySelectorAll('.cp-money-input'));
      }
      gIdx++;
    }

    let pIdx = 0;
    function addProductRow() {
      let tbody = document.querySelector('#product-table tbody');
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" name="new_products[${pIdx}][name]" class="admin-input" placeholder="Name" required></td>
        <td><input type="text" name="new_products[${pIdx}][url]" class="admin-input" placeholder="https://..." inputmode="url" autocomplete="url"></td>
        <td><input type="file" name="new_products[${pIdx}][image]" class="admin-input" accept="image/*" style="font-size:12px;"></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][length_inches]" class="admin-input" value="12" required></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][width_inches]" class="admin-input" value="12" required></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][thickness_inches]" class="admin-input" value="0.25" required></td>
        <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                   name="new_products[${pIdx}][cost_per_sqft]" class="admin-input cp-money-input" value="0" required></td>
        <td></td>
        <td style="text-align:center;"><span class="admin-form-help" title="New tiles are active by default">Active</span></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      if (window.cpMoneyMask) {
        window.cpMoneyMask.wire(tr.querySelectorAll('.cp-money-input'));
      }
      pIdx++;
      var nameInput = tr.querySelector('input[name*="[name]"]');
      if (nameInput) nameInput.focus();
    }

    let eIdx = 0;
    function addEdgeRow() {
      let tbody = document.querySelector('#edge-table tbody');
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" name="new_edges[${eIdx}][name]" class="admin-input" placeholder="Name"></td>
        <td><input type="text" name="new_edges[${eIdx}][url]" class="admin-input" placeholder="https://..." inputmode="url" autocomplete="url"></td>
        <td><input type="text" name="new_edges[${eIdx}][thickness]" class="admin-input" placeholder="5/16"></td>
        <td><input type="text" name="new_edges[${eIdx}][color]" class="admin-input" placeholder="White"></td>
        <td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                   name="new_edges[${eIdx}][unit_price]" class="admin-input cp-money-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      if (window.cpMoneyMask) {
        window.cpMoneyMask.wire(tr.querySelectorAll('.cp-money-input'));
      }
      eIdx++;
      var nameInput = tr.querySelector('input[name*="[name]"]');
      if (nameInput) nameInput.focus();
    }
  </script>
@endsection
