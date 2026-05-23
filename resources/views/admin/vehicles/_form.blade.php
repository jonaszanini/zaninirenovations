@php
  $vehicle = $vehicle ?? null;
  $isEdit = $vehicle !== null;
@endphp

<div class="admin-form-group">
  <label for="vehicle-name">Name <span class="cp-required">*</span></label>
  <input type="text" id="vehicle-name" name="name" value="{{ old('name', $vehicle?->name) }}" required maxlength="160" placeholder="e.g. White Ford F-150">
  @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
</div>

<div class="admin-form-grid-3">
  <div class="admin-form-group">
    <label for="vehicle-make">Make</label>
    <input type="text" id="vehicle-make" name="make" value="{{ old('make', $vehicle?->make) }}" maxlength="80" placeholder="Ford">
    @error('make') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="vehicle-model">Model</label>
    <input type="text" id="vehicle-model" name="model" value="{{ old('model', $vehicle?->model) }}" maxlength="80" placeholder="F-150">
    @error('model') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="vehicle-year">Year</label>
    <input type="number" id="vehicle-year" name="year" value="{{ old('year', $vehicle?->year) }}" min="1980" max="2100" placeholder="2020">
    @error('year') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="vehicle-plate">License plate</label>
    <input type="text" id="vehicle-plate" name="license_plate" value="{{ old('license_plate', $vehicle?->license_plate) }}" maxlength="32">
    @error('license_plate') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="vehicle-odometer">Odometer (km)</label>
    <input type="number" id="vehicle-odometer" name="odometer_reading" value="{{ old('odometer_reading', $vehicle?->odometer_reading) }}" min="0" step="1">
    @error('odometer_reading') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="vehicle-consumption">Fuel consumption (L/100 km)</label>
    <input type="number" id="vehicle-consumption" name="fuel_consumption_l100km" value="{{ old('fuel_consumption_l100km', $vehicle?->fuel_consumption_l100km) }}" min="0.01" max="100" step="0.01" placeholder="12.5">
    @error('fuel_consumption_l100km') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="vehicle-gas-price">Current gas price (CAD / L)</label>
    <input type="number" id="vehicle-gas-price" name="gas_price_per_liter" value="{{ old('gas_price_per_liter', $vehicle?->gas_price_per_liter) }}" min="0" max="10" step="0.001" placeholder="1.450">
    @error('gas_price_per_liter') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-group">
  <label for="vehicle-notes">Notes</label>
  <textarea id="vehicle-notes" name="notes" rows="3" maxlength="5000" class="admin-input">{{ old('notes', $vehicle?->notes) }}</textarea>
  @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
</div>

<div class="admin-form-group">
  <input type="hidden" name="is_active" value="0">
  <label class="cp-visit-toggle cp-helper-active-toggle" for="vehicle-is-active">
    <input type="checkbox"
           name="is_active"
           value="1"
           id="vehicle-is-active"
           @checked(old('is_active', $vehicle?->is_active ?? ! $isEdit))>
    <span class="cp-visit-toggle__control" aria-hidden="true"></span>
    <span class="cp-visit-toggle__label">Set as active vehicle</span>
  </label>
  <p class="admin-form-help">Only one vehicle can be active. It is used for project travel fuel estimates.</p>
</div>
