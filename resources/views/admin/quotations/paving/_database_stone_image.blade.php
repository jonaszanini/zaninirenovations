@php
  $imageUrlValue = old('image_url', $imageUrl ?? '');
@endphp
<div class="pq-db-field-group pq-db-image-group">
  <span class="pq-db-field-label">Stone image</span>
  <input type="url" name="image_url" value="{{ $imageUrlValue }}" maxlength="500"
         class="admin-input pq-db-stone-image-url" placeholder="Image URL (https://…)" aria-label="Image URL">
  <div class="pq-db-image-upload-row">
    <label class="admin-btn admin-btn--ghost admin-btn--xs pq-db-image-upload-label">
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
             class="pq-db-stone-image-file" aria-label="Upload stone image">
      Upload file
    </label>
    <span class="pq-db-image-upload-hint">JPG, PNG, WebP · max 5 MB</span>
  </div>
</div>
