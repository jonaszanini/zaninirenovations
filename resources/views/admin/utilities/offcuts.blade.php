@extends('admin.layouts.app')

@section('heading', $editing ? 'Edit offcut' : 'Offcut inventory')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $formOffcut = $editing;
    $formAction = $formOffcut
      ? route('admin.utilities.offcuts.update', $formOffcut)
      : route('admin.utilities.offcuts.store');
  @endphp

  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Leftover lumber and sheet goods from cut lists. Millwork leftovers show here as sheets and in the lumber calculator as usable lengths.
    </p>
    <div class="admin-toolbar-actions">
      <a href="{{ route('admin.utilities.lumber-board') }}" class="admin-btn admin-btn--ghost">
        <i class="fa fa-th-large"></i> Lumber calculator
      </a>
      <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
        <i class="fa fa-arrow-left"></i> All utilities
      </a>
    </div>
  </div>

  <div class="util-oc-grid">
    <section class="admin-card util-oc-form-card">
      <h3 class="util-lb-card-title">{{ $formOffcut ? 'Edit offcut' : 'Add offcut' }}</h3>
      <form method="post" action="{{ $formAction }}" class="util-oc-form">
        @csrf
        @if ($formOffcut)
          @method('PUT')
        @endif

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="oc-material">Material key</label>
            <input class="admin-input" type="text" id="oc-material" name="material_key" maxlength="80" required
                   value="{{ old('material_key', $formOffcut->material_key ?? '') }}"
                   placeholder="2x4, plywood, osb">
            @error('material_key') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="oc-qty">Qty</label>
            <input class="admin-input" type="number" id="oc-qty" name="qty" min="1" step="1" required
                   value="{{ old('qty', $formOffcut->qty ?? 1) }}">
            @error('qty') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="admin-form-grid-2 util-oc-dims">
          <div class="admin-form-group">
            <label for="oc-thick">Thickness (in)</label>
            <input class="admin-input" type="number" id="oc-thick" name="thickness_in" min="0" step="any" inputmode="decimal"
                   value="{{ old('thickness_in', $formOffcut->thickness_in ?? '') }}" placeholder="Optional">
          </div>
          <div class="admin-form-group">
            <label for="oc-width">Sheet width (in)</label>
            <input class="admin-input" type="number" id="oc-width" name="width_in" min="0" step="any" inputmode="decimal"
                   value="{{ old('width_in', $formOffcut->width_in ?? '') }}" placeholder="Sheets only">
          </div>
          <div class="admin-form-group">
            <label for="oc-length">Length (in) <span class="cp-required">*</span></label>
            <input class="admin-input" type="number" id="oc-length" name="length_in" min="0.01" step="any" inputmode="decimal" required
                   value="{{ old('length_in', $formOffcut->length_in ?? '') }}">
            @error('length_in') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="oc-location">Location</label>
            <input class="admin-input" type="text" id="oc-location" name="location" maxlength="120"
                   value="{{ old('location', $formOffcut->location ?? '') }}" placeholder="Shop rack…">
          </div>
        </div>

        <div class="admin-form-group">
          <label for="oc-notes">Notes</label>
          <input class="admin-input" type="text" id="oc-notes" name="notes" maxlength="255"
                 value="{{ old('notes', $formOffcut->notes ?? '') }}">
        </div>

        <div class="admin-form-group">
          <label for="oc-project">From project <span class="util-lb-optional">(optional)</span></label>
          <select class="admin-input" id="oc-project" name="created_from_project_id">
            <option value="">— None —</option>
            @foreach ($clientProjects as $cp)
              <option value="{{ $cp->id }}" @selected((int) old('created_from_project_id', $formOffcut->created_from_project_id ?? 0) === (int) $cp->id)>
                {{ $cp->linkSelectLabel() }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="util-oc-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-save"></i> {{ $formOffcut ? 'Update' : 'Add to inventory' }}
          </button>
          @if ($formOffcut)
            <a href="{{ route('admin.utilities.offcuts.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
          @endif
        </div>
      </form>
    </section>

    <section class="admin-card util-oc-list-card">
      <h3 class="util-lb-card-title">Inventory</h3>
      <form method="get" action="{{ route('admin.utilities.offcuts.index') }}" class="cp-filters cp-filters--3col util-oc-filters">
        <div class="cp-filter">
          <label for="oc-filter-mat">Material</label>
          <input class="admin-input" type="search" id="oc-filter-mat" name="material" value="{{ $material }}" placeholder="Filter key…">
        </div>
        <div class="cp-filter">
          <label for="oc-filter-status">Status</label>
          <select class="admin-input" id="oc-filter-status" name="status">
            <option value="available" @selected($status === 'available')>Available</option>
            <option value="used" @selected($status === 'used')>Used</option>
            <option value="all" @selected($status === 'all')>All</option>
          </select>
        </div>
        <div class="cp-filter cp-filter--actions">
          <button type="submit" class="admin-btn admin-btn--primary">Apply</button>
          <a href="{{ route('admin.utilities.offcuts.index') }}" class="admin-btn admin-btn--ghost">Reset</a>
        </div>
      </form>

      @if ($offcuts->isEmpty())
        <p class="util-lb-saved-empty">No offcuts match these filters.</p>
      @else
        <div class="util-oc-cards">
          @foreach ($offcuts as $row)
            @php
              $catalog = $prices[$row->material_key] ?? null;
            @endphp
            <article class="util-oc-card{{ $row->isAvailable() ? '' : ' is-used' }}">
              <div class="util-oc-card__top">
                <div>
                  <strong>{{ $row->displayName() }}</strong>
                  @if ($row->isSheet())
                    <span class="util-oc-pill">Sheet</span>
                  @else
                    <span class="util-oc-pill util-oc-pill--linear">Linear</span>
                  @endif
                  @unless ($row->isAvailable())
                    <span class="util-oc-pill util-oc-pill--used">Used</span>
                  @endunless
                  <div class="util-oc-card__meta">
                    {{ $row->sizeLabel() }}
                    @if ($row->thickness_in)
                      <span class="util-oc-muted">× {{ rtrim(rtrim(number_format((float) $row->thickness_in, 3, '.', ''), '0'), '.') }} thk</span>
                    @endif
                    · qty {{ $row->qty }}
                    @if ($catalog)
                      · ${{ number_format($catalog, 2) }}
                    @endif
                  </div>
                  <div class="util-oc-muted">
                    {{ $row->location ?: '—' }}
                    @if ($row->notes)
                      · {{ $row->notes }}
                    @endif
                  </div>
                </div>
              </div>
              <div class="util-oc-actions">
                <a href="{{ route('admin.utilities.offcuts.edit', $row) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                @if ($row->isAvailable())
                  <form method="post" action="{{ route('admin.utilities.offcuts.mark-used', $row) }}">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Mark used</button>
                  </form>
                @endif
                <form method="post" action="{{ route('admin.utilities.offcuts.destroy', $row) }}" onsubmit="return confirm('Delete this offcut?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </section>
  </div>
@endsection
