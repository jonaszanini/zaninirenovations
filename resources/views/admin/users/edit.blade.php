@extends('admin.layouts.app')

@section('heading', 'Edit user')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.users.update', $user) }}" method="post">
      @csrf
      @method('PUT')
      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="usr-name">Name</label>
          <input class="admin-input" type="text" id="usr-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
          @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="usr-email">Email</label>
          <input class="admin-input" type="email" id="usr-email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="usr-role">Role</label>
        <select class="admin-input" id="usr-role" name="role" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
          <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin — full access, incl. deletes</option>
          <option value="staff" @selected(old('role', $user->role) === 'staff')>Staff — blocked from delete actions</option>
        </select>
        @if ($user->id === auth()->id())
          <input type="hidden" name="role" value="admin">
          <p class="admin-form-help">You cannot change your own role.</p>
        @endif
        @error('role') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="usr-password">New password</label>
          <input class="admin-input" type="password" id="usr-password" name="password" autocomplete="new-password">
          <p class="admin-form-help">Leave blank to keep the current password.</p>
          @error('password') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="usr-password-confirm">Confirm new password</label>
          <input class="admin-input" type="password" id="usr-password-confirm" name="password_confirmation" autocomplete="new-password">
        </div>
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>Save changes</button>
        <a href="{{ route('admin.users.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
