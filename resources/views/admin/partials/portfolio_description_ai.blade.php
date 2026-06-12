<div class="portfolio-description-ai" id="portfolio-description-ai">
  <div class="nb2-generate-row portfolio-description-ai__actions">
    <div class="admin-ai-action-group">
      <button type="button" id="portfolio-desc-ai-btn" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Improve description with AI</span>
      </button>
      @include('admin.partials.ai_instruction_edit_link', [
        'instructionKey' => \App\Models\AiInstruction::KEY_PORTFOLIO_DESCRIPTION,
        'instructionLabel' => 'Edit portfolio description prompt',
      ])
    </div>
    <div id="portfolio-desc-ai-loading" class="nb2-loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Writing with Gemini…</span>
    </div>
  </div>
  <p class="admin-form-help">Write a rough description first. AI uses your text plus up to 8 project photos (uploads, checked Google Photos{{ isset($hasExistingPhotos) && $hasExistingPhotos ? ', or saved photos on edit' : '' }}) to draft two portfolio options.</p>
  <div id="portfolio-desc-ai-options" class="portfolio-description-ai__options" hidden>
    <span class="portfolio-description-ai__options-label">Switch version:</span>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-portfolio-desc-option="1">Option 1 · Craftsmanship</button>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-portfolio-desc-option="2">Option 2 · Technical</button>
  </div>
  <p id="portfolio-desc-ai-status" class="nb2-status" role="status"></p>
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-portfolio-description-ai.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-description-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
