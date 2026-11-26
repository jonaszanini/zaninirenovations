@extends('admin.layouts.app')

@section('heading', 'Edit project')

@section('content')
  @php
    $clientFieldsLocked = $project->isCompleted();
    $cpEditInvoiceBillingOpen = filled(trim((string) old('invoice_billing_name', $project->invoice_billing_name)))
      || filled(trim((string) old('invoice_billing_address', $project->invoice_billing_address)))
      || $errors->has('invoice_billing_name')
      || $errors->has('invoice_billing_address');
    $billingType = old('billing_type', $project->billing_type ?? \App\Models\ClientProject::BILLING_FIXED);

    $cpEditVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
    $cpEditVisitTimeVal = old('visit_time', $project->visitTimeForInput());
    $cpEditVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
      || (bool) old('visit_done', $project->visit_done)
      || filled($cpEditVisitDateVal)
      || $errors->has('visit_date')
      || $errors->has('visit_time');

    $cpEditStartDateVal = old('start_date', optional($project->start_date)->toDateString());
    $cpEditEndDateVal = old('end_date', optional($project->end_date)->toDateString());
    $cpEditScheduleChecked = filled($cpEditStartDateVal) || filled($cpEditEndDateVal)
      || $errors->has('start_date')
      || $errors->has('end_date');
    $cpEditScheduleExpandOpen = $cpEditScheduleChecked;
    $photoCount = $project->photos->count();
  @endphp

  <div class="cp-edit-page">
    <div class="cp-edit-project-layout cp-edit-page__grid">
      <div class="cp-edit-page__main">
        <div class="admin-card admin-card--narrow cp-edit-project-form-card">
          <form id="cp-edit-project-form" action="{{ route('admin.client-projects.update', $project) }}" method="post">
            @csrf
            @method('PUT')

            {{-- PROJECT SECTION ------------------------------------------------ --}}
            <div class="cp-edit-section cp-edit-section--first">
              <h3 class="cp-card-head cp-edit-section__head">
                <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-briefcase"></i></span>
                <span>Project</span>
              </h3>

              <div class="cp-edit-grid-project">
                <div class="admin-form-group cp-edit-col-name">
                  <label for="cp-name">Project name <span class="cp-required">*</span></label>
                  <input type="text"
                         id="cp-name"
                         name="name"
                         value="{{ old('name', $project->name) }}"
                         required
                         maxlength="255"
                         @class(['admin-input', 'is-invalid' => $errors->has('name')])>
                  @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-form-group cp-edit-col-short">
                  <span class="admin-label-with-tip">
                    <label for="cp-status">Status</label>
                    <x-admin.info-tip label="Project status">Pending Customer means this project is waiting on the client.</x-admin.info-tip>
                  </span>
                  <select id="cp-status" name="status" class="admin-input" @disabled($project->isClosed())>
                    @foreach (\App\Models\ClientProject::statusLabels() as $statusKey => $statusLabel)
                      <option value="{{ $statusKey }}" @selected(old('status', $project->status) === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                  </select>
                  @error('status') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-form-group cp-edit-col-short">
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

                <div class="admin-form-group cp-edit-col-short">
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
            </div>

            {{-- CLIENT INFORMATION SECTION ------------------------------------- --}}
            <div class="cp-edit-section">
              <h3 class="cp-card-head cp-edit-section__head">
                <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-user"></i></span>
                <span>Client information</span>
              </h3>

              <div class="cp-edit-directory-bar">
                @unless ($clientFieldsLocked)
                  @include('admin.client_projects._client_directory_picker', [
                    'clientsSearchUrl' => $clientsSearchUrl,
                    'initialClientForPicker' => $initialClientForPicker ?? null,
                  ])
                @endunless

                @include('admin.client_projects._client_directory_sync', ['project' => $project, 'clientFieldsLocked' => $clientFieldsLocked])
              </div>

              <div class="cp-edit-grid-client">
                <div class="admin-form-group cp-edit-span-5">
                  <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
                  <input type="text"
                         id="cp-client-name"
                         name="client_name"
                         value="{{ old('client_name', $project->client_name) }}"
                         maxlength="255"
                         data-cp-client-name
                         @class(['admin-input', 'is-invalid' => $errors->has('client_name')])
                         @disabled($clientFieldsLocked)>
                  @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-form-group cp-edit-span-3">
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

                <div class="admin-form-group cp-edit-span-4">
                  <label for="cp-client-email">Email</label>
                  <input type="email"
                         id="cp-client-email"
                         name="client_email"
                         value="{{ old('client_email', $project->client_email) }}"
                         maxlength="255"
                         @class(['admin-input', 'is-invalid' => $errors->has('client_email')])
                         @disabled($clientFieldsLocked)>
                  @error('client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>

                <div class="admin-form-group cp-edit-span-5">
                  <span class="admin-label-with-tip">
                    <label for="cp-client-email-secondary">Secondary email</label>
                    <x-admin.info-tip label="Secondary email">Optional — CC'd on every client email.</x-admin.info-tip>
                  </span>
                  <input type="email"
                         id="cp-client-email-secondary"
                         name="client_email_secondary"
                         value="{{ old('client_email_secondary', $project->client_email_secondary) }}"
                         maxlength="255"
                         placeholder="spouse@example.com"
                         @class(['admin-input', 'is-invalid' => $errors->has('client_email_secondary')])
                         @disabled($clientFieldsLocked)>
                  @error('client_email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
                </div>

                <div class="cp-edit-span-7 cp-edit-address-col">
                  @include('admin.partials.places_address_field', [
                    'inputId' => 'cp-client-address',
                    'addressName' => 'client_address',
                    'label' => 'Service address',
                    'help' => 'Address suggestions enabled — start typing and pick an address from the list so map coordinates are saved for travel estimates.',
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
                </div>
              </div>

              {{-- COLLAPSIBLE INVOICE BILLING ---------------------------------- --}}
              <details class="cp-edit-collapsible"
                       data-cp-invoice-billing-details
                       @if ($cpEditInvoiceBillingOpen) open @endif>
                <summary class="cp-edit-collapsible__summary">
                  <span class="cp-edit-collapsible__title-group">
                    <i class="fa fa-chevron-right cp-edit-collapsible__chevron" aria-hidden="true"></i>
                    <span>Invoice billing</span>
                    <span class="cp-edit-collapsible__tag">optional</span>
                    @if ($cpEditInvoiceBillingOpen)
                      <span class="cp-badge cp-edit-collapsible__badge">Set</span>
                    @endif
                  </span>
                  <span class="cp-edit-collapsible__tip" onclick="event.stopPropagation();">
                    @include('admin.client_projects._cp_field_hint', [
                      'tipText' => 'When the payer is a company or a different legal entity, enter the name and address that should appear in the Bill to block on invoices and receipts. Day-to-day contact (name, email, phone above) stays the same for emails and SMS.',
                      'tipLabel' => 'Invoice billing',
                    ])
                  </span>
                </summary>

                <div class="cp-edit-collapsible__body cp-edit-grid-invoice-billing">
                  <div class="admin-form-group cp-edit-span-5">
                    <label for="cp-invoice-billing-name">Legal billing name</label>
                    <input type="text"
                           id="cp-invoice-billing-name"
                           name="invoice_billing_name"
                           value="{{ old('invoice_billing_name', $project->invoice_billing_name) }}"
                           maxlength="255"
                           placeholder="e.g. ABC Renovations Inc."
                           @class(['admin-input', 'is-invalid' => $errors->has('invoice_billing_name')])
                           @disabled($clientFieldsLocked)>
                    @error('invoice_billing_name') <p class="admin-form-error">{{ $message }}</p> @enderror
                  </div>
                  <div class="admin-form-group cp-edit-span-7">
                    <span class="admin-label-with-tip">
                      <label for="cp-invoice-billing-address">Billing address</label>
                      <x-admin.info-tip label="Billing address">Leave blank to use the client address above on PDFs.</x-admin.info-tip>
                    </span>
                    <textarea id="cp-invoice-billing-address"
                              name="invoice_billing_address"
                              rows="2"
                              maxlength="2000"
                              class="admin-input"
                              placeholder="Registered office or mailing address for AP"
                              @disabled($clientFieldsLocked)>{{ old('invoice_billing_address', $project->invoice_billing_address) }}</textarea>
                    @error('invoice_billing_address') <p class="admin-form-error">{{ $message }}</p> @enderror
                  </div>
                </div>
              </details>
            </div>

            {{-- VISIT & SCHEDULE SECTION --------------------------------------- --}}
            <div class="cp-edit-section">
              <div class="cp-edit-grid-schedule">
                <div class="admin-form-section cp-visit-section cp-edit-subcard">
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

                <div class="admin-form-section cp-visit-section cp-edit-subcard">
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
              </div>
            </div>

            {{-- BILLING & NOTES SECTION ---------------------------------------- --}}
            <div class="cp-edit-section">
              <div class="cp-edit-grid-billing-notes">
                <div class="cp-edit-billing-col">
                  <h3 class="cp-card-head cp-edit-section__head">
                    <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-credit-card"></i></span>
                    <span>Billing</span>
                  </h3>
                  <div class="admin-form-group cp-billing-choice">
                    <label class="cp-billing-option">
                      <input type="radio"
                             name="billing_type"
                             value="{{ \App\Models\ClientProject::BILLING_FIXED }}"
                             data-cp-billing
                             @checked($billingType === \App\Models\ClientProject::BILLING_FIXED)>
                      <strong>Fixed price</strong>
                    </label>
                    <label class="cp-billing-option">
                      <input type="radio"
                             name="billing_type"
                             value="{{ \App\Models\ClientProject::BILLING_HOURLY }}"
                             data-cp-billing
                             @checked($billingType === \App\Models\ClientProject::BILLING_HOURLY)>
                      <strong>Hourly rate</strong>
                    </label>
                  </div>
                  @error('billing_type') <p class="admin-form-error">{{ $message }}</p> @enderror

                  <div class="admin-form-group cp-hourly-rate-group"
                       data-cp-hourly-rate
                       style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;' }}">
                    <label for="cp-hourly-rate">Hourly rate (CAD / hour) <span class="cp-required">*</span></label>
                    <div class="cp-cad-field">
                      <span class="cp-cad-field__prefix" aria-hidden="true">CA$</span>
                      <input type="text"
                             id="cp-hourly-rate"
                             name="hourly_rate"
                             class="admin-input cp-money-input"
                             inputmode="decimal"
                             autocomplete="off"
                             placeholder="0.00"
                             value="{{ old('hourly_rate', $project->hourly_rate ? number_format((float) $project->hourly_rate, 2, '.', '') : '') }}"
                             data-cp-money-entry="direct">
                    </div>
                    @error('hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
                  </div>
                </div>

                <div class="cp-edit-notes-col">
                  <h3 class="cp-card-head cp-edit-section__head">
                    <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-sticky-note-o"></i></span>
                    <span>Notes</span>
                  </h3>
                  <div class="admin-form-group cp-edit-notes-group">
                    <label for="cp-notes">Internal notes</label>
                    <textarea class="admin-input"
                              id="cp-notes"
                              name="notes"
                              rows="3"
                              maxlength="5000"
                              placeholder="Site notes, access codes, client preferences…">{{ old('notes', $project->notes) }}</textarea>
                    @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
                  </div>
                </div>
              </div>
            </div>

            <div class="admin-form-actions cp-edit-form-actions">
              <button type="submit" id="cp-btn-save" class="admin-btn admin-btn--primary" data-hotkey-save>
                <i class="fa fa-check" aria-hidden="true"></i> Save changes
              </button>
              <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost">Cancel</a>
            </div>
          </form>
        </div>

        <div class="admin-card cp-danger-card cp-edit-danger-card">
          <div class="cp-edit-danger-card__row">
            <h3 class="cp-danger-title cp-card-head">
              <span class="cp-card-head__icon cp-card-head__icon--danger" aria-hidden="true"><i class="fa fa-exclamation-triangle"></i></span>
              <span>Danger zone</span>
              <x-admin.info-tip label="Danger zone">Deleting a project permanently removes its history, financials, and time entries.</x-admin.info-tip>
            </h3>
            <form action="{{ route('admin.client-projects.destroy', $project) }}" method="post"
                  onsubmit="return confirm('Delete this project and all of its history, financials, and time entries? This cannot be undone.');">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                <i class="fa fa-trash-o" aria-hidden="true"></i> Delete project
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="cp-edit-page__side">
        <aside class="cp-edit-project-actions-aside cp-edit-actions-bar" role="region" aria-label="Project form actions">
          <button type="submit" id="cp-btn-save-aside" form="cp-edit-project-form" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>
            <i class="fa fa-check" aria-hidden="true"></i> Save changes
          </button>
          <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
        </aside>

        <div class="cp-edit-project-travel-wrap">
          @include('admin.client_projects._travel_cost', ['project' => $project])
        </div>

        <div class="admin-card admin-card--narrow cp-edit-project-photos-card">
          <h3 class="cp-card-head">
            <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-paperclip"></i></span>
            <span>Photos &amp; files</span>
          </h3>
          @include('admin.client_projects._project_photos_gallery', [
            'project' => $project,
            'galleryId' => 'cp-edit-project-photos',
            'editable' => true,
            'showHeading' => false,
          ])

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
                  <i class="fa fa-upload" aria-hidden="true"></i> Upload files
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
      </div>
    </div>
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
  <script src="{{ asset('js/admin-vehicles.js') }}?v={{ @filemtime(public_path('js/admin-vehicles.js')) ?: time() }}" defer></script>
@endpush
