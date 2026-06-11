@php
  /** @var \App\Models\AiGeneratedImage $image */
@endphp
<article
  class="nb2-gallery-card"
  data-aim-card
  data-image-id="{{ $image->id }}">
  <a href="{{ $image->imageUrl() }}" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open edited image">
    <img src="{{ $image->imageUrl() }}" alt="" loading="lazy" decoding="async">
  </a>
  <div class="nb2-gallery-card__body">
    <p class="nb2-gallery-card__prompt" title="{{ $image->prompt }}">{{ \Illuminate\Support\Str::limit($image->prompt, 120) }}</p>
    <p class="nb2-gallery-card__meta">{{ $image->displayMeta() }}</p>
    <div class="nb2-gallery-card__actions">
      @if ($image->hasOriginalDownload())
        <a href="{{ route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'original']) }}" class="admin-btn admin-btn--ghost admin-btn--sm aim-download-original" download>
          <i class="fa fa-download"></i> Edited
        </a>
      @endif
      @if (filled($image->source_image_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($image->source_image_path))
        <a href="{{ route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'source']) }}" class="admin-btn admin-btn--ghost admin-btn--sm aim-download-source" download>
          <i class="fa fa-file-image-o"></i> Source
        </a>
      @endif
      <button
        type="button"
        class="admin-btn admin-btn--ghost admin-btn--sm"
        data-aim-reuse-prompt
        data-prompt="{{ e($image->prompt) }}">
        <i class="fa fa-refresh"></i> Reuse prompt
      </button>
      <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-aim-delete data-delete-url="{{ route('admin.image-creation.destroy', $image) }}">
        <i class="fa fa-trash"></i> Delete
      </button>
    </div>
  </div>
</article>
