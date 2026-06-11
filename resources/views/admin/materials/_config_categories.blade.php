<p class="admin-form-help mat-categories__lede">
  Categories group materials in the catalog and on the materials list filter.
  Each material links to one category row.
</p>

<form method="post" action="{{ route('admin.materials.config.categories.store') }}" class="mat-categories__add">
  @csrf
  <input type="hidden" name="_config_section" value="categories">
  <div class="mat-categories__add-row">
    <div class="admin-form-group mat-categories__add-name">
      <label for="mat-category-add-name">New category name</label>
      <input type="text" id="mat-category-add-name" name="name" value="{{ old('name') }}" maxlength="120" required
             class="admin-input @error('name') is-invalid @enderror" placeholder="e.g. Roofing">
      @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>
    <div class="admin-form-group mat-categories__add-sort">
      <label for="mat-category-add-sort">Sort order</label>
      <input type="number" id="mat-category-add-sort" name="sort_order" value="{{ old('sort_order', 0) }}"
             min="0" max="32767" class="admin-input">
    </div>
    <div class="mat-categories__add-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> Add category
      </button>
    </div>
  </div>
</form>

@if ($materialCategories->isEmpty())
  <p class="admin-form-help">No categories yet.</p>
@else
  <div class="admin-table-scroll mat-categories__table-wrap">
    <table class="admin-table mat-categories__table">
      <thead>
        <tr>
          <th style="width:70px;">Order</th>
          <th>Name</th>
          <th style="width:90px;">Materials</th>
          <th style="width:200px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($materialCategories as $materialCategory)
          <tr>
            <td colspan="4" class="mat-categories__edit-row">
              <form method="post" action="{{ route('admin.materials.config.categories.update', $materialCategory) }}" class="mat-categories__edit-form">
                @csrf
                @method('PUT')
                <div class="mat-categories__edit-grid">
                  <div class="admin-form-group">
                    <label class="admin-sr-only" for="mat-category-sort-{{ $materialCategory->id }}">Sort order</label>
                    <input class="admin-input" type="number" id="mat-category-sort-{{ $materialCategory->id }}" name="sort_order"
                           value="{{ old('sort_order', $materialCategory->sort_order) }}" min="0" max="32767" class="admin-input">
                  </div>
                  <div class="admin-form-group">
                    <label class="admin-sr-only" for="mat-category-name-{{ $materialCategory->id }}">Name</label>
                    <input class="admin-input" type="text" id="mat-category-name-{{ $materialCategory->id }}" name="name"
                           value="{{ old('name', $materialCategory->name) }}" maxlength="120" required class="admin-input">
                  </div>
                  <div class="mat-categories__edit-meta">
                    <span class="mat-categories__count">{{ number_format($materialCategory->materials_count) }}</span>
                  </div>
                  <div class="mat-categories__edit-actions">
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Save</button>
                  </div>
                </div>
              </form>
              <form method="post" action="{{ route('admin.materials.config.categories.destroy', $materialCategory) }}"
                    class="mat-categories__delete-form"
                    onsubmit="return confirm('Delete category “{{ $materialCategory->name }}”?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"
                        @disabled($materialCategory->materials_count> 0) title="{{ $materialCategory->materials_count> 0 ? 'Reassign materials first' : 'Delete category' }}">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
