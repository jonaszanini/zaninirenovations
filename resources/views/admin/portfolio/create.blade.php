@extends('admin.layouts.app')

@section('heading', 'New project')

@section('content')
  <div class="cp-edit-project-layout cp-portfolio-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
    <form id="portfolio-create-form" action="{{ route('admin.portfolio.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input class="admin-input" type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255">
        @error('title')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea class="admin-input" id="description" name="description" required>{{ old('description') }}</textarea>
        @include('admin.partials.portfolio_description_ai')
        @error('description')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', []);
      @endphp
      <div class="admin-form-group">
        <div class="admin-form-group__label-row">
          <span class="admin-form-group-label">Categories</span>
          <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" title="Manage portfolio categories">
            <i class="fa fa-cog" aria-hidden="true"></i> Configure
          </a>
        </div>
        <p class="admin-form-help" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        <div class="admin-category-checkboxes">
          @foreach ($categories as $cat)
            <label class="admin-checkbox admin-checkbox-tile">
              <input type="checkbox" class="admin-checkbox__input" name="project_category_ids[]" value="{{ $cat->id }}" @checked(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">{{ $cat->name }}</span>
            </label>
          @endforeach
        </div>
        @error('project_category_ids')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
        @error('project_category_ids.*')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input class="admin-input" type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', now()->toDateString()) }}" required>
        <p class="admin-form-help">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
      @endphp
      <div class="admin-portfolio-upload-section">
        @include('admin.partials.local_and_google_photos', [
          'fileInputId' => 'photos',
          'fileInputName' => 'photos[]',
          'fileLabel' => 'Photos',
          'fileHint' => 'WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP at '.$portfolioWebp['quality'].'% quality, scaled to at most '.$portfolioWebp['max_edge_px'].'px on the longest side, and compressed to about '.(int) round($portfolioWebp['max_bytes'] / 1024).' KB or less; originals are not kept. Up to 15 photos per project. Display order matches the order you select (first = top). To straighten, rotate, or crop a photo after it is saved, edit the project and click <strong>Edit</strong> on the thumbnail.',
          'maxTotal' => 15,
          'statusMode' => 'total',
          'formSync' => true,
          'googleFootText' => 'Combined total cannot exceed 15 (file uploads + picked Google photos).',
          'showPreviewGrid' => true,
          'previewGridId' => 'portfolio-local-preview-grid',
          'fileErrors' => ['photos', 'photos.*'],
        ])

        @include('admin.partials.portfolio_draft_photos', [
          'draftPhotos' => $draftPhotos ?? [],
          'gridId' => 'portfolio-draft-photo-grid',
        ])

        @include('admin.partials.portfolio_ai_improve', [
          'fileInputId' => 'photos',
          'maxTotal' => 15,
          'stashDraftHint' => ' Improved photos are saved on the server (see Saved photos below) so they are kept if you need to fix the form.',
        ])
      </div>
    </form>
    </div>

    <aside class="cp-edit-project-actions-aside" role="region" aria-label="Portfolio form actions">
      <button type="submit" form="portfolio-create-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-check"></i> Save project
      </button>
      <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
    </aside>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-portfolio-create.js') }}?v={{ @filemtime(public_path('js/admin-portfolio-create.js')) ?: time() }}"></script>
  <script>
    window.portfolioCreateConfig = {
      formId: 'portfolio-create-form',
      fileInputId: 'photos',
      maxTotal: 15,
      stashDraftUrl: @json(route('admin.portfolio.draft-photos.store')),
      removeDraftUrl: @json(route('admin.portfolio.draft-photos.destroy')),
    };
    window.portfolioAiImproveConfig = {
      fileInputId: 'photos',
      maxTotal: 15,
      stashDrafts: true,
      csrfToken: @json(csrf_token()),
      aiImproveUrl: @json(route('admin.portfolio.ai-improve-photos')),
      saveHint: 'Save project',
    };
    window.portfolioDescriptionAiConfig = {
      descriptionInputId: 'description',
      fileInputId: 'photos',
      csrfToken: @json(csrf_token()),
      improveUrl: @json(route('admin.portfolio.ai-improve-description')),
    };
  </script>
@endpush


