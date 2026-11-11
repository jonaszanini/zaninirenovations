@php
  $visitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
  $visitTimeVal = old('visit_time', $project->visitTimeForInput());
  $startDateVal = old('start_date', optional($project->start_date)->toDateString());
  $endDateVal = old('end_date', optional($project->end_date)->toDateString());
  $visitFormOpen = $errors->has('visit_date') || $errors->has('visit_time');
  $scheduleFormOpen = $errors->has('start_date') || $errors->has('end_date');
  $visitRequiredInitial = old('visit_required', $project->visit_required) ? '1' : '0';

  if ($project->visit_done) {
    $visitDisplay = 'Done';
    if ($project->visit_date) {
      $visitDisplay .= ' ('.$project->visitDateTimeDisplay().')';
    }
  } elseif ($project->visit_date) {
    $visitDisplay = $project->visitDateTimeDisplay();
  } elseif ($project->visit_required) {
    $visitDisplay = 'Required, date not set yet';
  } else {
    $visitDisplay = 'Not set';
  }

  if ($project->start_date && $project->end_date) {
    $scheduleDisplay = \App\Models\ClientProject::formatDate($project->start_date)
      .' to '
      .\App\Models\ClientProject::formatDate($project->end_date);
  } elseif ($project->start_date) {
    $scheduleDisplay = \App\Models\ClientProject::formatDate($project->start_date);
  } elseif ($project->end_date) {
    $scheduleDisplay = \App\Models\ClientProject::formatDate($project->end_date);
  } else {
    $scheduleDisplay = 'Not set';
  }
@endphp

<div class="admin-card cp-overview-details-card">
  <h3>Details</h3>
  <dl class="cp-dl">
    <dt>Project name</dt><dd>{{ $project->name }}</dd>
    <dt>Client</dt><dd>@include('admin.partials.client_directory_link', [
      'project' => $project,
      'label' => $project->client_name,
    ])</dd>
    <dt>Email</dt><dd>@include('admin.partials.client_directory_link', [
      'project' => $project,
      'label' => $project->client_email,
      'fallbackMailto' => true,
    ])</dd>
    <dt>Secondary email</dt><dd>{{ $project->secondaryClientEmail() ?: 'Not set' }}</dd>
    <dt>Phone</dt><dd>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: 'Not set' }}</dd>
    <dt>Address</dt><dd>{{ $project->client_address ?: 'Not set' }}</dd>
    <dt>Created</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->created_at) }}</dd>
    <dt>Status changed</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->status_changed_at) }}</dd>

    <dt>
      <span class="cp-overview-dt">
        Visit
        <x-admin.info-tip label="Visit">On site visit before quoting. Set a date when you know it.</x-admin.info-tip>
      </span>
    </dt>
    <dd class="cp-overview-editable" data-cp-overview-editable="visit">
      <div class="cp-overview-value-row">
        <span class="cp-overview-value" data-cp-overview-visit-value>{{ $visitDisplay }}</span>
        <button type="button"
                class="cp-overview-edit-btn"
                data-cp-overview-edit="visit"
                aria-expanded="{{ $visitFormOpen ? 'true' : 'false' }}"
                aria-controls="cp-overview-visit-form"
                aria-label="Edit visit">
          <i class="fa fa-pencil" aria-hidden="true"></i>
        </button>
      </div>
      <form id="cp-overview-visit-form"
            class="cp-overview-inline-form"
            action="{{ route('admin.client-projects.update', $project) }}"
            method="post"
            data-cp-overview-form="visit"
            @unless ($visitFormOpen) hidden @endunless>
        @csrf
        @method('PUT')
        <input type="hidden" name="name" value="{{ $project->name }}">
        <input type="hidden" name="client_name" value="{{ $project->client_name }}">
        <input type="hidden" name="return_tab" value="overview">
        <input type="hidden"
               name="visit_required"
               value="{{ $visitRequiredInitial }}"
               data-cp-overview-visit-required>
        <div class="cp-overview-inline-fields">
          <div class="admin-form-group">
            <label for="cp-overview-visit-date">Visit date</label>
            <input class="admin-input"
                   type="date"
                   id="cp-overview-visit-date"
                   name="visit_date"
                   value="{{ $visitDateVal }}"
                   data-cp-overview-visit-date>
            @error('visit_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-overview-visit-time">Time</label>
            <input class="admin-input"
                   type="time"
                   id="cp-overview-visit-time"
                   name="visit_time"
                   value="{{ $visitTimeVal }}"
                   step="900"
                   data-cp-overview-visit-time>
            @error('visit_time') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>
        <div class="cp-overview-inline-actions">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save visit</button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-overview-cancel="visit">Cancel</button>
        </div>
      </form>
    </dd>

    <dt>
      <span class="cp-overview-dt">
        Schedule
        <x-admin.info-tip label="Schedule">Project start and end as shown on the Project Calendar and used for the Google Calendar email.</x-admin.info-tip>
      </span>
    </dt>
    <dd class="cp-overview-editable" data-cp-overview-editable="schedule">
      <div class="cp-overview-value-row">
        <span class="cp-overview-value" data-cp-overview-schedule-value>{{ $scheduleDisplay }}</span>
        <button type="button"
                class="cp-overview-edit-btn"
                data-cp-overview-edit="schedule"
                aria-expanded="{{ $scheduleFormOpen ? 'true' : 'false' }}"
                aria-controls="cp-overview-schedule-form"
                aria-label="Edit schedule">
          <i class="fa fa-pencil" aria-hidden="true"></i>
        </button>
      </div>
      <form id="cp-overview-schedule-form"
            class="cp-overview-inline-form"
            action="{{ route('admin.client-projects.update', $project) }}"
            method="post"
            data-cp-overview-form="schedule"
            @unless ($scheduleFormOpen) hidden @endunless>
        @csrf
        @method('PUT')
        <input type="hidden" name="name" value="{{ $project->name }}">
        <input type="hidden" name="client_name" value="{{ $project->client_name }}">
        <input type="hidden" name="return_tab" value="overview">
        <div class="cp-overview-inline-fields">
          <div class="admin-form-group">
            <label for="cp-overview-start-date">Start date</label>
            <input class="admin-input"
                   type="date"
                   id="cp-overview-start-date"
                   name="start_date"
                   value="{{ $startDateVal }}">
            @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-overview-end-date">End date</label>
            <input class="admin-input"
                   type="date"
                   id="cp-overview-end-date"
                   name="end_date"
                   value="{{ $endDateVal }}">
            @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>
        <div class="cp-overview-inline-actions">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save schedule</button>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-overview-cancel="schedule">Cancel</button>
        </div>
      </form>
    </dd>
  </dl>

  @if ($project->notes)
    <h3 class="cp-overview-notes-heading">Notes</h3>
    <div class="cp-notes-box">{{ $project->notes }}</div>
  @endif
</div>
