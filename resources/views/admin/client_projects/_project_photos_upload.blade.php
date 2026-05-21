@php
  $maxEdge = (int) config('webp.max_edge_px', 1920);
  $maxPhotos = \App\Services\ClientProjectPhotoStorage::MAX_PHOTOS_PER_PROJECT;
  $inputId = $inputId ?? 'cp-project-photos';
  $previewId = $previewId ?? 'cp-project-photos-preview';
  $existingCount = (int) ($existingCount ?? 0);
  $canAdd = max(0, $maxPhotos - $existingCount);
@endphp

<div class="admin-form-group cp-project-photos-upload">
  <label for="{{ $inputId }}">Photos <span class="admin-form-help cp-inline-help">(optional)</span></label>
  @if ($canAdd > 0)
    <input type="file"
           id="{{ $inputId }}"
           name="photos[]"
           accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png"
           multiple>
    <p class="admin-form-help">
      JPEG, PNG, or WebP (10 MB max each). Images are converted to WebP and resized so the longest side is at most {{ $maxEdge }}px.
      @if ($existingCount > 0)
        You can add up to {{ $canAdd }} more ({{ $existingCount }} / {{ $maxPhotos }} already saved).
      @else
        Up to {{ $maxPhotos }} photos per project.
      @endif
    </p>
    <div class="cp-project-photos-preview" id="{{ $previewId }}" hidden aria-live="polite"></div>
  @else
    <p class="admin-form-help">Maximum of {{ $maxPhotos }} photos reached. Remove one on the project page to add more.</p>
  @endif
  @error('photos')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error('photos.*')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
</div>

@if ($canAdd > 0)
  @push('scripts')
    <script>
      (function () {
        var input = document.getElementById(@json($inputId));
        var preview = document.getElementById(@json($previewId));
        if (!input || !preview) return;

        var objectUrls = [];

        function clearPreview() {
          objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
          objectUrls = [];
          preview.innerHTML = '';
          preview.hidden = true;
        }

        input.addEventListener('change', function () {
          clearPreview();
          var files = Array.prototype.slice.call(input.files || []);
          if (!files.length) return;

          preview.hidden = false;
          var grid = document.createElement('div');
          grid.className = 'cp-project-photos-preview-grid';

          files.forEach(function (file) {
            if (!file.type || file.type.indexOf('image/') !== 0) return;
            var url = URL.createObjectURL(file);
            objectUrls.push(url);
            var img = document.createElement('img');
            img.src = url;
            img.alt = file.name || 'Selected photo';
            img.loading = 'lazy';
            grid.appendChild(img);
          });

          preview.appendChild(grid);
        });
      })();
    </script>
  @endpush
@endif
