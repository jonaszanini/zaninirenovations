@php
  /** @var list<array{path: string, url: string, name: string}> $draftPhotos */
  $draftPhotos = $draftPhotos ?? [];
  $gridId = $gridId ?? 'portfolio-draft-photo-grid';
@endphp

<div
  class="admin-form-group admin-portfolio-draft-photos"
  id="portfolio-draft-photos-root"
  @if ($draftPhotos === []) hidden @endif>
  <span class="admin-form-group-label">Saved photos (kept if the form needs fixing)</span>
  <p class="admin-form-help" style="margin-top:0;margin-bottom:0.65rem;">
    These are stored on the server while you finish the form. They stay here if a required field is missing.
  </p>
  <div class="admin-local-photo-preview-grid admin-portfolio-draft-grid" id="{{ $gridId }}">
    @foreach ($draftPhotos as $photo)
      <div class="admin-portfolio-draft-tile" data-draft-path="{{ $photo['path'] }}">
        <img src="{{ $photo['url'] }}" alt="" loading="lazy" decoding="async">
        <span class="admin-local-photo-preview-caption">{{ $photo['name'] }}</span>
        <button type="button" class="admin-portfolio-draft-remove js-portfolio-draft-remove" title="Remove saved photo" aria-label="Remove saved photo">
          <i class="fa fa-times" aria-hidden="true"></i>
        </button>
        <input type="hidden" name="draft_photos[]" value="{{ $photo['path'] }}">
      </div>
    @endforeach
  </div>
</div>
