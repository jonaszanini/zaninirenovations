@php
  $cvStyleKey = $project->contactedViaStyleKey();
@endphp
<div class="cp-subtle {{ $lineClass ?? '' }}">
  Contact via:
  @if ($cvStyleKey !== null)
    <span class="cp-contacted-via-value cp-contacted-via-value--{{ $cvStyleKey }}">{{ $project->contactedViaDisplay() }}</span>
  @else
    {{ $project->contactedViaDisplay() }}
  @endif
</div>
