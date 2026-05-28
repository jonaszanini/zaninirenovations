@extends('admin.layouts.app')

@section('heading', 'Edit client — '.$client->name)

@section('content')
  <div class="admin-card admin-card--narrow">
    <form action="{{ route('admin.clients.update', $client) }}" method="post">
      @csrf
      @method('PUT')

      <div class="admin-form-group">
        <label for="cl-name">Name <span class="cp-required">*</span></label>
        <input type="text" id="cl-name" name="name" value="{{ old('name', $client->name) }}" required maxlength="255"
               class="@error('name') is-invalid @enderror">
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cl-email">Email</label>
          <input type="email" id="cl-email" name="email" value="{{ old('email', $client->email) }}" maxlength="255"
                 class="@error('email') is-invalid @enderror">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-phone">Phone</label>
          <input type="text" id="cl-phone" name="phone"
                 value="{{ \App\Models\ClientProject::formatPhone(old('phone', $client->phone)) }}"
                 class="cp-phone-input @error('phone') is-invalid @enderror"
                 inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx">
          @error('phone') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cl-email-secondary">Secondary email</label>
        <input type="email" id="cl-email-secondary" name="email_secondary" value="{{ old('email_secondary', $client->email_secondary) }}" maxlength="255"
               class="@error('email_secondary') is-invalid @enderror">
        @error('email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="cl-address">Address</label>
        <input type="text" id="cl-address" name="address" value="{{ old('address', $client->address) }}" maxlength="500"
               class="@error('address') is-invalid @enderror">
        @error('address') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="cl-notes">Notes</label>
        <textarea id="cl-notes" name="notes" rows="3" maxlength="5000">{{ old('notes', $client->notes) }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <p class="admin-form-help">
        Linked to <strong>{{ $client->projects()->count() }}</strong> project(s).
        Edits here update the central directory; active projects can sync these details when saved (see the &ldquo;Update central client record&rdquo; option on each project).
        Completed projects keep a frozen snapshot from their completion date.
      </p>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save changes
        </button>
        <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>

    @if ($client->projects()->exists())
      <p class="admin-form-help" style="margin-top:24px;color:#b45309;">
        <i class="fa fa-info-circle"></i>
        Delete is disabled while this client is linked to projects. Remove the client link on each project first (edit project → clear directory selection and save).
      </p>
    @else
      <form action="{{ route('admin.clients.destroy', $client) }}" method="post" style="margin-top:20px;"
            onsubmit="return confirm('Delete this client permanently?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
          <i class="fa fa-trash"></i> Delete client
        </button>
      </form>
    @endif
  </div>
@endsection
