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
  <h4 style="margin:0 0 8px;">Link to project</h4>
  <p class="admin-form-help" style="margin:0;">
    <strong>Save the quotation first</strong> (use <em>Save quotation</em>, not draft) with a total greater than zero.
    Then this page will show options to link an existing project or create a new one.
    @if (! empty($linkProject))
      <span class="cp-form-help-flush" style="display:block;margin-top:8px;">
        You opened this from <strong>{{ $linkProject->name }}</strong> — a published save will attach automatically when possible.
      </span>
    @endif
  </p>
</div>
