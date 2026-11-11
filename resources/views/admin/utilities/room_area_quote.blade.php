@extends('admin.layouts.app')

@php
  $layoutId = $layoutId ?? '';
  $option = $option ?? [];
  $trades = $trades ?? [];
  $wizardUrls = $wizardUrls ?? [];
  $existingQuotes = $existingQuotes ?? collect();
  $clientProject = $clientProject ?? null;
  $defaultClientName = $defaultClientName ?? '';
  $defaultTitle = $defaultTitle ?? '';
  $fmt = static function (float $n): string {
      return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
  };
@endphp

@section('heading', 'Create quote')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar util-rq-toolbar">
    <p class="admin-toolbar-lede">
      {{ $option['name'] ?? 'Room Area' }}
      @if (!empty($option['meta']))
        <span class="util-rq-toolbar-meta">{{ $option['meta'] }}</span>
      @endif
    </p>
    <a href="{{ $backUrl ?? route('admin.utilities.room-area', $roomAreaProject ? ['ra_project' => $roomAreaProject->id] : []) }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> Room Area
    </a>
  </div>

  <div class="util-rq" data-room-area-quote
       data-metrics='@json($option)'
       data-trades='@json($trades)'>
    <script type="application/json" id="rq-catalog-options">@json($catalogPickerOptions)</script>

    <section class="admin-card util-rq-card">
      <h3 class="util-rq-card-title">Start a calculator</h3>
      <p class="util-rq-hint">Opens the existing wizard with measurements filled from this layout.</p>
      <div class="util-rq-wizards">
        <a class="admin-btn admin-btn--outline" href="{{ $wizardUrls['flooring'] }}">Flooring</a>
        <a class="admin-btn admin-btn--outline" href="{{ $wizardUrls['trimming'] }}">Trim</a>
        <a class="admin-btn admin-btn--outline" href="{{ $wizardUrls['tile'] }}">Tile</a>
        <a class="admin-btn admin-btn--outline" href="{{ $wizardUrls['millwork'] }}">Millwork</a>
        <a class="admin-btn admin-btn--outline" href="{{ $wizardUrls['stairs'] }}">Stairs</a>
      </div>
      <dl class="util-rq-metrics">
        <div><dt>Floor</dt><dd>{{ $fmt((float) ($option['area_sqft'] ?? 0)) }} sq ft</dd></div>
        @if ((float) ($option['wall_thickness_in'] ?? 0) > 0)
          <div><dt>Footprint</dt><dd>{{ $fmt((float) ($option['outside_area_sqft'] ?? $option['area_sqft'] ?? 0)) }} sq ft</dd></div>
        @endif
        <div><dt>Net wall area</dt><dd>{{ $fmt((float) ($option['net_wall_area_sqft'] ?? $option['wall_area_sqft'] ?? 0)) }} sq ft</dd></div>
        <div><dt>Ceiling</dt><dd>{{ $fmt((float) ($option['ceiling_area_sqft'] ?? $option['area_sqft'] ?? 0)) }} sq ft</dd></div>
        @if ((int) ($option['flooring_boxes'] ?? 0) > 0)
          <div><dt>Flooring</dt><dd>{{ $fmt((float) ($option['flooring_with_waste_sqft'] ?? 0)) }} sq ft with waste · {{ (int) $option['flooring_boxes'] }} boxes</dd></div>
        @endif
        @if ((float) ($option['casing_lf'] ?? 0) > 0)
          <div><dt>Casing</dt><dd>{{ $fmt((float) $option['casing_lf']) }} lf</dd></div>
        @endif
        <div><dt>Baseboard</dt><dd>{{ $fmt((float) (($option['finish_baseboard_lf'] ?? 0) > 0 ? $option['finish_baseboard_lf'] : ($option['baseboard_lf'] ?? 0))) }} lf</dd></div>
        @if ((float) ($option['framing_lf'] ?? 0) > 0 && (float) ($option['shared_wall_lf'] ?? 0) > 0)
          <div><dt>Framing</dt><dd>{{ $fmt((float) $option['framing_lf']) }} lf</dd></div>
        @endif
        @if ((float) ($option['cabinet_base_lf'] ?? 0) + (float) ($option['cabinet_upper_lf'] ?? 0) > 0)
          <div><dt>Cabinets</dt><dd>{{ $fmt((float) ($option['cabinet_base_lf'] ?? 0)) }} lf base · {{ $fmt((float) ($option['cabinet_upper_lf'] ?? 0)) }} lf upper</dd></div>
        @endif
        @if ((int) ($option['stairs_treads'] ?? 0) > 0)
          <div><dt>Stairs</dt><dd>{{ (int) $option['stairs_treads'] }} treads</dd></div>
        @endif
        <div><dt>Height</dt><dd>{{ $fmt((float) ($option['wall_height_ft'] ?? 8)) }} ft</dd></div>
      </dl>
      @if ($clientProject)
        <p class="util-rq-linked">
          Linked to
          <a href="{{ route('admin.client-projects.show', $clientProject) }}#quotation">{{ $clientProject->name }}</a>
        </p>
      @endif
    </section>

    <section class="admin-card util-rq-card">
      <h3 class="util-rq-card-title">Add priced lines</h3>
      <p class="util-rq-hint">Quantities come from the saved drawing. Prices default from the catalog and stay editable.</p>

      <form method="post" action="{{ route('admin.utilities.room-area.quote.store') }}" id="rq-form" class="util-rq-form">
        @csrf
        <input type="hidden" name="layout" value="{{ $layoutId }}">

        <div class="util-rq-trades">
          @foreach ($trades as $key => $trade)
            @php
              $base = (float) ($option[$trade['metric']] ?? 0);
              if ($key === 'baseboard') {
                  $finishBb = (float) ($option['finish_baseboard_lf'] ?? 0);
                  if ($finishBb > 0) {
                      $base = $finishBb;
                  } elseif ($base <= 0) {
                      $base = (float) ($option['perimeter_lf'] ?? 0);
                  }
              }
              $waste = old('waste.'.$key, $trade['default_waste']);
            @endphp
            <div class="util-rq-trade{{ in_array($key, old('trades', []), true) ? ' is-on' : '' }}" data-rq-trade="{{ $key }}" data-rq-base="{{ $base }}" data-rq-unit="{{ $trade['unit'] }}">
              <label class="admin-checkbox util-rq-trade__check">
                <input type="checkbox" class="admin-checkbox__input" name="trades[]" value="{{ $key }}"
                       data-rq-enable @checked(in_array($key, old('trades', []), true))>
                <span class="admin-checkbox__mark"></span>
                <span class="admin-checkbox__text">{{ $trade['label'] }}</span>
              </label>
              <div class="util-rq-trade__waste">
                <label class="admin-sr-only" for="rq-waste-{{ $key }}">{{ $trade['label'] }} waste percent</label>
                <input class="admin-input util-rq-narrow" type="text" inputmode="decimal" id="rq-waste-{{ $key }}"
                       name="waste[{{ $key }}]" value="{{ $waste }}" data-rq-waste maxlength="6" title="Waste %">
                <span>%</span>
              </div>
              <div class="util-rq-trade__material">
                @include('admin.partials.search_select', [
                  'id' => 'rq-mat-'.$key,
                  'name' => 'catalog_material_id['.$key.']',
                  'value' => old('catalog_material_id.'.$key),
                  'hideLabel' => true,
                  'placeholder' => 'Material…',
                  'ariaLabel' => $trade['label'].' material',
                  'optionsSource' => 'rq-catalog-options',
                  'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                  'metaKeys' => ['sku', 'price_label'],
                  'emptyMessage' => 'No matching materials',
                  'wrapperClass' => 'util-rq-select',
                  'maxOptions' => 40,
                  'listParent' => 'body',
                  'attrs' => ['data-rq-catalog' => $key],
                ])
              </div>
              <div class="util-rq-trade__price">
                <label class="admin-sr-only" for="rq-price-{{ $key }}">{{ $trade['label'] }} unit price</label>
                <input class="admin-input cp-money-input util-rq-money" type="text" inputmode="decimal" autocomplete="off"
                       id="rq-price-{{ $key }}" name="unit_price[{{ $key }}]"
                       value="{{ old('unit_price.'.$key) }}" data-rq-price data-cp-money-entry="direct">
              </div>
            </div>
          @endforeach
        </div>
        @error('trades')
          <p class="admin-form-error">{{ $message }}</p>
        @enderror

        <div class="util-rq-target">
          <div class="admin-form-group">
            <label for="rq-quotation">Quotation</label>
            <select class="admin-input" id="rq-quotation" name="quotation_id">
              <option value="">New quotation</option>
              @foreach ($existingQuotes as $quote)
                <option value="{{ $quote->id }}" @selected((string) old('quotation_id') === (string) $quote->id)>
                  {{ $quote->quote_number }}{{ $quote->display_title ? ' — '.$quote->display_title : '' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group" data-rq-new-fields>
            <label for="rq-client">Client</label>
            <input class="admin-input" type="text" id="rq-client" name="client_name" maxlength="160"
                   value="{{ old('client_name', $defaultClientName) }}">
            @error('client_name')
              <p class="admin-form-error">{{ $message }}</p>
            @enderror
          </div>
          <div class="admin-form-group" data-rq-new-fields>
            <label for="rq-title">Title</label>
            <input class="admin-input" type="text" id="rq-title" name="display_title" maxlength="255"
                   value="{{ old('display_title', $defaultTitle) }}">
          </div>
        </div>

        <div class="util-rq-preview" data-rq-preview hidden>
          <h4 class="util-rq-preview__title">Line preview</h4>
          <ul class="util-rq-preview__list" data-rq-preview-body></ul>
          <p class="util-rq-preview__total">Pre-tax <strong data-rq-preview-total>CA$0.00</strong></p>
        </div>

        <div class="util-rq-actions">
          <button type="submit" class="admin-btn admin-btn--primary" data-rq-submit disabled>
            Insert lines
          </button>
        </div>
      </form>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-utilities-room-area-quote.js') }}?v={{ @filemtime(public_path('js/admin-utilities-room-area-quote.js')) ?: time() }}"></script>
@endpush
