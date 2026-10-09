<div class="portfolio-description-ai" id="portfolio-description-ai">
  <div class="nb2-generate-row portfolio-description-ai__actions">
    @component('admin.partials.ai_action_group', [
      'instructionKey' => \App\Models\AiInstruction::KEY_PORTFOLIO_DESCRIPTION,
      'instructionLabel' => 'Edit portfolio description prompt',
      'showHistory' => true,
    ])
      <button type="button" id="portfolio-desc-ai-btn" class="admin-btn admin-btn--primary">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Improve description with AI</span>
      </button>
    @endcomponent
    <div id="portfolio-desc-ai-loading" class="nb2-loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Writing with Gemini…</span>
    </div>
  </div>
  <span class="admin-label-with-tip">
    <span class="admin-form-group-label">Improve description</span>
    <x-admin.info-tip label="Improve description with AI">Write a rough description first. AI uses your text plus up to 8 project photos (uploads, checked Google Photos{{ isset($hasExistingPhotos) && $hasExistingPhotos ? ', or saved photos on edit' : '' }}) to draft two portfolio options. Use the history icon to switch between versions.</x-admin.info-tip>
  </span>
  <p id="portfolio-desc-ai-status" class="nb2-status" role="status"></p>
</div>

@include('admin.partials.ai_text_history_assets')
@once
  @push('scripts')
    <script src="{{ asset('js/admin-portfolio-description-ai.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-description-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
