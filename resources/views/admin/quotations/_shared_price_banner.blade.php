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
      <h3 class="sp-heading">Shared</h3>
      <a href="{{ route('admin.quotations.shared-prices.edit') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Edit in Shared prices
      </a>
    </div>
    <p class="admin-form-help">These labor and material rates are shared across quotation types. Changing them here is not possible — open Shared prices.</p>
    <ul class="sp-shared-list">
      @foreach ($keys as $key)
        @php
          $work = \App\Services\SharedWorkCatalog::work($key);
          $def = \App\Services\SharedWorkCatalog::definition($key);
          $label = $work->label ?? ($def['label'] ?? $key);
        @endphp
        <li>
          <a href="{{ \App\Services\SharedWorkCatalog::sharedPricesUrl($key) }}">{{ $label }}</a>
          <span>{{ \App\Services\SharedWorkCatalog::rateLine($key) }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif
