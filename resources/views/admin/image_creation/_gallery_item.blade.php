@php
  /** @var \App\Models\AiGeneratedImage $image */
@endphp
<article class="nb2-gallery-card" data-nb2-card data-image-id="{{ $image->id }}">
  <a href="{{ $image->imageUrl() }}" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open full image">
    <img src="{{ $image->imageUrl() }}" alt="" loading="lazy" decoding="async">
  </a>
  <div class="nb2-gallery-card__body">
    <p class="nb2-gallery-card__prompt" title="{{ $image->prompt }}">{{ \Illuminate\Support\Str::limit($image->prompt, 120) }}</p>
    <p class="nb2-gallery-card__meta">{{ $image->displayCreatedAt() }}</p>
    <div class="nb2-gallery-card__actions">
      <a href="{{ route('admin.image-creation.download', $image) }}" class="admin-btn admin-btn--ghost admin-btn--sm" download>
        <i class="fa fa-download"></i> Download
      </a>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-nb2-recreate data-prompt="{{ e($image->prompt) }}">
        <i class="fa fa-refresh"></i> Recreate
      </button>
      <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-nb2-delete data-delete-url="{{ route('admin.image-creation.destroy', $image) }}">
        <i class="fa fa-trash"></i> Delete
      </button>
    </div>
  </div>
</article>
