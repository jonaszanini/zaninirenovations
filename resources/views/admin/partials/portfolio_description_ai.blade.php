<div class="portfolio-description-ai" id="portfolio-description-ai">
  <div class="nb2-generate-row portfolio-description-ai__actions">
    <div class="admin-ai-action-group">
      @include('admin.partials.ai_instruction_edit_link', [
        'instructionKey' => \App\Models\AiInstruction::KEY_PORTFOLIO_DESCRIPTION,
        'instructionLabel' => 'Edit portfolio description prompt',
      ])
      <button type="button" id="portfolio-desc-ai-btn" class="admin-btn admin-btn--primary">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Improve description with AI</span>
      </button>
      <span class="admin-ai-text-history-mount" data-history-mount></span>
    </div>
    <div id="portfolio-desc-ai-loading" class="nb2-loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Writing with Gemini…</span>
    </div>
  </div>
  <p class="admin-form-help">Write a rough description first. AI uses your text plus up to 8 project photos (uploads, checked Google Photos{{ isset($hasExistingPhotos) && $hasExistingPhotos ? ', or saved photos on edit' : '' }}) to draft two portfolio options. Use the history icon to switch between versions.</p>
  <p id="portfolio-desc-ai-status" class="nb2-status" role="status"></p>
</div>

@include('admin.partials.ai_text_history_assets')
@once
  @push('scripts')
    <script src="{{ asset('js/admin-portfolio-description-ai.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-description-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
