@php
  $project = $project ?? null;
@endphp
@if ($project && (filled($project->notes) || $project->photos->isNotEmpty()))
  <details class="admin-accordion admin-card cp-project-context-card">
    <summary class="admin-accordion__summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="admin-accordion__title">Project Context</span>
    </summary>
    <div class="admin-accordion__panel">
      @if (filled($project->notes))
        <div style="margin-bottom: 12px;">
          <h5 style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Notes</h5>
          <div style="font-size: 13px; color: #334155; white-space: pre-wrap; line-height: 1.4;">{{ $project->notes }}</div>
        </div>
      @endif

      @if ($project->photos->isNotEmpty())
        <div>
          <h5 style="margin: 0 0 8px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Photos &amp; Files</h5>
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
