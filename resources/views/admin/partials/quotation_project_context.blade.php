@php
  $project = $project ?? null;
@endphp
@if ($project && (filled($project->notes) || $project->photos->isNotEmpty() || $project->documentLinks->isNotEmpty()))
  <details class="admin-accordion admin-card cp-project-context-card">
    <summary class="admin-accordion__summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="admin-accordion__title">Project Context</span>
    </summary>
    <div class="admin-accordion__panel">
      @if (filled($project->notes))
        <div class="cp-project-context-notes">
          <h5 class="cp-project-context-notes__label">Notes</h5>
          <div class="cp-project-context-notes__body">{{ $project->notes }}</div>
        </div>
      @endif

      @if ($project->photos->isNotEmpty() || $project->documentLinks->isNotEmpty())
        <div class="cp-project-context-photos">
          <h5 class="cp-project-context-notes__label">Photos &amp; Files</h5>
          @include('admin.client_projects._project_photos_gallery', [
            'project' => $project,
            'galleryId' => 'cp-quotation-context-photos',
            'editable' => false,
            'showHeading' => false,
          ])
        </div>
      @endif
    </div>
  </details>
@endif
