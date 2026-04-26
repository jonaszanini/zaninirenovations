@extends('admin.layouts.app')

@section('heading', 'Edit project')

@section('content')
  <div class="admin-card admin-card--narrow">
    <form id="cp-edit-project-form" action="{{ route('admin.client-projects.update', $project) }}" method="post">
      @csrf
      @method('PUT')

      <div class="admin-form-group">
        <label for="cp-name">Project name <span class="cp-required">*</span></label>
        <input type="text" id="cp-name" name="name" value="{{ old('name', $project->name) }}" required maxlength="255"
               @class(['is-invalid' => $errors->has('name')])>
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-section">
        <h3>Client information</h3>

        <div class="admin-form-group">
          <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
          <input type="text" id="cp-client-name" name="client_name" value="{{ old('client_name', $project->client_name) }}" required maxlength="255"
                 @class(['is-invalid' => $errors->has('client_name')])>
          @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-client-email">Email</label>
            <input type="email" id="cp-client-email" name="client_email" value="{{ old('client_email', $project->client_email) }}" maxlength="255"
                   @class(['is-invalid' => $errors->has('client_email')])>
            @error('client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-client-phone">Phone number</label>
            <input type="text"
                   id="cp-client-phone"
                   name="client_phone"
                   @class(['cp-phone-input', 'is-invalid' => $errors->has('client_phone')])
                   inputmode="tel"
                   autocomplete="tel"
                   maxlength="14"
                   placeholder="(xxx) xxx-xxxx"
                   value="{{ \App\Models\ClientProject::formatPhone(old('client_phone', $project->client_phone)) }}">
            @error('client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="admin-form-group">
          <label for="cp-client-email-secondary">Secondary email <small class="admin-form-help cp-inline-help">(optional — CC'd on every client email)</small></label>
          <input type="email" id="cp-client-email-secondary" name="client_email_secondary"
                 value="{{ old('client_email_secondary', $project->client_email_secondary) }}" maxlength="255"
                 placeholder="spouse@example.com"
                 @class(['is-invalid' => $errors->has('client_email_secondary')])>
          @error('client_email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-group">
          <label for="cp-client-address">Address</label>
          <input type="text" id="cp-client-address" name="client_address" value="{{ old('client_address', $project->client_address) }}" maxlength="500"
                 @class(['is-invalid' => $errors->has('client_address')])>
          @error('client_address') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Visit date</h3>
        <p class="admin-form-help">On-site visit before quoting. Enable only when the client has asked for a visit.</p>
        <input type="hidden" name="visit_required" value="0">
        <div class="admin-form-group">
          <label class="cp-checkbox-label">
            <input type="checkbox" name="visit_required" value="1" id="cp-edit-visit-required"
              @checked(old('visit_required', $project->visit_required))>
            <span>Visit required</span>
          </label>
        </div>
        <div class="admin-form-group cp-visit-date">
          <label for="cp-edit-visit-date">Visit date</label>
          <input type="date" id="cp-edit-visit-date" name="visit_date"
            value="{{ old('visit_date', optional($project->visit_date)->toDateString()) }}">
          @error('visit_date') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Schedule <span class="admin-form-help cp-inline-help" title="Optional. Used to show the project on the Project Calendar page and to send an 'Add to Google Calendar' email.">?</span></h3>
        <div id="cp-edit-date-warning" class="admin-flash admin-flash--warning" style="display:none;margin-bottom:14px;" role="alert"></div>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start-date">Start date</label>
            <input type="date" id="cp-start-date" name="start_date" value="{{ old('start_date', optional($project->start_date)->toDateString()) }}">
            @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-end-date">End date</label>
            <input type="date" id="cp-end-date" name="end_date" value="{{ old('end_date', optional($project->end_date)->toDateString()) }}">
            @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">Same-day start &amp; end is allowed for small projects.</p>
          </div>
        </div>
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
            <span>
              <strong>Fixed price</strong>
              <small>One total amount, paid in 1–4 installments.</small>
            </span>
          </label>
          <label class="cp-billing-option">
            <input type="radio" name="billing_type" value="{{ \App\Models\ClientProject::BILLING_HOURLY }}"
                   data-cp-billing
                   @checked($billingType === \App\Models\ClientProject::BILLING_HOURLY)>
            <span>
              <strong>Hourly rate</strong>
              <small>Set a rate per hour — earnings = rate × logged hours.</small>
            </span>
          </label>
        </div>
        @error('billing_type') <p class="admin-form-error">{{ $message }}</p> @enderror

        <div class="admin-form-group cp-hourly-rate-group" data-cp-hourly-rate
             style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;' }}">
          <label for="cp-hourly-rate">Hourly rate (CAD / hour) <span class="cp-required">*</span></label>
          <input type="text" id="cp-hourly-rate" name="hourly_rate"
                 class="cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 placeholder="0.00"
                 value="{{ old('hourly_rate', $project->hourly_rate ? number_format((float) $project->hourly_rate, 2, '.', '') : '') }}">
          @error('hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cp-notes">Notes</label>
        <textarea id="cp-notes" name="notes" rows="4" maxlength="5000">{{ old('notes', $project->notes) }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary"><i class="fa fa-check"></i> Save changes</button>
        <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
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
      var start = document.getElementById('cp-start-date');
      var end = document.getElementById('cp-end-date');
      var warn = document.getElementById('cp-edit-date-warning');
      var visitCb = document.getElementById('cp-edit-visit-required');
      var visitDate = document.getElementById('cp-edit-visit-date');

      function syncEditVisitDate() {
        if (!visitCb || !visitDate) return;
        visitDate.disabled = !visitCb.checked;
      }

      function checkEditDates() {
        if (!start || !end || !warn) return true;
        if (!start.value || !end.value) {
          warn.style.display = 'none';
          warn.textContent = '';
          return true;
        }
        if (start.value > end.value) {
          warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
          warn.style.display = 'block';
          return false;
        }
        warn.style.display = 'none';
        warn.textContent = '';
        return true;
      }

      if (start && end && warn) {
        start.addEventListener('change', checkEditDates);
        end.addEventListener('change', checkEditDates);
      }
      if (visitCb && visitDate) {
        visitCb.addEventListener('change', syncEditVisitDate);
        syncEditVisitDate();
      }
      form.addEventListener('submit', function (e) {
        if (visitCb && visitDate && !visitCb.checked) {
          visitDate.disabled = false;
          visitDate.value = '';
        }
        if (!checkEditDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
@endsection
