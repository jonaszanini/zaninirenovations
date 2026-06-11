@php
  use App\Services\ClientProjectPhotoStorage;

  $maxEdge = (int) config('webp.max_edge_px', 1280);
  $webpQuality = (int) config('webp.quality', 75);
  $webpMaxKb = (int) round(config('webp.max_bytes', 102400) / 1024);
  $maxFiles = ClientProjectPhotoStorage::MAX_PHOTOS_PER_PROJECT;
  $inputId = $inputId ?? 'cp-project-photos';
  $previewId = $previewId ?? 'cp-project-photos-preview';
  $existingCount = (int) ($existingCount ?? 0);
  $canAdd = max(0, $maxFiles - $existingCount);
  $formId = $formId ?? null;
  $acceptList = implode(',', ClientProjectPhotoStorage::acceptAttributeList());
@endphp

<div class="admin-form-group cp-project-photos-upload">
  <label for="{{ $inputId }}">Photos &amp; files <span class="admin-form-help cp-inline-help">(optional)</span></label>
  @if ($canAdd> 0)
    <input class="admin-input" type="file"
           id="{{ $inputId }}"
           name="photos[]"
           @if ($formId) form="{{ $formId }}" @endif
           accept="{{ $acceptList }}"
           multiple>
    <p class="admin-form-help">
      <strong>Images</strong> — JPEG, PNG, or WebP ({{ ClientProjectPhotoStorage::MAX_IMAGE_KB / 1024 }} MB max each). Converted to WebP at {{ $webpQuality }}% quality, scaled to at most {{ $maxEdge }}px, and compressed to about {{ $webpMaxKb }}&nbsp;KB or less.
      <strong>Videos</strong> — MP4, WebM, or MOV ({{ ClientProjectPhotoStorage::MAX_VIDEO_KB / 1024 }} MB max each).
      <strong>PDFs</strong> — {{ ClientProjectPhotoStorage::MAX_PDF_KB / 1024 }} MB max each.
      @if ($existingCount> 0)
        You can add up to {{ $canAdd }} more ({{ $existingCount }} / {{ $maxFiles }} already saved).
      @else
        Up to {{ $maxFiles }} files per project.
      @endif
    </p>
    <div class="cp-project-photos-preview" id="{{ $previewId }}" hidden aria-live="polite"></div>
  @else
    <p class="admin-form-help">Maximum of {{ $maxFiles }} files reached. Remove one on the project page to add more.</p>
  @endif
  @error('photos')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
  @error('photos.*')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
</div>

@if ($canAdd> 0)
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

        function appendPdfTile(grid, file) {
          var tile = document.createElement('div');
          tile.className = 'cp-project-photos-preview-tile cp-project-photos-preview-tile--pdf';
          tile.innerHTML = '<i class="fa fa-file-pdf-o" aria-hidden="true"></i><span>' + (file.name || 'PDF') + '</span>';
          grid.appendChild(tile);
        }

        function appendVideoTile(grid, file) {
          var url = URL.createObjectURL(file);
          objectUrls.push(url);
          var tile = document.createElement('div');
          tile.className = 'cp-project-photos-preview-tile cp-project-photos-preview-tile--video';
          var video = document.createElement('video');
          video.src = url;
          video.muted = true;
          video.playsInline = true;
          video.preload = 'metadata';
          tile.appendChild(video);
          grid.appendChild(tile);
        }

        input.addEventListener('change', function () {
          clearPreview();
          var files = Array.prototype.slice.call(input.files || []);
          if (!files.length) return;

          preview.hidden = false;
          var grid = document.createElement('div');
          grid.className = 'cp-project-photos-preview-grid';

          files.forEach(function (file) {
            var type = file.type || '';
            if (type.indexOf('image/') === 0) {
              var imgUrl = URL.createObjectURL(file);
              objectUrls.push(imgUrl);
              var img = document.createElement('img');
              img.src = imgUrl;
              img.alt = file.name || 'Selected image';
              img.loading = 'lazy';
              grid.appendChild(img);
            } else if (type.indexOf('video/') === 0) {
              appendVideoTile(grid, file);
            } else if (type === 'application/pdf' || (file.name && /\.pdf$/i.test(file.name))) {
              appendPdfTile(grid, file);
            }
          });

          if (grid.childNodes.length) {
            preview.appendChild(grid);
          } else {
            preview.hidden = true;
          }
        });
      })();
    </script>
  @endpush
@endif
