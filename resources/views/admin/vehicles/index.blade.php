@extends('admin.layouts.app')

@section('heading', 'Vehicles')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-vehicles.css') }}?v={{ @filemtime(public_path('css/admin-vehicles.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Track company vehicles, fuel costs, business contact, and your home / office address for project travel estimates.
    </p>
    <a href="{{ route('admin.vehicles.create') }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> Add new vehicle
    </a>
  </div>

  @unless ($mapsConfigured)
    <div class="admin-flash admin-flash--warning admin-vehicles-maps-warning" role="alert">
      <strong>Google Maps not configured.</strong>
      Add <code>GOOGLE_MAPS_API_KEY</code> to your environment and enable <strong>Maps JavaScript API</strong>, <strong>Places API (New)</strong>, and <strong>Distance Matrix API</strong> in Google Cloud Console (with billing on).
    </div>
  @endunless

  <div class="admin-vehicles-grid">
    <div class="admin-card admin-vehicles-home-card">
      <h3>Home / office address</h3>
      <p class="admin-form-help">Used as the starting point for round-trip travel cost on each project.</p>
      <form action="{{ route('admin.vehicles.settings.home-address') }}" method="post">
        @csrf
        @method('PUT')
        @include('admin.partials.places_address_field', [
          'inputId' => 'home-address',
          'addressName' => 'home_address',
          'label' => 'Home / office address',
          'value' => old('home_address', $settings->home_address),
          'placeIdName' => 'home_address_place_id',
          'placeIdValue' => old('home_address_place_id', $settings->home_address_place_id),
          'latName' => 'home_address_latitude',
          'latValue' => old('home_address_latitude', $settings->home_address_latitude),
          'lngName' => 'home_address_longitude',
          'lngValue' => old('home_address_longitude', $settings->home_address_longitude),
          'help' => 'Pick a Google suggestion so distance to projects can be calculated automatically.',
        ])
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>Save home address</button>
      </form>
    </div>

    <div class="admin-card admin-vehicles-contact-card">
      <h3>Business contact @include('admin.partials.info_tip', [
        'tipText' => 'Shown to clients on quote accept pages.',
        'tipLabel' => 'About business contact',
      ])</h3>
      <form action="{{ route('admin.vehicles.settings.business-contact') }}" method="post">
        @csrf
        @method('PUT')
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="company-contact-phone">Phone</label>
            <input class="admin-input cp-phone-input"
                   type="text"
                   id="company-contact-phone"
                   name="contact_phone"
                   value="{{ old('contact_phone', $settings->contact_phone) }}"
                   inputmode="tel"
                   autocomplete="tel"
                   maxlength="40"
                   placeholder="(647) 834-7570">
            @error('contact_phone') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="company-contact-email">Email</label>
            <input class="admin-input"
                   type="email"
                   id="company-contact-email"
                   name="contact_email"
                   value="{{ old('contact_email', $settings->contact_email) }}"
                   autocomplete="email"
                   maxlength="160"
                   placeholder="zaninirenovations@gmail.com">
            @error('contact_email') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>Save business contact</button>
      </form>
    </div>

    <div class="admin-card admin-vehicles-calc-card"
         id="vehicles-fuel-calc"
         data-cp-vehicle-calc
         @if ($activeVehicle && $activeVehicle->hasFuelConsumption() && $settings->hasGasPrice())
           data-consumption="{{ $activeVehicle->fuel_consumption_l100km }}"
           data-gas-price="{{ $settings->gas_price_per_liter }}"
           data-vehicle-name="{{ $activeVehicle->name }}"
         @elseif ($activeVehicle && $activeVehicle->hasFuelConsumption())
           data-consumption="{{ $activeVehicle->fuel_consumption_l100km }}"
           data-vehicle-name="{{ $activeVehicle->name }}"
         @endif>
      <h3>Fuel calculator</h3>

      <form action="{{ route('admin.vehicles.settings.gas-price') }}" method="post" class="admin-vehicles-gas-form">
        @csrf
        @method('PUT')
        <div class="admin-form-group admin-vehicles-gas-field">
          <label for="company-gas-price">Current gas price (CAD / L)</label>
          <div class="admin-vehicles-gas-row">
            <input class="admin-input cp-money-input" type="text"
                   id="company-gas-price"
                   name="gas_price_per_liter"
                   value="{{ old('gas_price_per_liter', $settings->gas_price_per_liter !== null ? number_format((float) $settings->gas_price_per_liter, 3, '.', '') : '') }}"
                   inputmode="decimal"
                   autocomplete="off"
                  
                   data-cp-money-places="3"
                   data-cp-gas-price
                   placeholder="1.450"
                   required>
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>Save gas price</button>
          </div>
          <p class="admin-form-help">Company-wide — used with each vehicle’s L/100 km for fuel estimates.</p>
          @error('gas_price_per_liter') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </form>

      @if ($activeVehicle && $activeVehicle->hasFuelConsumption() && $settings->hasGasPrice())
        <p class="admin-form-help">Based on active vehicle: <strong>{{ $activeVehicle->name }}</strong></p>
        <div class="cp-profit-grid admin-vehicles-calc-tiles">
          <div class="cp-profit-tile">
            <div class="cp-profit-label">Cost per km</div>
            <div class="cp-profit-value" data-cp-calc-cost-per-km>—</div>
          </div>
          <div class="cp-profit-tile">
            <div class="cp-profit-label">Fuel use</div>
            <div class="cp-profit-value" data-cp-calc-consumption>—</div>
          </div>
        </div>
        <div class="admin-form-group admin-vehicles-trip-estimator">
          <label for="vehicles-trip-km">Quick trip estimate (km)</label>
          <input type="number"
                 id="vehicles-trip-km"
                 class="admin-input"
                 min="0"
                 step="0.1"
                 placeholder="e.g. 45"
                 data-cp-calc-trip-km>
          <p class="admin-form-help admin-vehicles-trip-result" data-cp-calc-trip-result aria-live="polite">
            Enter a distance to estimate fuel cost.
          </p>
        </div>
      @else
        <div class="admin-empty admin-vehicles-calc-empty">
          <p>Add an active vehicle with L/100 km and save a company gas price above to see fuel calculations and project travel estimates.</p>
        </div>
      @endif
    </div>
  </div>

  <h3 class="admin-vehicles-list-heading">Your vehicles</h3>

  @if ($vehicles->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>No vehicles yet.</p>
        <a href="{{ route('admin.vehicles.create') }}" class="admin-btn admin-btn--primary">Add your first vehicle</a>
      </div>
    </div>
  @else
    <div class="admin-vehicles-cards">
      @foreach ($vehicles as $vehicle)
        @php
          $costPerKm = $settings->hasGasPrice()
            ? $vehicle->costPerKm((float) $settings->gas_price_per_liter)
            : null;
        @endphp
        <article class="admin-card admin-vehicle-card {{ $vehicle->is_active ? 'admin-vehicle-card--active' : '' }}">
          <div class="admin-vehicle-card__head">
            <div>
              <h4 class="admin-vehicle-card__title">
                {{ $vehicle->name }}
                @if ($vehicle->is_active)
                  <span class="cp-badge admin-vehicle-card__badge">Active</span>
                @endif
              </h4>
              <p class="admin-vehicle-card__meta">{{ $vehicle->displaySubtitle() }}</p>
            </div>
            @unless ($vehicle->is_active)
              <form action="{{ route('admin.vehicles.activate', $vehicle) }}" method="post">
                @csrf
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Use this vehicle for travel estimates">Set active</button>
              </form>
            @endunless
          </div>
          <dl class="admin-vehicle-card__stats">
            <div>
              <dt>Fuel use</dt>
              <dd>{{ $vehicle->fuel_consumption_l100km !== null ? number_format((float) $vehicle->fuel_consumption_l100km, 1).' L/100km' : '—' }}</dd>
            </div>
            <div>
              <dt>Cost / km</dt>
              <dd>{{ $costPerKm !== null ? \App\Models\ClientProject::formatMoney($costPerKm) : '—' }}</dd>
            </div>
            <div>
              <dt>Odometer</dt>
              <dd>{{ $vehicle->odometer_reading !== null ? number_format($vehicle->odometer_reading).' km' : '—' }}</dd>
            </div>
          </dl>
          @if (filled($vehicle->notes))
            <p class="admin-vehicle-card__notes">{{ $vehicle->notes }}</p>
          @endif
          <div class="admin-vehicle-card__actions">
            <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
            <form action="{{ route('admin.vehicles.destroy', $vehicle) }}" method="post" onsubmit="return confirm('Delete this vehicle?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
            </form>
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection

@push('scripts')
  @include('admin.partials.google_maps_scripts')
  <script src="{{ asset('js/admin-vehicles.js') }}?v={{ @filemtime(public_path('js/admin-vehicles.js')) ?: time() }}" defer></script>
@endpush
