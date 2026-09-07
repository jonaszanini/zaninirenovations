@extends('site.layouts.public')

@section('page_title', 'Project questionnaire – Zanini Renovations')
@section('meta_robots', 'noindex, nofollow')
@section('meta_description', 'Answer a few questions so Zanini Renovations can finish your basement quotation.')

@push('styles')
<link rel="stylesheet" href="{{ url('css/basement-intake.css') }}?v={{ @filemtime(public_path('css/basement-intake.css')) ?: time() }}">
@endpush

@php
  $form = $form ?? [];
  $sink = old('sink', $form['sink'] ?? null);
  $bathType = old('bath_type', $form['bath_type'] ?? null);
  $lightingType = old('lighting_type', $form['lighting_type'] ?? null);
  $ceilingType = old('ceiling_type', $form['ceiling_type'] ?? null);
  $flooringType = old('flooring_type', $form['flooring_type'] ?? null);
  $paintColors = old('paint_colors', $form['paint_colors'] ?? null);
  $tileFloorFinish = old('tile_floor_finish', $form['tile_floor_finish'] ?? null);
  $tileShowerFinish = old('tile_shower_finish', $form['tile_shower_finish'] ?? null);
@endphp

@section('content')
  <main class="bi-page">
    <div class="container">
      <div class="row">
        <div class="col-md-8 col-md-offset-2">
          <h1 class="bi-page__title">A few details for your quote</h1>
          <p class="bi-page__lead">
            @if ($isBathroomOnly)
              Answer these questions about the bathroom. Use the buttons — there is nothing to type except sizes and counts.
            @else
              Answer these questions about the basement. Use the buttons — there is nothing to type except sizes and counts.
            @endif
            Nothing is pre-selected, so choose an answer for every question. Submit once when you are done.
          </p>

          @if ($errors->any())
            <div class="bi-error" role="alert">
              {{ $errors->first() }}
            </div>
          @endif
          @if (session('error'))
            <div class="bi-error" role="alert">{{ session('error') }}</div>
          @endif

          <form method="post" action="{{ route('public.basement-intake.store', ['token' => $quote->client_intake_token]) }}" id="bi-form">
            @csrf

            <section class="bi-card">
              <h2 class="bi-card__title">Size</h2>
              @include('site.basement_intake._area', [
                'prefix' => 'area_entry',
                'modeName' => 'area_entry_mode',
                'sqftName' => 'area_sqft',
                'lenName' => 'area_length_ft',
                'widName' => 'area_width_ft',
                'label' => $isBathroomOnly ? 'Bathroom size' : 'Basement / work area',
                'form' => $form,
                'areaMin' => '0.01',
              ])
            </section>

            @unless ($isBathroomOnly)
              <section class="bi-card">
                <h2 class="bi-card__title">The space</h2>
                <div class="bi-grid bi-grid--2">
                  <div class="bi-field">
                    <label for="ceiling_height_ft">Ceiling height (ft)</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="ceiling_height_ft" name="ceiling_height_ft"
                           value="{{ old('ceiling_height_ft', $form['ceiling_height_ft'] ?? '') }}" placeholder="e.g. 8" required>
                  </div>
                  <div class="bi-field">
                    <label for="room_count">Finished rooms / zones when done</label>
                    <input type="number" step="1" min="1" class="form-control" id="room_count" name="room_count"
                           value="{{ old('room_count', $form['room_count'] ?? '') }}" placeholder="e.g. 1" required>
                  </div>
                </div>
                @include('site.basement_intake._yesno', ['name' => 'include_demolition', 'label' => 'Tear out existing floor or drywall?', 'form' => $form])
                <div class="bi-field">
                  <label for="partition_lf">New walls (linear feet). Use 0 if none.</label>
                  <input type="number" step="0.01" min="0" class="form-control" id="partition_lf" name="partition_lf"
                         value="{{ old('partition_lf', $form['partition_lf'] ?? '') }}" placeholder="0" required>
                </div>
                @include('site.basement_intake._yesno', ['name' => 'include_bathroom', 'label' => 'Include a bathroom in this basement?', 'form' => $form])
              </section>

              <section class="bi-card">
                <h2 class="bi-card__title">Electrical</h2>
                <p class="bi-card__lead">These answers go to a licensed electrician. Zanini does not price this work from this form.</p>
                <div class="bi-grid bi-grid--2">
                  <div class="bi-field">
                    <label for="outlet_qty">New outlets</label>
                    <input type="number" step="1" min="0" class="form-control" id="outlet_qty" name="outlet_qty"
                           value="{{ old('outlet_qty', $form['outlet_qty'] ?? '') }}" placeholder="0" required>
                  </div>
                  <div class="bi-field">
                    <label for="switch_qty">Switches</label>
                    <input type="number" step="1" min="0" class="form-control" id="switch_qty" name="switch_qty"
                           value="{{ old('switch_qty', $form['switch_qty'] ?? '') }}" placeholder="0" required>
                  </div>
                </div>
                <div class="bi-field">
                  <span class="bi-legend">Living-area ceiling lights</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    @foreach (['spot' => 'Spot / pot lights', 'fixture' => 'Light fixtures', 'both' => 'Both'] as $val => $lbl)
                      <label class="bi-choice">
                        <input type="radio" name="lighting_type" value="{{ $val }}" @checked($lightingType === $val) @if($loop->first) required @endif>
                        <span>{{ $lbl }}</span>
                      </label>
                    @endforeach
                  </div>
                </div>
                <div class="bi-grid bi-grid--2">
                  <div class="bi-field">
                    <label for="light_qty">How many spot lights (0 if none)</label>
                    <input type="number" step="1" min="0" class="form-control" id="light_qty" name="light_qty"
                           value="{{ old('light_qty', $form['light_qty'] ?? '') }}" placeholder="0" required>
                  </div>
                  <div class="bi-field" data-bi-reveal="lighting_type=both" hidden>
                    <label for="fixture_qty">How many light fixtures</label>
                    <input type="number" step="1" min="0" class="form-control" id="fixture_qty" name="fixture_qty"
                           value="{{ old('fixture_qty', $form['fixture_qty'] ?? '') }}" placeholder="0" required>
                  </div>
                </div>
                @include('site.basement_intake._yesno', ['name' => 'panel_upgrade', 'label' => 'Electrical panel upgrade needed?', 'form' => $form])
              </section>

              <section class="bi-card">
                <h2 class="bi-card__title">Plumbing extras</h2>
                <p class="bi-card__lead">These answers go to a licensed plumber. Zanini does not price this work from this form.</p>
                @include('site.basement_intake._yesno', ['name' => 'bath_rough_in', 'label' => 'Bathroom drain, vent, and water lines (rough-in)?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'sink_laundry', 'label' => 'Laundry / sink hookup?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'wet_bar', 'label' => 'Wet bar?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'floor_drain', 'label' => 'Floor drain?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'wh_reloc', 'label' => 'Move the water heater?', 'form' => $form])
              </section>

              <section class="bi-card">
                <h2 class="bi-card__title">Insulation, ceiling &amp; floor</h2>
                @include('site.basement_intake._yesno', ['name' => 'insulate_walls', 'label' => 'Insulate the walls?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'insulate_ceiling', 'label' => 'Insulate the ceiling?', 'form' => $form])
                @include('site.basement_intake._yesno', ['name' => 'underslab', 'label' => 'Under-slab insulation?', 'form' => $form])
                <div class="bi-field">
                  <span class="bi-legend">Ceiling finish</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    @foreach (['drywall' => 'Drywall', 'drop' => 'Drop ceiling', 'none' => 'Leave as-is'] as $val => $lbl)
                      <label class="bi-choice">
                        <input type="radio" name="ceiling_type" value="{{ $val }}" @checked($ceilingType === $val) @if($loop->first) required @endif>
                        <span>{{ $lbl }}</span>
                      </label>
                    @endforeach
                  </div>
                </div>
                <div class="bi-field">
                  <span class="bi-legend">Flooring</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    @foreach (['lvp' => 'Vinyl (LVP)', 'laminate' => 'Laminate', 'tile' => 'Tile', 'carpet' => 'Carpet', 'epoxy' => 'Epoxy', 'none' => 'Keep existing'] as $val => $lbl)
                      <label class="bi-choice">
                        <input type="radio" name="flooring_type" value="{{ $val }}" @checked($flooringType === $val) @if($loop->first) required @endif>
                        <span>{{ $lbl }}</span>
                      </label>
                    @endforeach
                  </div>
                </div>
                @include('site.basement_intake._supply', ['name' => 'flooring_provide', 'label' => 'Who supplies the flooring?', 'form' => $form, 'hideWhen' => 'flooring_type=none'])
                <div data-bi-hide-when="flooring_type=none">
                  @include('site.basement_intake._yesno', ['name' => 'underlayment', 'label' => 'Underlayment under the floor?', 'form' => $form])
                  @include('site.basement_intake._yesno', ['name' => 'subfloor_prep', 'label' => 'Level / prep the subfloor?', 'form' => $form])
                </div>
                <div class="bi-field">
                  <label for="door_qty">Interior doors to install</label>
                  <input type="number" step="1" min="0" class="form-control" id="door_qty" name="door_qty"
                         value="{{ old('door_qty', $form['door_qty'] ?? '') }}" placeholder="0" required>
                </div>
                <div class="bi-field">
                  <span class="bi-legend">Paint colors</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    <label class="bi-choice">
                      <input type="radio" name="paint_colors" value="1" @checked((string) $paintColors === '1') required>
                      <span>One color</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="paint_colors" value="2" @checked((string) $paintColors === '2')>
                      <span>Two colors</span>
                    </label>
                  </div>
                </div>
              </section>
            @endunless

            <div @unless($isBathroomOnly) data-bi-reveal="include_bathroom=1" hidden @endunless>
              <section class="bi-card">
                <h2 class="bi-card__title">Bathroom</h2>
                @if ($isBathroomOnly)
                  <p class="bi-card__lead">Rough-in answers go to a licensed plumber. Zanini does not price that work from this form.</p>
                  @include('site.basement_intake._yesno', ['name' => 'bath_rough_in', 'label' => 'New drain, vent, and water lines (rough-in)?', 'form' => $form])
                @endif
                <div class="bi-field">
                  <span class="bi-legend">Bathroom type</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    <label class="bi-choice">
                      <input type="radio" name="bath_type" value="full" @checked($bathType === 'full') required>
                      <span>Full bath (shower or tub)</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="bath_type" value="half" @checked($bathType === 'half')>
                      <span>Half bath (toilet + sink)</span>
                    </label>
                  </div>
                </div>
                <div class="bi-field">
                  <span class="bi-legend">Sink</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    <label class="bi-choice">
                      <input type="radio" name="sink" value="vanity" @checked($sink === 'vanity') required>
                      <span>Vanity</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="sink" value="pedestal" @checked($sink === 'pedestal')>
                      <span>Pedestal sink</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="sink" value="none" @checked($sink === 'none')>
                      <span>No sink</span>
                    </label>
                  </div>
                </div>
                @include('site.basement_intake._supply', ['name' => 'sink_provide', 'label' => 'Who supplies the vanity or pedestal sink?', 'form' => $form, 'hideWhen' => 'sink=none'])
                @include('site.basement_intake._supply', ['name' => 'faucet_provide', 'label' => 'Who supplies the faucet?', 'form' => $form, 'hideWhen' => 'sink=none'])
                <div class="bi-field" data-bi-hide-when="sink=none">
                  <span class="bi-legend">How many sinks?</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    @php $sinkQty = (string) old('bath_sink_qty', $form['bath_sink_qty'] ?? ''); @endphp
                    <label class="bi-choice">
                      <input type="radio" name="bath_sink_qty" value="1" @checked($sinkQty === '1')>
                      <span>1</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="bath_sink_qty" value="2" @checked($sinkQty === '2')>
                      <span>2</span>
                    </label>
                  </div>
                </div>
                @include('site.basement_intake._yesno', ['name' => 'include_toilet', 'label' => 'Install a toilet?', 'form' => $form])
                @include('site.basement_intake._supply', ['name' => 'toilet_provide', 'label' => 'Who supplies the toilet?', 'form' => $form, 'reveal' => 'include_toilet=1'])
                <div data-bi-reveal="bath_type=full" hidden>
                  @include('site.basement_intake._yesno', ['name' => 'include_shower', 'label' => 'Install a shower?', 'form' => $form])
                  @include('site.basement_intake._supply', ['name' => 'shower_provide', 'label' => 'Who supplies the shower frame?', 'form' => $form, 'reveal' => 'include_shower=1'])
                  @include('site.basement_intake._supply', ['name' => 'showerhead_provide', 'label' => 'Who supplies the showerhead?', 'form' => $form, 'reveal' => 'include_shower=1'])
                  @include('site.basement_intake._yesno', ['name' => 'include_tub', 'label' => 'Install a bathtub?', 'form' => $form])
                  @include('site.basement_intake._supply', ['name' => 'tub_provide', 'label' => 'Who supplies the tub?', 'form' => $form, 'reveal' => 'include_tub=1'])
                </div>
                @include('site.basement_intake._yesno', ['name' => 'include_exhaust_fan', 'label' => 'Bathroom exhaust fan?', 'form' => $form])
                <p class="bi-card__lead">Bathroom lights go to a licensed electrician. Zanini does not price that work from this form.</p>
                @include('site.basement_intake._yesno', ['name' => 'include_bath_fixture', 'label' => 'Bathroom light fixture?', 'form' => $form])
                <div class="bi-field" data-bi-reveal="include_bath_fixture=1" hidden>
                  <label for="bath_fixture_qty">How many bathroom light fixtures</label>
                  <input type="number" step="1" min="1" class="form-control" id="bath_fixture_qty" name="bath_fixture_qty"
                         value="{{ old('bath_fixture_qty', $form['bath_fixture_qty'] ?? '') }}" required>
                </div>
                @include('site.basement_intake._yesno', ['name' => 'include_bath_spot', 'label' => 'Bathroom spot / pot light?', 'form' => $form])
                <div class="bi-field" data-bi-reveal="include_bath_spot=1" hidden>
                  <label for="bath_spot_qty">How many bathroom spot lights</label>
                  <input type="number" step="1" min="1" class="form-control" id="bath_spot_qty" name="bath_spot_qty"
                         value="{{ old('bath_spot_qty', $form['bath_spot_qty'] ?? '') }}" required>
                </div>
              </section>

              <section class="bi-card">
                <h2 class="bi-card__title">Bathroom floor &amp; tile</h2>
                @include('site.basement_intake._area', [
                  'prefix' => 'tile_floor',
                  'label' => 'Floor area',
                  'form' => $form,
                ])
                <div class="bi-field">
                  <span class="bi-legend">Floor finish</span>
                  <div class="bi-choices" role="radiogroup" aria-required="true">
                    <label class="bi-choice">
                      <input type="radio" name="tile_floor_finish" value="tile" @checked($tileFloorFinish === 'tile') required>
                      <span>Tile</span>
                    </label>
                    <label class="bi-choice">
                      <input type="radio" name="tile_floor_finish" value="vinyl" @checked($tileFloorFinish === 'vinyl')>
                      <span>Vinyl (LVP)</span>
                    </label>
                  </div>
                </div>
                <div data-bi-reveal="bath_type=full" hidden>
                  @include('site.basement_intake._area', [
                    'prefix' => 'tile_shower',
                    'label' => 'Shower tile area (walls / floor)',
                    'form' => $form,
                  ])
                  <div class="bi-field">
                    <span class="bi-legend">Shower finish</span>
                    <div class="bi-choices" role="radiogroup" aria-required="true">
                      <label class="bi-choice">
                        <input type="radio" name="tile_shower_finish" value="tile" @checked($tileShowerFinish === 'tile') required>
                        <span>Tile</span>
                      </label>
                      <label class="bi-choice">
                        <input type="radio" name="tile_shower_finish" value="vinyl" @checked($tileShowerFinish === 'vinyl')>
                        <span>Vinyl (LVP)</span>
                      </label>
                    </div>
                  </div>
                </div>
              </section>
            </div>

            <section class="bi-card">
              <h2 class="bi-card__title">Finish</h2>
              @include('site.basement_intake._yesno', ['name' => 'include_cleaning', 'label' => 'Final cleaning?', 'form' => $form])
              @include('site.basement_intake._yesno', ['name' => 'include_touchups', 'label' => 'Touch-ups / punch list?', 'form' => $form])
            </section>

            <button type="submit" class="btn btn-theme bi-submit">Submit answers</button>
          </form>
        </div>
      </div>
    </div>
  </main>
@endsection

@push('scripts')
<script src="{{ url('js/basement-intake.js') }}?v={{ @filemtime(public_path('js/basement-intake.js')) ?: time() }}"></script>
@endpush
