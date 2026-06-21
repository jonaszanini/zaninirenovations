@extends('admin.layouts.app')

@section('heading', 'Tile Configuration')

@push('head')
  <style>
    .config-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px; }
    .config-card h3 { margin: 0 0 16px; color: #024768; }
    .table-container { overflow-x: auto; margin-bottom: 16px; }
    table.admin-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    table.admin-table th, table.admin-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
    table.admin-table th { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600; }
    table.admin-table .admin-input { min-width: 80px; }
  </style>
@endpush

@section('content')
  @if (session('success'))
    <div class="admin-banner admin-banner--success" style="margin-bottom:16px;">
      <i class="fa fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="admin-banner admin-banner--danger" style="margin-bottom:16px;">
      <i class="fa fa-exclamation-triangle"></i> Please check the form for errors.
    </div>
  @endif

  <form action="{{ route('admin.quotations.tile.price-configuration.update') }}{{ $qpid ? '?project='.$qpid : '' }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

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
              <th style="width:50px;"></th>
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
                <td><input type="number" step="0.0001" name="mortars[{{ $index }}][cost_price]" class="admin-input" value="{{ (float)$m->cost_price }}" required></td>
                <td><input type="number" name="mortars[{{ $index }}][sort_order]" class="admin-input" value="{{ $m->sort_order }}"></td>
                <td>
                  <label class="admin-form-check" style="margin:0;"><input type="checkbox" name="mortars[{{ $index }}][is_active]" value="1" {{ $m->is_active ? 'checked' : '' }}></label>
                </td>
                <td>
                  <label class="admin-text-danger" style="cursor:pointer; font-size:12px;">
                    <input type="checkbox" name="delete_mortar_ids[]" value="{{ $m->id }}"> Del
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
              <th style="width:50px;"></th>
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
                <td><input type="number" step="0.0001" name="grouts[{{ $index }}][cost_price]" class="admin-input" value="{{ (float)$g->cost_price }}" required></td>
                <td><input type="number" name="grouts[{{ $index }}][sort_order]" class="admin-input" value="{{ $g->sort_order }}"></td>
                <td>
                  <label class="admin-form-check" style="margin:0;"><input type="checkbox" name="grouts[{{ $index }}][is_active]" value="1" {{ $g->is_active ? 'checked' : '' }}></label>
                </td>
                <td>
                  <label class="admin-text-danger" style="cursor:pointer; font-size:12px;">
                    <input type="checkbox" name="delete_grout_ids[]" value="{{ $g->id }}"> Del
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
              <th style="width:50px;"></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $index => $p)
              <tr>
                <td>
                  <input type="hidden" name="products[{{ $index }}][id]" value="{{ $p->id }}">
                  <input type="text" name="products[{{ $index }}][name]" class="admin-input" value="{{ $p->name }}" required>
                </td>
                <td><input type="url" name="products[{{ $index }}][url]" class="admin-input" value="{{ $p->url }}" placeholder="https://..."></td>
                <td>
                  @if($p->image_path)
                    <div style="margin-bottom:4px;">
                      <a href="{{ Storage::url($p->image_path) }}" target="_blank">
                        <img src="{{ Storage::url($p->image_path) }}" style="max-height:40px; border-radius:4px;">
                      </a>
                    </div>
                  @endif
                  <input type="file" name="products[{{ $index }}][image]" class="admin-input" accept="image/*" style="font-size:12px;">
                </td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][length_inches]" class="admin-input" value="{{ (float)$p->length_inches }}" required></td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][width_inches]" class="admin-input" value="{{ (float)$p->width_inches }}" required></td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][thickness_inches]" class="admin-input" value="{{ (float)$p->thickness_inches }}" required></td>
                <td><input type="number" step="0.0001" name="products[{{ $index }}][cost_per_sqft]" class="admin-input" value="{{ (float)$p->cost_per_sqft }}" required></td>
                <td><input type="number" name="products[{{ $index }}][sort_order]" class="admin-input" value="{{ $p->sort_order }}"></td>
                <td>
                  <label class="admin-form-check" style="margin:0;"><input type="checkbox" name="products[{{ $index }}][is_active]" value="1" {{ $p->is_active ? 'checked' : '' }}></label>
                </td>
                <td>
                  <label class="admin-text-danger" style="cursor:pointer; font-size:12px;">
                    <input type="checkbox" name="delete_product_ids[]" value="{{ $p->id }}"> Del
                  </label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="addProductRow()">+ Add Tile Product</button>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <a href="{{ $qpid ? route('admin.quotations.tile.create', ['project' => $qpid]) : route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost">Back</a>
      <button type="submit" class="admin-btn admin-btn--primary"><i class="fa fa-save"></i> Save Configuration</button>
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
        <td><input type="number" step="0.0001" name="new_mortars[${mIdx}][cost_price]" class="admin-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
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
        <td><input type="number" step="0.0001" name="new_grouts[${gIdx}][cost_price]" class="admin-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      gIdx++;
    }

    let pIdx = 0;
    function addProductRow() {
      let tbody = document.querySelector('#product-table tbody');
      let tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" name="new_products[${pIdx}][name]" class="admin-input" placeholder="Name"></td>
        <td><input type="url" name="new_products[${pIdx}][url]" class="admin-input" placeholder="https://..."></td>
        <td><input type="file" name="new_products[${pIdx}][image]" class="admin-input" accept="image/*" style="font-size:12px;"></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][length_inches]" class="admin-input" value="12"></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][width_inches]" class="admin-input" value="12"></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][thickness_inches]" class="admin-input" value="0.25"></td>
        <td><input type="number" step="0.0001" name="new_products[${pIdx}][cost_per_sqft]" class="admin-input" value="0"></td>
        <td></td><td></td>
        <td><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" onclick="this.closest('tr').remove()" style="padding:4px 8px; color:#ef4444;"><i class="fa fa-times"></i></button></td>
      `;
      tbody.appendChild(tr);
      pIdx++;
    }
  </script>
@endsection
