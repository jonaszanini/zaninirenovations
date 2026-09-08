{{-- One nested sheet: diagram + part sizes. No rip/crosscut steps. --}}
@php
  $stats = is_array($layout['stats'] ?? null) ? $layout['stats'] : [];
@endphp
<div class="mw-cut-sheet">
  <div class="mw-cut-sheet__stats">
    <strong>{{ $stats['label'] ?? 'Sheet' }}</strong>
    <span>Used {{ $stats['used_pct'] ?? 0 }}% · waste {{ $stats['waste_pct'] ?? 0 }}%</span>
  </div>
  <div class="mw-cut-svg-wrap">{!! $layout['svg'] ?? '' !!}</div>
  @if (! empty($layout['legend']))
    <ul class="mw-cut-legend">
      @foreach ($layout['legend'] as $row)
        <li>
          <span class="mw-cut-legend__n">{{ $row['n'] ?? '' }}</span>
          <span>{{ $row['label'] ?? 'Part' }} · {{ $row['size'] ?? '' }}@if (! empty($row['rotated'])) · rotated @endif</span>
        </li>
      @endforeach
    </ul>
  @endif
</div>
