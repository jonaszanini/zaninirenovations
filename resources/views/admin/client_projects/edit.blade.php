@extends('admin.layouts.app')

@section('heading', 'Edit project')

@push('head')
<style>
/* Edit project: two-column layout on desktop */
.cp-edit-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 16px;
}
.cp-edit-form-grid > .admin-form-group { margin-bottom: 14px; }
.cp-edit-form-grid > .cp-edit-form-full { grid-column: 1 / -1; }
@media (max-width: 700px) {
  .cp-edit-form-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
  <div class="cp-edit-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
      <form id="cp-edit-project-form" action="{{ route('admin.client-projects.update', $project) }}" method="post">
        @csrf
        @method('PUT')

      <div class="cp-edit-form-grid">
        <div class="admin-form-group">
          <label for="cp-name">Project name <span class="cp-required">*</span></label>
          <input class="admin-input" type="text" id="cp-name" name="name" value="{{ old('name', $project->name) }}" required maxlength="255"
                 @class(['admin-input', 'is-invalid' => $errors->has('name')])>
          @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-group">
          <label for="cp-contacted-via">Contact via <span class="cp-required">*</span></label>
          <select id="cp-contacted-via" name="contacted_via" required @class(['admin-input', 'is-invalid' => $errors->has('contacted_via')])>
            @if (old('contacted_via', $project->contacted_via) === null || old('contacted_via', $project->contacted_via) === '')
              <option value="" disabled selected>Select…</option>
            @endif
            @foreach (\App\Models\ClientProject::contactedViaOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('contacted_via', $project->contacted_via) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('contacted_via') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-group">
          <span class="admin-label-with-tip cp-label-with-hint">
            <label for="cp-conversation-on">Conversation on</label>
            @include('admin.client_projects._cp_field_hint', [
              'tipText' => 'If the thread moved (e.g. they emailed first but you now chat on SMS or WhatsApp), pick where you usually follow up.',
              'tipLabel' => 'Conversation on',
            ])
          </span>
          <select id="cp-conversation-on" name="conversation_on" @class(['admin-input', 'is-invalid' => $errors->has('conversation_on')])>
            <option value="" @selected(old('conversation_on', $project->conversation_on) === null || old('conversation_on', $project->conversation_on) === '')>— Not set —</option>
            @foreach (\App\Models\ClientProject::contactedViaOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('conversation_on', $project->conversation_on) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('conversation_on') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Client information</h3>

        @php $clientFieldsLocked = $project->isCompleted(); @endphp

        @unless ($clientFieldsLocked)
          @include('admin.client_projects._client_directory_picker', [
            'clientsSearchUrl' => $clientsSearchUrl,
            'initialClientForPicker' => $initialClientForPicker ?? null,
          ])
        @endunless

        @include('admin.client_projects._client_directory_sync', ['project' => $project, 'clientFieldsLocked' => $clientFieldsLocked])

        <div class="cp-edit-form-grid">
          <div class="admin-form-group">
            <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
            <input class="admin-input" type="text" id="cp-client-name" name="client_name" value="{{ old('client_name', $project->client_name) }}" maxlength="255"
                   data-cp-client-name
                   @class(['admin-input', 'is-invalid' => $errors->has('client_name')]) @disabled($clientFieldsLocked)>
            @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <span class="admin-label-with-tip">
              <label for="cp-client-email-secondary">Secondary email</label>
              <x-admin.info-tip label="Secondary email">Optional — CC'd on every client email.</x-admin.info-tip>
            </span>
            <input class="admin-input" type="email" id="cp-client-email-secondary" name="client_email_secondary"
                   value="{{ old('client_email_secondary', $project->client_email_secondary) }}" maxlength="255"
                   placeholder="spouse@example.com"
                   @class(['admin-input', 'is-invalid' => $errors->has('client_email_secondary')]) @disabled($clientFieldsLocked)>
            @error('client_email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-client-email">Email</label>
            <input class="admin-input" type="email" id="cp-client-email" name="client_email" value="{{ old('client_email', $project->client_email) }}" maxlength="255"
                   @class(['admin-input', 'is-invalid' => $errors->has('client_email')]) @disabled($clientFieldsLocked)>
            @error('client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-client-phone">Phone number</label>
            <input type="text"
                   id="cp-client-phone"
                   name="client_phone"
                   @class(['admin-input', 'cp-phone-input', 'is-invalid' => $errors->has('client_phone')])
                   inputmode="tel"
                   autocomplete="tel"
                   maxlength="14"
                   placeholder="(xxx) xxx-xxxx"
                   value="{{ \App\Models\ClientProject::formatPhone(old('client_phone', $project->client_phone)) }}"
                   @disabled($clientFieldsLocked)>
            @error('client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        @include('admin.partials.places_address_field', [
          'inputId' => 'cp-client-address',
          'addressName' => 'client_address',
          'label' => 'Service address',
          'value' => old('client_address', $project->client_address),
          'placeIdName' => 'client_address_place_id',
          'placeIdValue' => old('client_address_place_id', $project->client_address_place_id),
          'latName' => 'client_address_latitude',
          'latValue' => old('client_address_latitude', $project->client_address_latitude),
          'lngName' => 'client_address_longitude',
          'lngValue' => old('client_address_longitude', $project->client_address_longitude),
          'directoryHintId' => 'cp-client-address-directory-hint',
          'disabled' => $clientFieldsLocked,
        ])

        <div class="admin-form-section" style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #e5e7eb;">
          <h3 style="font-size:1rem;" class="cp-label-with-hint">
            Invoice billing <span class="admin-form-help cp-inline-help">(optional)</span>
            @include('admin.client_projects._cp_field_hint', [
              'tipText' => 'When the payer is a company or a different legal entity, enter the name and address that should appear in the Bill to block on invoices and receipts. Day-to-day contact (name, email, phone above) stays the same for emails and SMS.',
              'tipLabel' => 'Invoice billing',
            ])
          </h3>
          <div class="admin-form-group">
            <label for="cp-invoice-billing-name">Legal billing name</label>
            <input class="admin-input" type="text" id="cp-invoice-billing-name" name="invoice_billing_name"
                   value="{{ old('invoice_billing_name', $project->invoice_billing_name) }}" maxlength="255"
                     placeholder="e.g. ABC Renovations Inc."
                     @class(['admin-input', 'is-invalid' => $errors->has('invoice_billing_name')]) @disabled($clientFieldsLocked)>
            @error('invoice_billing_name') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <span class="admin-label-with-tip">
              <label for="cp-invoice-billing-address">Billing address</label>
              <x-admin.info-tip label="Billing address">Leave blank to use the client address above on PDFs.</x-admin.info-tip>
            </span>
              <textarea id="cp-invoice-billing-address" name="invoice_billing_address" rows="3" maxlength="2000"
                        class="admin-input" placeholder="Registered office or mailing address for AP"
                        @disabled($clientFieldsLocked)>{{ old('invoice_billing_address', $project->invoice_billing_address) }}</textarea>
            @error('invoice_billing_address') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>
      </div>

      @php
        $cpEditVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
        $cpEditVisitTimeVal = old('visit_time', $project->visitTimeForInput());
        $cpEditVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
          || (bool) old('visit_done', $project->visit_done)
          || filled($cpEditVisitDateVal)
          || $errors->has('visit_date')
          || $errors->has('visit_time');
      @endphp
      <div class="admin-form-section cp-visit-section">
        @include('admin.client_projects._visit_required_toggle', [
          'visitRequiredId' => 'cp-edit-visit-required',
          'visitRequiredChecked' => old('visit_required', $project->visit_required),
          'expandableId' => 'cp-edit-visit-expandable',
          'visitDateId' => 'cp-edit-visit-date',
          'visitDateValue' => $cpEditVisitDateVal,
          'visitTimeId' => 'cp-edit-visit-time',
          'visitTimeValue' => $cpEditVisitTimeVal,
          'expandOpen' => $cpEditVisitExpandOpen,
          'showVisitDone' => true,
          'visitDoneId' => 'cp-edit-visit-done',
          'visitDoneChecked' => old('visit_done', $project->visit_done),
        ])
      </div>

      @php
        $cpEditStartDateVal = old('start_date', optional($project->start_date)->toDateString());
        $cpEditEndDateVal = old('end_date', optional($project->end_date)->toDateString());
        $cpEditScheduleChecked = filled($cpEditStartDateVal) || filled($cpEditEndDateVal)
          || $errors->has('start_date')
          || $errors->has('end_date');
        $cpEditScheduleExpandOpen = $cpEditScheduleChecked;
      @endphp
      <div class="admin-form-section cp-visit-section">
        @include('admin.client_projects._schedule_toggle', [
          'scheduleToggleId' => 'cp-edit-schedule-enabled',
          'scheduleChecked' => $cpEditScheduleChecked,
          'expandableId' => 'cp-edit-schedule-expandable',
          'warningId' => 'cp-edit-date-warning',
          'startDateId' => 'cp-start-date',
          'endDateId' => 'cp-end-date',
          'startDateValue' => $cpEditStartDateVal,
          'endDateValue' => $cpEditEndDateVal,
          'expandOpen' => $cpEditScheduleExpandOpen,
          'excludeProjectId' => $project->id,
        ])
      </div>

      <div class="admin-form-section">
        <h3>Billing</h3>
        @php
          $billingType = old('billing_type', $project->billing_type ?? \App\Models\ClientProject::BILLING_FIXED);
        @endphp
        <div class="admin-form-group cp-billing-choice">
          <label class="cp-billing-option">
            <input type="radio" name="billing_type" value="{{ \App\Models\ClientProject::BILLING_FIXED }}"
                   data-cp-billing
                   @checked($billingType === \App\Models\ClientProject::BILLING_FIXED)>
            <strong>Fixed price</strong>
          </label>
          <label class="cp-billing-option">
            <input type="radio" name="billing_type" value="{{ \App\Models\ClientProject::BILLING_HOURLY }}"
                   data-cp-billing
                   @checked($billingType === \App\Models\ClientProject::BILLING_HOURLY)>
            <strong>Hourly rate</strong>
          </label>
        </div>
        @error('billing_type') <p class="admin-form-error">{{ $message }}</p> @enderror

        <div class="admin-form-group cp-hourly-rate-group" data-cp-hourly-rate
             style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;' }}">
          <label for="cp-hourly-rate">Hourly rate (CAD / hour) <span class="cp-required">*</span></label>
          <input type="text" id="cp-hourly-rate" name="hourly_rate"
                 class="admin-input cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                
                 placeholder="0.00"
                 value="{{ old('hourly_rate', $project->hourly_rate ? number_format((float) $project->hourly_rate, 2, '.', '') : '') }}" data-cp-money-entry="direct">
          @error('hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cp-notes">Notes</label>
        <textarea class="admin-input" id="cp-notes" name="notes" rows="4" maxlength="5000">{{ old('notes', $project->notes) }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-actions">
        <button type="submit" id="cp-btn-save" class="admin-btn admin-btn--primary" data-hotkey-save><i class="fa fa-check"></i> Save changes</button>
        <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
    </div>

    <aside class="cp-edit-project-actions-aside" role="region" aria-label="Project form actions">
      <button type="submit" id="cp-btn-save-aside" form="cp-edit-project-form" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>
        <i class="fa fa-check"></i> Save changes
      </button>
      <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
    </aside>
  </div>

  <div class="admin-card admin-card--narrow cp-edit-project-photos-card">
    <h3>Photos &amp; files</h3>
    @include('admin.client_projects._project_photos_gallery', [
      'project' => $project,
      'galleryId' => 'cp-edit-project-photos',
      'editable' => true,
      'showHeading' => false,
    ])

    @php $photoCount = $project->photos->count(); @endphp
    @if ($photoCount < \App\Services\ClientProjectPhotoStorage::MAX_PHOTOS_PER_PROJECT)
      <form action="{{ route('admin.client-projects.photos.store', $project) }}" method="post" enctype="multipart/form-data" class="cp-edit-project-photos-form">
        @csrf
        @include('admin.client_projects._project_photos_upload', [
          'inputId' => 'cp-edit-project-photos-input',
          'previewId' => 'cp-edit-project-photos-preview',
          'existingCount' => $photoCount,
        ])
        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-upload"></i> Upload files
          </button>
        </div>
      </form>
    @endif

    @include('admin.client_projects._project_document_links_form', [
      'project' => $project,
      'standalone' => true,
      'rootId' => 'cp-edit-document-links',
    ])
  </div>

  <div class="admin-card cp-danger-card">
    <h3 class="cp-danger-title">Danger zone</h3>
    <p class="admin-form-help">Deleting a project permanently removes its history, financials, and time entries.</p>
    <form action="{{ route('admin.client-projects.destroy', $project) }}" method="post"
          onsubmit="return confirm('Delete this project and all of its history, financials, and time entries? This cannot be undone.');">
      @csrf
      @method('DELETE')
      <button type="submit" class="admin-btn admin-btn--danger">Delete project</button>
    </form>
  </div>
  <script>
    (function () {
      var form = document.getElementById('cp-edit-project-form');
      if (!form) return;
      var visitCb = document.getElementById('cp-edit-visit-required');
      var visitDate = document.getElementById('cp-edit-visit-date');
      var visitTime = document.getElementById('cp-edit-visit-time');
      var visitDoneCb = document.getElementById('cp-edit-visit-done');
      var visitExpand = document.getElementById('cp-edit-visit-expandable');

      function syncEditVisitExpandable() {
        if (!visitExpand) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        var open = required || done;
        visitExpand.classList.toggle('is-open', open);
        visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function syncEditVisitFields() {
        if (!visitDate) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        if (!required && !done) {
          visitDate.value = '';
          if (visitTime) visitTime.value = '';
        }
        visitDate.readOnly = !!done;
        visitDate.disabled = !done && !required;
        visitDate.classList.toggle('cp-visit-date-input--locked', !!done || !required);
        if (visitTime) {
          visitTime.readOnly = !!done;
          visitTime.disabled = !done && !required;
          visitTime.classList.toggle('cp-visit-time-input--locked', !!done || !required);
        }
        syncEditVisitExpandable();
      }

      if (visitCb) visitCb.addEventListener('change', syncEditVisitFields);
      if (visitDoneCb) visitDoneCb.addEventListener('change', syncEditVisitFields);
      if (visitDate) visitDate.addEventListener('change', syncEditVisitExpandable);
      if (visitDate) visitDate.addEventListener('input', syncEditVisitExpandable);
      syncEditVisitFields();

      form.addEventListener('submit', function () {
        if (visitDate && visitCb && !visitCb.checked && !(visitDoneCb && visitDoneCb.checked)) {
          visitDate.disabled = false;
          visitDate.readOnly = false;
          visitDate.value = '';
          if (visitTime) {
            visitTime.disabled = false;
            visitTime.readOnly = false;
            visitTime.value = '';
          }
        }
      });
    })();
  </script>
@endsection

@push('scripts')
  @include('admin.partials.google_maps_scripts')
@endpush
