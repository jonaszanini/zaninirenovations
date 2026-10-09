{{--
  Shared AI action cluster. DOM / tab order is always:
  [AI button + config] [optional extra pair] [history/undo icon].
  Each [button + config] is a nowrap pair so the sliders stay beside the
  button when the row wraps. Use @component … @endcomponent.
--}}
@php
  $instructionKey = $instructionKey ?? '';
  $instructionLabel = $instructionLabel ?? 'Edit AI prompt';
  $showHistory = ! empty($showHistory);
  $historyKey = $historyKey ?? null;
  $groupClass = trim((string) ($groupClass ?? ''));
  $extraHtml = isset($extra) ? trim((string) $extra) : '';
@endphp
<div class="admin-ai-action-group{{ $groupClass !== '' ? ' '.$groupClass : '' }}">
  <div class="admin-ai-action-pair">
    {{ $slot ?? '' }}
    @include('admin.partials.ai_instruction_edit_link', [
      'instructionKey' => $instructionKey,
      'instructionLabel' => $instructionLabel,
    ])
  </div>
  @if ($extraHtml !== '' || $showHistory)
    <div class="admin-ai-action-tail">
      @if ($extraHtml !== '')
        <div class="admin-ai-action-pair">
          {{ $extra }}
        </div>
      @endif
      @if ($showHistory)
        <span class="admin-ai-text-history-mount" data-history-mount
          @if (filled($historyKey)) data-history-key="{{ $historyKey }}" @endif></span>
      @endif
    </div>
  @endif
</div>
