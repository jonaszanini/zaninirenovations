{{--
  Placeholder shown on new (unsaved) quotations before link/create options are available.

  Optional:
    $linkProject     — ClientProject when opened from a project context
    $wrapAdminCard   — wrap in admin-card (default false)
    $cardStyle       — inline style on the card wrapper
--}}
@php
  $wrapAdminCard = $wrapAdminCard ?? false;
@endphp
<div class="{{ $wrapAdminCard ? 'admin-card ' : '' }}fq-link-project-card"@if (! empty($cardStyle)) style="{{ $cardStyle }}"@endif>
  <h4 style="margin:0;">
    <span class="admin-label-with-tip">
      Link to project
      <x-admin.info-tip label="Link to project">Save the quotation first (use Save quotation, not draft) with a total greater than zero. Then this page will show options to link an existing project or create a new one.@if (! empty($linkProject)) You opened this from {{ $linkProject->name }} — a published save will attach automatically when possible.@endif</x-admin.info-tip>
    </span>
  </h4>
</div>
