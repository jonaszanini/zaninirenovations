@php
  $cvStyleKey = $project->contactedViaStyleKey();
  $coStyleKey = $project->conversationOnStyleKey();
@endphp
<div class="cp-subtle {{ $lineClass ?? '' }}">
  Contact via:
  @if ($cvStyleKey !== null)
    <span class="cp-contacted-via-value cp-contacted-via-value--{{ $cvStyleKey }}">{{ $project->contactedViaDisplay() }}</span>
  @else
    {{ $project->contactedViaDisplay() }}
  @endif
</div>
@if (filled($project->conversation_on))
  <div class="cp-subtle {{ $lineClass ?? '' }}" style="margin-top:0.35rem;">
    Conversation on:
    @if ($coStyleKey !== null)
      <span class="cp-contacted-via-value cp-contacted-via-value--{{ $coStyleKey }}">{{ $project->conversationOnDisplay() }}</span>
    @else
      {{ $project->conversationOnDisplay() }}
    @endif
  </div>
@endif
