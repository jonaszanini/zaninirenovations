@props(['material' => null, 'at' => null])

@php
    $syncedAt = $at ?? ($material?->last_price_synced_at);
    $meta = \App\Support\MaterialPriceSyncedAtDisplay::relativeLabel($syncedAt);
    $stale = \App\Support\MaterialPriceSyncedAtDisplay::isStale($syncedAt);
    $tooltip = \App\Support\MaterialPriceSyncedAtDisplay::tooltip($syncedAt);
@endphp
<span {{ $attributes->class(['mat-price-sync-age', 'mat-price-sync-age--stale' => $stale]) }}
      title="{{ $tooltip }}">updated {{ $meta }}</span>
