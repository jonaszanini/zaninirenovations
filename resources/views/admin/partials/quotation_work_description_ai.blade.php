@php
  $workDescriptionTextareaId = $workDescriptionTextareaId ?? 'work-description';
@endphp
<div class="quotation-work-description-ai"
     data-textarea-id="{{ $workDescriptionTextareaId }}"
     data-improve-url="{{ route('admin.quotations.work-description.ai') }}"
     data-csrf="{{ csrf_token() }}">
  <div class="nb2-generate-row quotation-work-description-ai__actions">
    <div class="admin-ai-action-group">
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm quotation-work-description-ai__btn">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Improve description with AI</span>
      </button>
      @include('admin.partials.ai_instruction_edit_link', [
        'instructionKey' => \App\Models\AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION,
        'instructionLabel' => 'Edit quotation work description prompt',
      ])
    </div>
    <div class="nb2-loading quotation-work-description-ai__loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Writing with Gemini…</span>
    </div>
  </div>
  <p class="nb2-status quotation-work-description-ai__status" role="status"></p>
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-quotation-work-description-ai.js') }}?v={{ @filemtime(public_path('js/admin-quotation-work-description-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
