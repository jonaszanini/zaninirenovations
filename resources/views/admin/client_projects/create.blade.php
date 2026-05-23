@extends('admin.layouts.app')

@section('heading', 'New project')

@section('content')
  @php
    $billingType = old('billing_type', \App\Models\ClientProject::BILLING_FIXED);
    $sendClientEmail = old('send_client_email', '1') === '1';
    $cpCreateVisitRequired = (bool) old('visit_required');
    $cpCreateVisitDateVal = old('visit_date');
    $cpCreateHasVisitDate = filled($cpCreateVisitDateVal);
    $cpCreateVisitExpandOpen = $cpCreateVisitRequired || $errors->has('visit_date');
  @endphp

  <div class="cp-edit-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
      <form id="cp-create-project-form" action="{{ route('admin.client-projects.store') }}" method="post" enctype="multipart/form-data">
        @csrf

        <div class="admin-form-group">
          <label for="cp-name">Project name <span class="cp-required">*</span></label>
          <input type="text" id="cp-name" name="name" value="{{ old('name') }}" required maxlength="255"
                 @class(['is-invalid' => $errors->has('name')])>
          @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-group">
          <label for="cp-contacted-via">Contact via <span class="cp-required">*</span></label>
          <select id="cp-contacted-via" name="contacted_via" required @class(['admin-input', 'is-invalid' => $errors->has('contacted_via')])>
            <option value="" disabled @selected(old('contacted_via') === null)>Select…</option>
            @foreach (\App\Models\ClientProject::contactedViaOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('contacted_via') === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('contacted_via') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-group">
          <label for="cp-conversation-on">Conversation on</label>
          <select id="cp-conversation-on" name="conversation_on" @class(['admin-input', 'is-invalid' => $errors->has('conversation_on')])>
            <option value="" @selected(old('conversation_on') === null || old('conversation_on') === '')>— Not set —</option>
            @foreach (\App\Models\ClientProject::contactedViaOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('conversation_on') === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <p class="admin-form-help">If the thread moved (e.g. they emailed first but you now chat on SMS or WhatsApp), pick where you usually follow up.</p>
          @error('conversation_on') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-section">
          <h3>Client information</h3>

          @include('admin.client_projects._client_directory_picker', [
            'clientsSearchUrl' => $clientsSearchUrl,
            'initialClientForPicker' => null,
          ])

          <div class="admin-form-group">
            <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
            <input type="text" id="cp-client-name" name="client_name" value="{{ old('client_name') }}" required maxlength="255"
                   @class(['is-invalid' => $errors->has('client_name')])>
            @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>

          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="cp-client-email">Email</label>
              <input type="email" id="cp-client-email" name="client_email" value="{{ old('client_email') }}" maxlength="255"
                     @class(['is-invalid' => $errors->has('client_email')])>
              @error('client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
              <label class="admin-checkbox" for="cp-send-client-email" style="margin-top:8px;font-weight:400;">
                <input type="hidden" name="send_client_email" value="0">
                <input type="checkbox"
                       class="admin-checkbox__input"
                       id="cp-send-client-email"
                       name="send_client_email"
                       value="1"
                       @checked($sendClientEmail)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Send automated &ldquo;quote request received&rdquo; email to the client</span>
              </label>
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
                     value="{{ \App\Models\ClientProject::formatPhone(old('client_phone')) }}">
              @error('client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="admin-form-group">
            <label for="cp-client-email-secondary">Secondary email <small class="admin-form-help cp-inline-help">(optional — CC'd on every client email)</small></label>
            <input type="email" id="cp-client-email-secondary" name="client_email_secondary"
                   value="{{ old('client_email_secondary') }}" maxlength="255"
                   placeholder="spouse@example.com"
                   @class(['is-invalid' => $errors->has('client_email_secondary')])>
            @error('client_email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>

          <div class="admin-form-group">
            <label for="cp-client-address">Address</label>
            <input type="text" id="cp-client-address" name="client_address" value="{{ old('client_address') }}" maxlength="500"
                   @class(['is-invalid' => $errors->has('client_address')])>
            @error('client_address') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>

          <div class="admin-form-section" style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #e5e7eb;">
            <h3 style="font-size:1rem;">Invoice billing <span class="admin-form-help cp-inline-help">(optional)</span></h3>
            <p class="admin-form-help">When the payer is a company or a different legal entity, enter the name and address that should appear in the <strong>Bill to</strong> block on invoices and receipts. Day-to-day contact (name, email, phone above) stays the same for emails and SMS.</p>
            <div class="admin-form-group">
              <label for="cp-invoice-billing-name">Legal billing name</label>
              <input type="text" id="cp-invoice-billing-name" name="invoice_billing_name"
                     value="{{ old('invoice_billing_name') }}" maxlength="255"
                     placeholder="e.g. ABC Renovations Inc."
                     @class(['is-invalid' => $errors->has('invoice_billing_name')])>
              @error('invoice_billing_name') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="cp-invoice-billing-address">Billing address</label>
              <textarea id="cp-invoice-billing-address" name="invoice_billing_address" rows="3" maxlength="2000"
                        class="admin-input" placeholder="Registered office or mailing address for AP">{{ old('invoice_billing_address') }}</textarea>
              @error('invoice_billing_address') <p class="admin-form-error">{{ $message }}</p> @enderror
              <p class="admin-form-help">Leave blank to use the client address above on PDFs.</p>
            </div>
          </div>
        </div>

        <div class="admin-form-section cp-visit-section">
          @include('admin.client_projects._visit_required_toggle', [
            'visitRequiredId' => 'cp-create-visit-required',
            'visitRequiredChecked' => old('visit_required'),
            'expandableId' => 'cp-create-visit-expandable',
            'visitDateId' => 'cp-create-visit-date',
            'visitDateValue' => $cpCreateVisitDateVal,
            'expandOpen' => $cpCreateVisitExpandOpen,
            'slowExpand' => true,
          ])
        </div>

        <div class="admin-form-section">
          <h3>Schedule <span class="admin-form-help cp-inline-help" title="Optional. Used to show the project on the Project Calendar page and to send an 'Add to Google Calendar' email.">?</span></h3>
          <div id="cp-create-date-warning" class="admin-flash admin-flash--warning" style="display:none;margin-bottom:14px;" role="alert"></div>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="cp-start-date">Start date</label>
              <input type="date" id="cp-start-date" name="start_date" value="{{ old('start_date') }}">
              @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
            </div>
            <div class="admin-form-group">
              <label for="cp-end-date">End date</label>
              <input type="date" id="cp-end-date" name="end_date" value="{{ old('end_date') }}">
              @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
              <p class="admin-form-help">Same-day start &amp; end is allowed for small projects.</p>
            </div>
          </div>
        </div>

        <div class="admin-form-section">
          <h3>Billing</h3>
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
                   value="{{ old('hourly_rate') }}">
            @error('hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="admin-form-group">
          <label for="cp-notes">Notes</label>
          <textarea id="cp-notes" name="notes" rows="4" maxlength="5000">{{ old('notes') }}</textarea>
          @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-check"></i> Create project
          </button>
          <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
        </div>

        <p class="admin-form-help cp-form-footer" data-cp-fixed-quote-pending
           style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY || $cpCreateVisitRequired ? 'display:none;' : '' }}">
          Status will be set to <strong>Quote Pending</strong>. You'll receive a daily email reminder until the quote is sent.
        </p>
        <p class="admin-form-help cp-form-footer" data-cp-fixed-visit-required
           style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY || ! $cpCreateVisitRequired || $cpCreateHasVisitDate ? 'display:none;' : '' }}">
          Status will be set to <strong>Visit required</strong>. Set a visit date later to move the project to Visit scheduled.
        </p>
        <p class="admin-form-help cp-form-footer" data-cp-fixed-visit-scheduled
           style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY || ! $cpCreateVisitRequired || ! $cpCreateHasVisitDate ? 'display:none;' : '' }}">
          Status will be set to <strong>Visit scheduled</strong>. You'll receive a daily email reminder until the visit is done and the quote is sent.
        </p>
        <p class="admin-form-help cp-form-footer" data-cp-hourly-only
           style="{{ $billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;' }}">
          Status will be set to <strong>Proposal Accepted</strong>. No email is sent to the client yet &mdash; once
          you schedule the dates you'll be able to send a service confirmation from the project page.
        </p>
      </form>
    </div>

    <aside class="cp-edit-project-actions-aside" role="region" aria-label="Project form actions">
      <button type="submit" form="cp-create-project-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-check"></i> Create project
      </button>
      <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
    </aside>
  </div>

  <div class="admin-card admin-card--narrow cp-edit-project-photos-card">
    <h3>Photos &amp; files</h3>
    @include('admin.client_projects._project_photos_upload', [
      'inputId' => 'cp-create-project-photos',
      'previewId' => 'cp-create-project-photos-preview',
      'existingCount' => 0,
      'formId' => 'cp-create-project-form',
    ])
  </div>

  <script>
    (function () {
      var form = document.getElementById('cp-create-project-form');
      if (!form) return;
      var start = document.getElementById('cp-start-date');
      var end = document.getElementById('cp-end-date');
      var warn = document.getElementById('cp-create-date-warning');

      function checkCreateDates() {
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
        start.addEventListener('change', checkCreateDates);
        end.addEventListener('change', checkCreateDates);
      }

      form.addEventListener('submit', function (e) {
        var visitDate = document.getElementById('cp-create-visit-date');
        var visitCb = document.getElementById('cp-create-visit-required');
        if (visitDate && visitCb && !visitCb.checked) {
          visitDate.disabled = false;
          visitDate.value = '';
        }
        if (!checkCreateDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
@endsection
