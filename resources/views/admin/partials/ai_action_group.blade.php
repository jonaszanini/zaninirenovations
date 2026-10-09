{{--
  Shared AI action cluster. DOM / tab order is always:
  [AI button(s)] [config/sliders icon] [optional extra] [history/undo icon].
  Use @component('admin.partials.ai_action_group', [...]) … @endcomponent
  so future buttons inherit this order.
--}}
@php
  $instructionKey = $instructionKey ?? '';
  $instructionLabel = $instructionLabel ?? 'Edit AI prompt';
  $showHistory = ! empty($showHistory);
  $historyKey = $historyKey ?? null;
  $groupClass = trim((string) ($groupClass ?? ''));
@endphp
<div class="admin-ai-action-group{{ $groupClass !== '' ? ' '.$groupClass : '' }}">
  {{ $slot ?? '' }}
  @include('admin.partials.ai_instruction_edit_link', [
    'instructionKey' => $instructionKey,
    'instructionLabel' => $instructionLabel,
  ])
  {{ $extra ?? '' }}
  @if ($showHistory)
    <span class="admin-ai-text-history-mount" data-history-mount
      @if (filled($historyKey)) data-history-key="{{ $historyKey }}" @endif></span>
  @endif
</div>
