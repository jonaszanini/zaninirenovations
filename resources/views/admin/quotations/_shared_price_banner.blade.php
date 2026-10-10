{{--
  Read-only list of library items this quote type uses.

  @param string $consumer  flooring|trimming|basement|painting|shed|tile|millwork
--}}
@php
  $consumer = $consumer ?? '';
  $keys = \App\Services\SharedWorkCatalog::worksForConsumer($consumer);
@endphp
@if ($keys !== [])
  <div class="sp-shared-banner">
    <div class="sp-shared-banner__head">
      <h3 class="sp-heading">
        Shared
        <x-admin.info-tip label="Shared">These labor and material rates are shared across quotation types. Changing them here is not possible — open Shared prices.</x-admin.info-tip>
      </h3>
      <a href="{{ route('admin.quotations.shared-prices.edit') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Edit in Shared prices
      </a>
    </div>
    <ul class="sp-shared-list">
      @foreach ($keys as $key)
        @php
          $work = \App\Services\SharedWorkCatalog::work($key);
          $def = \App\Services\SharedWorkCatalog::definition($key);
          $label = $work->label ?? ($def['label'] ?? $key);
          $isCustom = $work && $work->isCustom();
        @endphp
        <li>
          <span class="sp-shared-item">
            <a href="{{ \App\Services\SharedWorkCatalog::sharedPricesUrl($key) }}">{{ $label }}</a>
            @if ($isCustom)
              <span class="sp-ref-badge" title="Reference only — quote calculators do not use this item.">Reference</span>
            @endif
          </span>
          <span class="sp-shared-rate">{{ \App\Services\SharedWorkCatalog::rateLine($key) }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif
