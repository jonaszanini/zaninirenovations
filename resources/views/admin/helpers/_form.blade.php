@php
  $helper = $helper ?? null;
@endphp

<div class="admin-form-group">
  <label for="name">Name</label>
  <input type="text" id="name" name="name" value="{{ old('name', $helper?->name) }}" required maxlength="160">
  @error('name') <div class="admin-error">{{ $message }}</div> @enderror
</div>
<div class="admin-form-group">
  <label for="email">Email</label>
  <input type="email" id="email" name="email" value="{{ old('email', $helper?->email) }}" required maxlength="255">
  @error('email') <div class="admin-error">{{ $message }}</div> @enderror
</div>
<div class="admin-form-group">
  <label for="phone">Phone (optional)</label>
  <input type="text" id="phone" name="phone" value="{{ old('phone', $helper?->phone) }}" maxlength="20">
  @error('phone') <div class="admin-error">{{ $message }}</div> @enderror
</div>
<div class="admin-form-group">
  <label for="hourly_rate">Hourly rate ($)</label>
  <input type="number" id="hourly_rate" name="hourly_rate" value="{{ old('hourly_rate', $helper?->hourly_rate ?? '0.00') }}" required min="0" step="0.01">
  @error('hourly_rate') <div class="admin-error">{{ $message }}</div> @enderror
</div>
<div class="admin-form-group">
  <label class="cp-helper-active-toggle">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $helper?->is_active ?? true))>
    Active (can be assigned to projects)
  </label>
</div>
