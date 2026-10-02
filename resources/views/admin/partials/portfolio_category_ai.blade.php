<div class="portfolio-category-ai" id="portfolio-category-ai">
  <div class="nb2-generate-row portfolio-category-ai__actions">
    <div class="admin-ai-action-group">
      <button type="button" id="portfolio-category-ai-btn" class="admin-btn admin-btn--primary">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Auto categorize with AI</span>
      </button>
      @include('admin.partials.ai_instruction_edit_link', [
        'instructionKey' => \App\Models\AiInstruction::KEY_PORTFOLIO_CATEGORIES,
        'instructionLabel' => 'Edit portfolio category prompt',
      ])
    </div>
    <div id="portfolio-category-ai-loading" class="nb2-loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span>Categorizing with Gemini…</span>
    </div>
  </div>
  <p class="admin-form-help portfolio-category-ai__help">Sends the title, description, and up to 8 photos. Matching categories are checked, and any new ones are created and checked.</p>
  <p id="portfolio-category-ai-status" class="nb2-status portfolio-category-ai__status" role="status"></p>
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-portfolio-category-ai.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-category-ai.js')) ?: time() }}"></script>
  @endpush
@endonce
