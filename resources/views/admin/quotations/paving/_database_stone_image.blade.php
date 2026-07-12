@php
  $imageUrlValue = old('image_url', $imageUrl ?? '');
  $hasImageUrl = filled($imageUrlValue);
@endphp
<div class="pq-db-field-group pq-db-image-cell">
  <div class="pq-db-image-label-row">
    <span class="pq-db-field-label">Image</span>
    <div class="pq-db-image-mode" role="group" aria-label="Image source">
      <button type="button" class="pq-db-image-mode-btn is-active" data-pq-image-mode="url">URL</button>
      <button type="button" class="pq-db-image-mode-btn" data-pq-image-mode="upload">Upload</button>
    </div>
  </div>
  <div class="pq-db-image-row">
    <div class="pq-db-stone-thumb-wrap pq-db-stone-preview" aria-hidden="true">
      @if ($hasImageUrl)
        <img src="{{ $imageUrlValue }}" alt="" class="pq-db-stone-preview-img">
        <i class="fa fa-cubes pq-db-stone-preview-placeholder" style="display:none;" aria-hidden="true"></i>
      @else
        <img src="" alt="" class="pq-db-stone-preview-img" style="display:none;">
        <i class="fa fa-cubes pq-db-stone-preview-placeholder" aria-hidden="true"></i>
      @endif
    </div>
    <div class="pq-db-image-panel pq-db-image-panel--url">
      <input type="url" name="image_url" value="{{ $imageUrlValue }}" maxlength="500"
             class="admin-input pq-db-stone-image-url" placeholder="https://…" aria-label="Image URL">
    </div>
    <div class="pq-db-image-panel pq-db-image-panel--upload" hidden>
      <label class="pq-db-file-picker">
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
               class="pq-db-stone-image-file" aria-label="Upload stone image">
        <span class="pq-db-file-picker-btn">Choose file…</span>
        <span class="pq-db-file-picker-name">No file selected</span>
      </label>
    </div>
  </div>
</div>
