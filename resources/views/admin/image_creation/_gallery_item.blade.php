@php
  /** @var \App\Models\AiGeneratedImage $image */
@endphp
<article
  class="nb2-gallery-card"
  data-nb2-card
  data-image-id="{{ $image->id }}"
>
  <a href="{{ $image->imageUrl() }}" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open WebP preview">
    <img src="{{ $image->imageUrl() }}" alt="" loading="lazy" decoding="async">
  </a>
  <div class="nb2-gallery-card__body">
    <p class="nb2-gallery-card__prompt" title="{{ $image->prompt }}">{{ \Illuminate\Support\Str::limit($image->prompt, 120) }}</p>
    <p class="nb2-gallery-card__meta">{{ $image->displayMeta() }}</p>
    <div class="nb2-gallery-card__actions">
      @if ($image->hasOriginalDownload())
        <a href="{{ route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'original']) }}" class="admin-btn admin-btn--ghost admin-btn--sm nb2-download-original" download>
          <i class="fa fa-download"></i> Original
        </a>
      @endif
      @if ($image->hasWebpDownload())
        <a href="{{ route('admin.image-creation.download', ['ai_generated_image' => $image, 'format' => 'webp']) }}" class="admin-btn admin-btn--ghost admin-btn--sm nb2-download-webp" download>
          <i class="fa fa-file-image-o"></i> WebP
        </a>
      @endif
      <button
        type="button"
        class="admin-btn admin-btn--ghost admin-btn--sm"
        data-nb2-recreate
        data-prompt="{{ e($image->prompt) }}"
        data-aspect-ratio="{{ e($image->aspect_ratio ?: '16:9') }}"
        data-image-size="{{ e($image->image_size ?: '1K') }}"
      >
        <i class="fa fa-refresh"></i> Recreate
      </button>
      <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-nb2-delete data-delete-url="{{ route('admin.image-creation.destroy', $image) }}">
        <i class="fa fa-trash"></i> Delete
      </button>
    </div>
  </div>
</article>
