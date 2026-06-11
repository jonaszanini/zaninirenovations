<p class="admin-form-help mat-vendors__lede">
  Vendors are the suppliers you buy materials from. Each material links to one vendor row.
  Home Depot SKU bulk import always uses the vendor marked <strong>Default for HD import</strong>.
</p>

<form method="post" action="{{ route('admin.materials.config.vendors.store') }}" class="mat-vendors__add">
  @csrf
  <div class="admin-form-grid-2 mat-vendors__add-grid">
    <div class="admin-form-group">
      <label for="mat-vendor-add-name">New vendor name</label>
      <input type="text" id="mat-vendor-add-name" name="name" value="{{ old('name') }}" maxlength="120" required
             class="admin-input @error('name') is-invalid @enderror" placeholder="e.g. Rona">
      @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>
    <div class="admin-form-group">
      <label for="mat-vendor-add-sort">Sort order</label>
      <div class="mat-vendors__add-actions">
        <input type="number" id="mat-vendor-add-sort" name="sort_order" value="{{ old('sort_order', 0) }}"
               min="0" max="32767" class="admin-input">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
          <i class="fa fa-plus"></i> Add vendor
        </button>
      </div>
    </div>
  </div>
</form>

@if ($vendors->isEmpty())
  <p class="admin-form-help">No vendors yet.</p>
@else
  <div class="admin-table-scroll mat-vendors__table-wrap">
    <table class="admin-table mat-vendors__table">
      <thead>
        <tr>
          <th style="width:70px;">Order</th>
          <th>Name</th>
          <th>Slug</th>
          <th style="width:90px;">Materials</th>
          <th style="width:140px;">HD import</th>
          <th style="width:200px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($vendors as $vendor)
          <tr>
            <td colspan="6" class="mat-vendors__edit-row">
              <form method="post" action="{{ route('admin.materials.config.vendors.update', $vendor) }}" class="mat-vendors__edit-form">
                @csrf
                @method('PUT')
                <div class="mat-vendors__edit-grid">
                  <div class="admin-form-group">
                    <label class="admin-sr-only" for="mat-vendor-sort-{{ $vendor->id }}">Sort order</label>
                    <input type="number" id="mat-vendor-sort-{{ $vendor->id }}" name="sort_order"
                           value="{{ old('sort_order', $vendor->sort_order) }}" min="0" max="32767" class="admin-input">
                  </div>
                  <div class="admin-form-group">
                    <label class="admin-sr-only" for="mat-vendor-name-{{ $vendor->id }}">Name</label>
                    <input type="text" id="mat-vendor-name-{{ $vendor->id }}" name="name"
                           value="{{ old('name', $vendor->name) }}" maxlength="120" required class="admin-input">
                  </div>
                  <div class="admin-form-group">
                    <label class="admin-sr-only" for="mat-vendor-slug-{{ $vendor->id }}">Slug</label>
                    <input type="text" id="mat-vendor-slug-{{ $vendor->id }}" name="slug"
                           value="{{ old('slug', $vendor->slug) }}" maxlength="80" required
                           pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="admin-input admin-code">
                  </div>
                  <div class="mat-vendors__edit-meta">
                    <span class="mat-vendors__count">{{ number_format($vendor->materials_count) }}</span>
                  </div>
                  <div class="mat-vendors__edit-default">
                    @include('admin.partials.checkbox', [
                      'name' => 'is_default_for_homedepot',
                      'id' => 'mat-vendor-hd-'.$vendor->id,
                      'checked' => old('is_default_for_homedepot', $vendor->is_default_for_homedepot),
                      'label' => 'Default',
                    ])
                  </div>
                  <div class="mat-vendors__edit-actions">
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Save</button>
                  </div>
                </div>
              </form>
              @if ($vendor->slug !== \App\Models\MaterialVendor::SLUG_HOME_DEPOT)
                <form method="post" action="{{ route('admin.materials.config.vendors.destroy', $vendor) }}"
                      class="mat-vendors__delete-form"
                      onsubmit="return confirm('Delete vendor “{{ $vendor->name }}”?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                          @disabled($vendor->materials_count > 0) title="{{ $vendor->materials_count > 0 ? 'Reassign materials first' : 'Delete vendor' }}">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
