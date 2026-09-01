@php
  $workDescriptionTextareaId = $workDescriptionTextareaId ?? 'work-description';
  $aiContextFormId = $aiContextFormId ?? '';
  $aiQuotationType = $aiQuotationType ?? '';
  $showCreateFromQuote = $aiQuotationType !== '' && $aiQuotationType !== 'standard';
  // Fallback to URL-based unique key so create and edit pages have independent histories
  $historyKey = $historyKey ?? ('quotation-work-desc:' . md5(request()->fullUrl()));
@endphp
<div class="quotation-work-description-ai"
     data-textarea-id="{{ $workDescriptionTextareaId }}"
     data-history-key="{{ $historyKey }}"
     data-improve-url="{{ route('admin.quotations.work-description.ai') }}"
     data-csrf="{{ csrf_token() }}"
     @if ($aiContextFormId) data-context-form="{{ $aiContextFormId }}" @endif
     @if ($aiQuotationType) data-quotation-type="{{ $aiQuotationType }}" @endif>
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
      @if ($showCreateFromQuote)
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm quotation-work-description-ai__from-quote">
          <i class="fa fa-list-alt" aria-hidden="true"></i>
          <span>Create automatically from quotation</span>
        </button>
        @include('admin.partials.ai_instruction_edit_link', [
          'instructionKey' => \App\Models\AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION_FROM_QUOTE,
          'instructionLabel' => 'Edit create-from-quotation prompt',
        ])
      @endif
      <span class="admin-ai-text-history-mount" data-history-mount></span>
    </div>
    @if (!empty($trailingActionPartial))
      <div class="admin-ai-action-group admin-ai-action-group--trailing">
        @include($trailingActionPartial)
      </div>
    @endif
    <div class="nb2-loading quotation-work-description-ai__loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Writing with Gemini…</span>
    </div>
  </div>
  <p class="nb2-status quotation-work-description-ai__status" role="status"></p>
</div>

@include('admin.partials.ai_text_history_assets')
@once
  @push('scripts')
    <script src="{{ asset('js/admin-quotation-work-description-ai.js') }}?v={{ @filemtime(public_path('js/admin-quotation-work-description-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
