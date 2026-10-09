{{--
  Collapsible Filters card for admin list pages.

  @component('admin.partials.filters_accordion', [
    'id' => 'admin-filters-client-projects',
    'storageKey' => 'admin-filters:client-projects',
    'activeCount' => $activeFilterCount,
    'hint' => 'Optional helper text shown inside the open panel.',
  ])
    <form method="get" class="cp-filters">…</form>
  @endcomponent

  Collapsed by default. Opens when $activeCount > 0 unless the user
  explicitly closed it (localStorage; see public/js/admin-filters-accordion.js).
--}}
@php
  $id = $id ?? 'admin-filters';
  $storageKey = $storageKey ?? ('admin-filters:'.$id);
  $activeCount = (int) ($activeCount ?? 0);
  $hint = $hint ?? null;
  $panelId = $id.'-panel';
@endphp
<details
  class="admin-filters-accordion admin-card"
  id="{{ $id }}"
  data-admin-filters-accordion
  data-storage-key="{{ $storageKey }}"
  data-active-count="{{ $activeCount }}"
  @if ($activeCount > 0) open @endif
>
  <summary
    class="admin-filters-accordion__summary admin-chevron-summary"
    aria-controls="{{ $panelId }}"
  >
    <span class="admin-filters-accordion__icon" aria-hidden="true"><i class="fa fa-filter"></i></span>
    <span class="admin-filters-accordion__title">Filters</span>
    @if ($activeCount > 0)
      <span class="admin-filters-accordion__badge">{{ $activeCount }} active</span>
    @endif
  </summary>
  <div class="admin-filters-accordion__panel" id="{{ $panelId }}">
    @if (filled($hint))
      <p class="admin-filters-accordion__hint">{{ $hint }}</p>
    @endif
    {{ $slot }}
  </div>
</details>
