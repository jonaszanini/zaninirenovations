@php
  $helper = $helper ?? null;
@endphp

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="name">Name</label>
    <input class="admin-input" type="text" id="name" name="name" value="{{ old('name', $helper?->name) }}" required maxlength="160">
    @error('name') <div class="admin-form-error">{{ $message }}</div> @enderror
  </div>
  <div class="admin-form-group">
    <label for="email">Email</label>
    <input class="admin-input" type="email" id="email" name="email" value="{{ old('email', $helper?->email) }}" required maxlength="255">
    @error('email') <div class="admin-form-error">{{ $message }}</div> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="phone">Phone (optional)</label>
    <input class="admin-input cp-phone-input" type="text" id="phone" name="phone" value="{{ old('phone', $helper?->phone) }}" inputmode="tel" autocomplete="tel" maxlength="20">
    @error('phone') <div class="admin-form-error">{{ $message }}</div> @enderror
  </div>
  <div class="admin-form-group">
    <label for="hourly_rate">Hourly rate (CAD / hour)</label>
    <input type="text"
           id="hourly_rate"
           name="hourly_rate"
           class="admin-input cp-money-input"
           inputmode="decimal"
           autocomplete="off"
           placeholder="0.00"
           required
           value="{{ old('hourly_rate', $helper && $helper->hourly_rate !== null ? number_format((float) $helper->hourly_rate, 2, '.', '') : '') }}" data-cp-money-entry="direct">
    @error('hourly_rate') <div class="admin-form-error">{{ $message }}</div> @enderror
  </div>
</div>
<div class="admin-form-group">
  <input type="hidden" name="is_active" value="0">
  <label class="cp-visit-toggle cp-helper-active-toggle" for="helper-is-active">
    <input type="checkbox"
           name="is_active"
           value="1"
           id="helper-is-active"
           @checked(old('is_active', $helper?->is_active ?? true))>
    <span class="cp-visit-toggle__control" aria-hidden="true"></span>
    <span class="cp-visit-toggle__label">Active (can be assigned to projects)</span>
  </label>
  @error('is_active') <div class="admin-form-error">{{ $message }}</p> @enderror
</div>
