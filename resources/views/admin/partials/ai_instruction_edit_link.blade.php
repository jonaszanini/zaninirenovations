@php
  $instructionKey = $instructionKey ?? '';
  $instructionLabel = $instructionLabel ?? 'Edit AI prompt';
@endphp
@if (filled($instructionKey))
  <a href="{{ route('admin.ai-instructions.edit', $instructionKey) }}"
     class="admin-ai-instruction-edit-link"
     title="{{ $instructionLabel }}"
     aria-label="{{ $instructionLabel }}"
     target="_blank" rel="noopener">
    <i class="fa fa-sliders" aria-hidden="true"></i>
  </a>
@endif
