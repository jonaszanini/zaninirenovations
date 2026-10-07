@php
  $roomAreaImport = $roomAreaImport ?? null;
@endphp
@if (is_array($roomAreaImport) && ! empty($roomAreaImport['id']))
  <div class="admin-flash admin-flash--info util-rq-prefill-banner">
    Measurements filled from Room Area:
    <strong>{{ $roomAreaImport['label'] }}</strong>
    @if (! empty($roomAreaImport['note']))
      <span class="util-rq-prefill-banner__note">{{ $roomAreaImport['note'] }}</span>
    @endif
    <a href="{{ route('admin.utilities.room-area.quote', ['layout' => $roomAreaImport['id']]) }}">Back to Create quote</a>
  </div>
@endif
