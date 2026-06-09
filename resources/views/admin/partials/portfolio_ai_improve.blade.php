@php
  /** @var string $fileInputId @var int $maxTotal */
@endphp

<div class="admin-portfolio-ai-improve" id="portfolio-ai-improve">
  <div class="admin-form-group aim-prompt-group">
    <span class="admin-form-group-label">AI photo enhancement</span>
    <p class="admin-form-hint">Pick and check Google Photos below (or choose local files), then click Apply. Gemini uses the <strong>Latest projects photo prompt improvement</strong> instruction from Tools → AI Instructions. Results are converted to WebP at {{ (int) config('webp.quality', 75) }}% quality, max {{ (int) config('webp.max_edge_px', 1280) }}px, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB each. Improved photos are added to the Photos field above; Google picks are cleared so they are not imported twice.</p>
  </div>

  <div class="nb2-generate-row">
    <button type="button" id="portfolio-ai-apply-btn" class="admin-btn admin-btn--primary">
      <i class="fa fa-magic" aria-hidden="true"></i>
      <span>Apply AI improvements</span>
    </button>
    <div id="portfolio-ai-loading" class="nb2-loading" hidden aria-live="polite">
      <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
      <span id="portfolio-ai-loading-text">Editing with Gemini…</span>
    </div>
  </div>

  <p id="portfolio-ai-status" class="nb2-status" role="status"></p>
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-portfolio-ai-improve.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-ai-improve.js')) ?: time() }}"></script>
  @endpush
@endonce
