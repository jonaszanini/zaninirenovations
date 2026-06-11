@php
  /**
   * Local file upload + Google Photos picker (shared by portfolio and AI modify).
   *
   * @var string $fileInputId
   * @var string $fileInputName
   * @var string $fileLabel
   * @var string $fileHint
   * @var int $maxTotal
   * @var string $statusMode
   * @var bool $formSync
   * @var string|null $googleFootText
   * @var bool $showPreviewGrid
   * @var string|null $previewGridId
   */
  $fileInputName = $fileInputName ?? 'photos[]';
  $statusMode = $statusMode ?? 'total';
  $formSync = $formSync ?? true;
  $showPreviewGrid = $showPreviewGrid ?? false;
  $previewGridId = $previewGridId ?? 'admin-local-photo-preview-grid';
@endphp

<div class="admin-portfolio-upload-section">
  <div class="admin-form-group admin-portfolio-upload-files">
    <label for="{{ $fileInputId }}">{{ $fileLabel }}</label>
    <input class="admin-input"
      type="file"
      id="{{ $fileInputId }}"
      name="{{ $fileInputName }}"
      accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png"
      multiple
      @if($showPreviewGrid) data-admin-local-preview-target="{{ $previewGridId }}" @endif>
    <p class="admin-form-help">{{ $fileHint }}</p>
    @if ($showPreviewGrid)
      <div class="admin-local-photo-preview-grid" id="{{ $previewGridId }}" hidden aria-live="polite"></div>
    @endif
    @isset($fileErrors)
      @foreach ((array) $fileErrors as $errorKey)
        @error($errorKey)
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      @endforeach
    @else
      @error('photos')
        <div class="admin-form-error">{{ $message }}</div>
      @enderror
      @error('photos.*')
        <div class="admin-form-error">{{ $message }}</div>
      @enderror
      @error('source_images')
        <div class="admin-form-error">{{ $message }}</div>
      @enderror
      @error('source_images.*')
        <div class="admin-form-error">{{ $message }}</div>
      @enderror
    @endisset
  </div>

  <div class="admin-portfolio-upload-divider" aria-hidden="true"><span>or</span></div>

  @include('admin.partials.google_photos_picker', [
    'maxTotal' => $maxTotal,
    'fileInputId' => $fileInputId,
    'statusMode' => $statusMode,
    'formSync' => $formSync,
    'footText' => $googleFootText,
  ])
</div>
