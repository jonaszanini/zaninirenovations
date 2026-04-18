@extends('admin.layouts.app')

@section('heading', 'New project')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.client-projects.store') }}" method="post">
      @csrf

      <div class="admin-form-group">
        <label for="cp-name">Project name <span class="cp-required">*</span></label>
        <input type="text" id="cp-name" name="name" value="{{ old('name') }}" required maxlength="255">
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-section">
        <h3>Client information</h3>

        <div class="admin-form-group">
          <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
          <input type="text" id="cp-client-name" name="client_name" value="{{ old('client_name') }}" required maxlength="255">
          @error('client_name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-client-email">Email</label>
            <input type="email" id="cp-client-email" name="client_email" value="{{ old('client_email') }}" maxlength="255">
            @error('client_email') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">If provided, the client gets an automated "quote request received" email.</p>
          </div>
          <div class="admin-form-group">
            <label for="cp-client-phone">Phone number</label>
            <input type="text" id="cp-client-phone" name="client_phone" value="{{ old('client_phone') }}" maxlength="50">
            @error('client_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>

        <div class="admin-form-group">
          <label for="cp-client-address">Address</label>
          <input type="text" id="cp-client-address" name="client_address" value="{{ old('client_address') }}" maxlength="500">
          @error('client_address') <p class="admin-form-error">{{ $message }}</p> @enderror
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

      <p class="admin-form-help cp-form-footer">
        Status will be set to <strong>Quote Pending</strong>. You'll receive a daily email reminder until the quote is sent.
      </p>
    </form>
  </div>
@endsection
