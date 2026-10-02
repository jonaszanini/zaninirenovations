@php
  /** @var string $fileInputId @var int $maxTotal */
  $portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
@endphp

<div class="admin-portfolio-ai-improve" id="portfolio-ai-improve">
  <div class="admin-form-group aim-prompt-group">
    <span class="admin-form-group-label">AI photo enhancement</span>
    <p class="admin-form-help">@if (!empty($allowExisting))Check <strong>Improve</strong> on a saved photo above, or pick and check Google Photos (or choose local files), then click Apply.@else Pick and check Google Photos below (or choose local files), then click Apply.@endif Gemini uses the <strong>Latest projects photo prompt improvement</strong> instruction from Tools → AI Instructions. Results are converted to WebP at {{ $portfolioWebp['quality'] }}% quality, max {{ $portfolioWebp['max_edge_px'] }}px, about {{ (int) round($portfolioWebp['max_bytes'] / 1024) }}&nbsp;KB each.@if (!empty($allowExisting)) Improved saved photos replace the original file immediately.@endif @if (!empty($stashDraftHint)) {{ $stashDraftHint }}@else New uploads are added to the Photos field above; Google picks are cleared so they are not imported twice.@endif</p>
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
