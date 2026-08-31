@php
  $project = $project ?? null;
@endphp
@if ($project)
  <a href="{{ route('admin.client-projects.show', $project) }}#quotation" class="admin-btn admin-btn--ghost admin-btn--sm">
    <i class="fa fa-arrow-left"></i> Back to project
  </a>
@endif
