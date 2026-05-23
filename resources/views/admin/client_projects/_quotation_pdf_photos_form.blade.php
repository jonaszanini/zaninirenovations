{{-- Expects: $project (ClientProject), $quotation (ClientProjectQuotation), $legacy (bool) --}}
@unless ($legacy)
  @php
    $projectImagePhotos = ($project->photos ?? collect())->filter(fn ($p) => $p->isImage())->values();
    $selectedPdfPhotoIds = $quotation->selectedPdfPhotoIds();
    $selectedSet = array_fill_keys($selectedPdfPhotoIds, true);
  @endphp
  <div class="cp-quotation-pdf-photos">
    <form method="post"
          action="{{ route('admin.client-projects.quotation.pdf-photos', [$project, $quotation]) }}"
          class="cp-quotation-pdf-photos__form">
      @csrf
      <div class="cp-quotation-pdf-photos__header">
        <div>
          <h4 class="cp-quotation-pdf-photos__title">Photos for client PDF</h4>
          <p class="admin-form-help cp-quotation-pdf-photos__help">
            Select project images to append after the quotation when you download or email the PDF.
            Videos and PDF files on the project are not embedded in the quotation PDF.
          </p>
        </div>
        @if ($projectImagePhotos->isNotEmpty())
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-save"></i> Save photo selection
          </button>
        @endif
      </div>

      @if ($projectImagePhotos->isEmpty())
        <p class="admin-form-help cp-quotation-pdf-photos__empty">
          No project images yet.
          <a href="{{ route('admin.client-projects.edit', $project) }}">Add photos on the project</a>
          first, then pick which ones to include here.
        </p>
      @else
        <div class="cp-quotation-pdf-photos__toolbar">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--xs" data-cp-q-pdf-photos-select-all>
            Select all
          </button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--xs" data-cp-q-pdf-photos-clear>
            Clear
          </button>
          @if (count($selectedPdfPhotoIds) > 0)
            <span class="cp-quotation-pdf-photos__count">{{ count($selectedPdfPhotoIds) }} selected for PDF</span>
          @endif
        </div>
        <div class="cp-quotation-pdf-photos__grid">
          @foreach ($projectImagePhotos as $photo)
            @php $checked = isset($selectedSet[$photo->id]); @endphp
            <label class="cp-quotation-pdf-photos__item @if ($checked) is-selected @endif">
              <input type="checkbox"
                     name="pdf_photo_ids[]"
                     value="{{ $photo->id }}"
                     @checked($checked)>
              <span class="cp-quotation-pdf-photos__thumb">
                <img src="{{ $photo->url() }}" alt="" loading="lazy" width="120" height="90">
              </span>
              <span class="cp-quotation-pdf-photos__check" aria-hidden="true"><i class="fa fa-check"></i></span>
            </label>
          @endforeach
        </div>
      @endif
    </form>
  </div>

  @once
    @push('scripts')
      <script>
        (function () {
          document.querySelectorAll('.cp-quotation-pdf-photos__form').forEach(function (form) {
            var selectAll = form.querySelector('[data-cp-q-pdf-photos-select-all]');
            var clearBtn = form.querySelector('[data-cp-q-pdf-photos-clear]');
            var boxes = form.querySelectorAll('input[type="checkbox"][name="pdf_photo_ids[]"]');

            function syncSelectedClass() {
              form.querySelectorAll('.cp-quotation-pdf-photos__item').forEach(function (item) {
                var cb = item.querySelector('input[type="checkbox"]');
                item.classList.toggle('is-selected', cb && cb.checked);
              });
            }

            boxes.forEach(function (cb) {
              cb.addEventListener('change', syncSelectedClass);
            });

            if (selectAll) {
              selectAll.addEventListener('click', function () {
                boxes.forEach(function (cb) { cb.checked = true; });
                syncSelectedClass();
              });
            }

            if (clearBtn) {
              clearBtn.addEventListener('click', function () {
                boxes.forEach(function (cb) { cb.checked = false; });
                syncSelectedClass();
              });
            }
          });
        })();
      </script>
    @endpush
  @endonce
@endunless
