@extends('admin.layouts.app')

@section('heading', 'New project')

@section('content')
  <div class="cp-edit-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
    <form id="portfolio-create-form" action="{{ route('admin.portfolio.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255">
        @error('title')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required>{{ old('description') }}</textarea>
        @include('admin.partials.portfolio_description_ai')
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', []);
      @endphp
      <div class="admin-form-group">
        <span class="admin-form-group-label">Categories</span>
        <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
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
          <div class="admin-error">{{ $message }}</div>
        @enderror
        @error('project_category_ids.*')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', now()->toDateString()) }}" required>
        <p class="admin-form-hint">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php($portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings())
      <div class="admin-portfolio-upload-section">
        @include('admin.partials.local_and_google_photos', [
          'fileInputId' => 'photos',
          'fileInputName' => 'photos[]',
          'fileLabel' => 'Photos',
          'fileHint' => 'WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP at '.$portfolioWebp['quality'].'% quality, scaled to at most '.$portfolioWebp['max_edge_px'].'px on the longest side, and compressed to about '.(int) round($portfolioWebp['max_bytes'] / 1024).' KB or less; originals are not kept. Up to 15 photos per project. Display order matches the order you select (first = top). To crop a photo after it is saved, edit the project and use the crop button on the thumbnail.',
          'maxTotal' => 15,
          'statusMode' => 'total',
          'formSync' => true,
          'googleFootText' => 'Combined total cannot exceed 15 (file uploads + picked Google photos).',
          'showPreviewGrid' => true,
          'previewGridId' => 'portfolio-local-preview-grid',
          'fileErrors' => ['photos', 'photos.*'],
        ])

        @include('admin.partials.portfolio_ai_improve', [
          'fileInputId' => 'photos',
          'maxTotal' => 15,
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
  <script>
    window.portfolioAiImproveConfig = {
      fileInputId: 'photos',
      maxTotal: 15,
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


