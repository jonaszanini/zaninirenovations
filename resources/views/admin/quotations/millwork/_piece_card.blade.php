@php
  use App\Services\Millwork\MillworkPartBuilder;
  $pi = $pi ?? 0;
  $piece = is_array($piece ?? null) ? $piece : [];
  $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
  $sets = is_array($piece['sets'] ?? null) ? $piece['sets'] : [['qty' => 1, 'unit' => 'in']];
  $unit = ($piece['unit'] ?? 'in') === 'ft' ? 'ft' : 'in';
  $style = MillworkPartBuilder::normalizeCabinetStyle((string) ($piece['style'] ?? 'base'));
  $profile = MillworkPartBuilder::normalizeTrimProfile((string) ($piece['profile'] ?? 'baseboard'));
  $isLegacyQuote = $isLegacyQuote ?? (((int) ($cutPlanVersion ?? $builderVersion ?? 2)) < 2);
@endphp
<article class="mw-piece admin-card" data-mw-piece data-mw-type="{{ $type }}" data-mw-below="{{ (($piece['below'] ?? 'divisions') === 'drawers') ? 'drawers' : 'divisions' }}">
  <div class="mw-piece__head">
    <h4 class="mw-piece__title">Piece <span data-mw-piece-n>{{ is_numeric($pi) ? ((int) $pi + 1) : '1' }}</span></h4>
    <div class="mw-piece__actions">
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-duplicate-piece>
        <i class="fa fa-copy"></i> Duplicate
      </button>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-remove-piece aria-label="Remove piece">
        <i class="fa fa-trash"></i> Remove
      </button>
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--id">
    <div class="admin-form-group mw-field--type">
      <label>@include('admin.partials.info_tip', ['text' => 'Pick the millwork type. Fields below change to match.']) Type</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][type]" data-mw-type-input>
        @foreach (MillworkPartBuilder::TYPE_GROUPS as $group => $keys)
          <optgroup label="{{ $group }}">
            @foreach ($keys as $key)
              <option value="{{ $key }}" @selected($type === $key)>{{ MillworkPartBuilder::TYPES[$key] }}</option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
    </div>
    <div class="admin-form-group">
      <label>Room</label>
      <input type="text" class="admin-input mw-preview" name="pieces[{{ $pi }}][room]" maxlength="80"
             placeholder="Kitchen…" value="{{ $piece['room'] ?? '' }}">
    </div>
    <div class="admin-form-group mw-piece__name">
      <label>Piece name</label>
      <input type="text" class="admin-input mw-preview" name="pieces[{{ $pi }}][name]" maxlength="80"
             placeholder="Pantry built-in…" value="{{ $piece['name'] ?? '' }}">
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--dims" data-mw-show="kallax cabinet vanity closet bench mantel wainscoting trim">
    <input type="hidden" name="pieces[{{ $pi }}][unit]" value="{{ $unit }}" data-mw-unit>
    <div class="admin-form-group mw-field--qty" data-mw-show="kallax cabinet vanity closet bench mantel">
      <label>How many</label>
      <input type="number" min="1" max="200" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][qty]" value="{{ $piece['qty'] ?? 1 }}">
    </div>
    @include('admin.quotations.millwork._dim_field', [
      'pi' => $pi,
      'piece' => $piece,
      'row' => $piece,
      'namePrefix' => 'pieces['.$pi.']',
      'dimName' => 'width',
      'dimLabel' => 'W',
      'dimField' => 'w',
      'dimShow' => 'kallax cabinet vanity closet',
      'dimPlaceholder' => $type === 'closet' ? '72' : '36',
      'setField' => null,
    ])
    @include('admin.quotations.millwork._dim_field', [
      'pi' => $pi,
      'piece' => $piece,
      'row' => $piece,
      'namePrefix' => 'pieces['.$pi.']',
      'dimName' => 'length',
      'dimLabel' => 'L',
      'dimField' => 'len',
      'dimShow' => 'bench mantel',
      'dimPlaceholder' => $type === 'mantel' ? '60' : '48',
      'setField' => null,
    ])
    <div class="admin-form-group mw-field--lf" data-mw-show="wainscoting trim">
      <label>Lin. ft</label>
      <input type="text" inputmode="decimal" autocomplete="off"
             class="admin-input mw-preview mw-input--dim{{ $errors->has('pieces.'.$pi.'.linear_ft') ? ' is-invalid' : '' }}"
             name="pieces[{{ $pi }}][linear_ft]"
             placeholder="24" value="{{ $piece['linear_ft'] ?? '' }}">
      @error('pieces.'.$pi.'.linear_ft')
        <p class="admin-form-error">{{ $message }}</p>
      @enderror
    </div>
    @include('admin.quotations.millwork._dim_field', [
      'pi' => $pi,
      'piece' => $piece,
      'row' => $piece,
      'namePrefix' => 'pieces['.$pi.']',
      'dimName' => 'height',
      'dimLabel' => 'H',
      'dimField' => 'h',
      'dimShow' => 'kallax cabinet vanity closet bench mantel wainscoting',
      'dimPlaceholder' => in_array($type, ['closet'], true) ? '84' : ($type === 'wainscoting' ? '36' : '34.5'),
      'setField' => null,
    ])
    @include('admin.quotations.millwork._dim_field', [
      'pi' => $pi,
      'piece' => $piece,
      'row' => $piece,
      'namePrefix' => 'pieces['.$pi.']',
      'dimName' => 'depth',
      'dimLabel' => 'D',
      'dimField' => 'd',
      'dimShow' => 'kallax cabinet vanity closet bench mantel',
      'dimPlaceholder' => $type === 'vanity' ? '21' : ($type === 'closet' ? '14' : ($type === 'mantel' ? '10' : '24')),
      'setField' => null,
    ])
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="kallax closet">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Sections across. Vertical partitions = columns − 1.']) Columns</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][columns]" value="{{ $piece['columns'] ?? 2 }}">
    </div>
    <div class="admin-form-group" data-mw-show="kallax">
      <label>Rows</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][rows]" value="{{ $piece['rows'] ?? 2 }}">
    </div>
    <div class="admin-form-group mw-field--finish" data-mw-show="kallax">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>None</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
        <option value="clear" @selected(($piece['finish'] ?? '') === 'clear')>Clear coat</option>
      </select>
    </div>
    <div class="admin-form-group" data-mw-show="closet">
      <label>Shelves / sec.</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][shelf_count]" value="{{ $piece['shelf_count'] ?? 3 }}">
    </div>
    <div class="admin-form-group" data-mw-show="closet">
      <label>Rods</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][rod_count]" value="{{ $piece['rod_count'] ?? 1 }}">
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="cabinet vanity closet">
    <div class="admin-form-group mw-field--style" data-mw-show="cabinet">
      <label>Style</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][style]" data-mw-cabinet-style>
        @foreach (MillworkPartBuilder::CABINET_STYLES as $key => $label)
          <option value="{{ $key }}" @selected($style === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="admin-form-group">
      <label>Doors</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][include_doors]">
        <option value="1" @selected(!empty($piece['include_doors']))>Yes</option>
        <option value="0" @selected(empty($piece['include_doors']))>No</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Door #</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][door_count]" value="{{ $piece['door_count'] ?? 2 }}">
    </div>
    <div class="admin-form-group" data-mw-show="cabinet vanity">
      <label>Shelves</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][shelf_count]" value="{{ $piece['shelf_count'] ?? 1 }}">
    </div>
    <div class="admin-form-group" data-mw-show="cabinet vanity closet">
      <label>Drawers</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][drawer_count]" value="{{ $piece['drawer_count'] ?? 0 }}">
    </div>
    <div class="admin-form-group mw-field--finish">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>None</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
        <option value="clear" @selected(($piece['finish'] ?? '') === 'clear')>Clear coat</option>
      </select>
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="cabinet cabinet_door vanity closet bench-drawers">
    @unless ($isLegacyQuote)
    <div class="admin-form-group" data-mw-show="cabinet cabinet_door vanity closet">
      <label>
        <span data-mw-show="cabinet vanity closet">Hinges (per unit)</span>
        <span data-mw-show="cabinet_door">Hinges (total)</span>
      </label>
      <input type="number" min="0" max="100" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][hinge_qty]"
             placeholder="Auto" value="{{ $piece['hinge_qty'] ?? '' }}">
    </div>
    @endunless
    <div class="admin-form-group">
      <label>Pulls</label>
      <input type="number" min="0" max="100" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][pull_qty]"
             placeholder="Auto" value="{{ $piece['pull_qty'] ?? '' }}">
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="bench">
    <div class="admin-form-group mw-field--sides">
      <label>Sides</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][sides]">
        <option value="panel" @selected(($piece['sides'] ?? 'panel') === 'panel')>Panel sides</option>
        <option value="wall" @selected(($piece['sides'] ?? '') === 'wall')>Walls (no sides)</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Below seat</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][below]">
        <option value="divisions" @selected(($piece['below'] ?? 'divisions') === 'divisions')>Divisions</option>
        <option value="drawers" @selected(($piece['below'] ?? '') === 'drawers')>Drawers</option>
      </select>
    </div>
    <div class="admin-form-group" data-mw-show="bench-divisions">
      <label>Divisions</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][division_count]" value="{{ $piece['division_count'] ?? 2 }}">
    </div>
    <div class="admin-form-group" data-mw-show="bench-drawers">
      <label>Drawers</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][drawer_count]" value="{{ $piece['drawer_count'] ?? 0 }}">
    </div>
    <div class="admin-form-group mw-field--finish">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>None</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
        <option value="clear" @selected(($piece['finish'] ?? '') === 'clear')>Clear coat</option>
      </select>
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="trim">
    <div class="admin-form-group mw-field--profile">
      <label>Profile</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][profile]">
        @foreach (MillworkPartBuilder::TRIM_PROFILES as $key => $label)
          <option value="{{ $key }}" @selected($profile === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Each opening adds 2 × height + width of casing (defaults 80" × 32").']) Openings</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" name="pieces[{{ $pi }}][openings]" value="{{ $piece['openings'] ?? 0 }}">
    </div>
    <div class="admin-form-group">
      <label>Stock (ft)</label>
      <input type="number" min="1" step="0.5" class="admin-input mw-preview mw-input--dim" name="pieces[{{ $pi }}][stock_length_ft]"
             placeholder="12" value="{{ $piece['stock_length_ft'] ?? 12 }}">
    </div>
    <div class="admin-form-group mw-field--finish">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>None</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
        <option value="clear" @selected(($piece['finish'] ?? '') === 'clear')>Clear coat</option>
      </select>
    </div>
  </div>

  <div class="mw-piece__row mw-piece__row--pack" data-mw-show="shelf floating_shelf cabinet_door mantel wainscoting">
    <div class="admin-form-group mw-field--finish">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>None</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
        <option value="clear" @selected(($piece['finish'] ?? '') === 'clear')>Clear coat</option>
      </select>
    </div>
  </div>

  <div data-mw-show="shelf floating_shelf cabinet_door">
    <p class="admin-form-help mw-piece__hint">Size sets — add groups if you have mixed sizes.</p>
    <p class="admin-form-help mw-piece__hint" data-mw-show="floating_shelf">Open-back box: top, bottom, front, and ends. Thickness is the outer box height.</p>
    <div data-mw-set-list>
      @foreach ($sets as $si => $set)
        @include('admin.quotations.millwork._set_row', ['pi' => $pi, 'si' => $si, 'set' => is_array($set) ? $set : [], 'unit' => $unit])
      @endforeach
    </div>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-add-set>
      <i class="fa fa-plus"></i> Add size set
    </button>
  </div>

  <details class="mw-piece__more">
    <summary>More options</summary>
    <div class="mw-piece__row mw-piece__row--pack" data-mw-show="cabinet vanity closet">
      <div class="admin-form-group">
        <label>Left side</label>
        <select class="admin-input mw-preview" name="pieces[{{ $pi }}][left_side]">
          <option value="panel" @selected(($piece['left_side'] ?? 'panel') === 'panel')>Panel</option>
          <option value="wall" @selected(($piece['left_side'] ?? '') === 'wall')>Against wall</option>
        </select>
      </div>
      <div class="admin-form-group">
        <label>Right side</label>
        <select class="admin-input mw-preview" name="pieces[{{ $pi }}][right_side]">
          <option value="panel" @selected(($piece['right_side'] ?? 'panel') === 'panel')>Panel</option>
          <option value="wall" @selected(($piece['right_side'] ?? '') === 'wall')>Against wall</option>
        </select>
      </div>
      <div class="admin-form-group" data-mw-show="cabinet vanity closet">
        <label>Overlay</label>
        <select class="admin-input mw-preview" name="pieces[{{ $pi }}][overlay]">
          <option value="full" @selected(($piece['overlay'] ?? 'full') === 'full')>Full</option>
          <option value="half" @selected(($piece['overlay'] ?? '') === 'half')>Half</option>
          <option value="inset" @selected(($piece['overlay'] ?? '') === 'inset')>Inset</option>
        </select>
      </div>
      <div class="admin-form-group" data-mw-show="cabinet vanity closet">
        <label>Hinge</label>
        <select class="admin-input mw-preview" name="pieces[{{ $pi }}][hinge_style]">
          <option value="overlay" @selected(($piece['hinge_style'] ?? 'overlay') === 'overlay')>Overlay</option>
          <option value="inset" @selected(($piece['hinge_style'] ?? '') === 'inset')>Inset</option>
          <option value="soft_close" @selected(($piece['hinge_style'] ?? '') === 'soft_close')>Soft-close</option>
        </select>
      </div>
    </div>
    <div class="mw-piece__row mw-piece__row--pack">
      <div class="admin-form-group">
        <label>Fasteners</label>
        <select class="admin-input mw-preview" name="pieces[{{ $pi }}][fastener]">
          <option value="screws" @selected(($piece['fastener'] ?? 'screws') === 'screws')>Screws</option>
          <option value="nails" @selected(($piece['fastener'] ?? '') === 'nails')>Nails</option>
        </select>
      </div>
    </div>
    <div class="mw-piece__flags">
      @include('admin.partials.checkbox', [
        'name' => 'pieces['.$pi.'][edge_banding]',
        'hiddenFallback' => true,
        'checked' => array_key_exists('edge_banding', $piece) ? ! empty($piece['edge_banding']) : true,
        'label' => 'Edge banding',
        'info' => 'Shrinks the raw cut so the finished size matches after banding.',
        'inputClass' => 'admin-checkbox__input mw-preview',
      ])
      <div data-mw-show="floating_shelf kallax cabinet vanity closet bench mantel">
        @include('admin.partials.checkbox', [
          'name' => 'pieces['.$pi.'][include_back]',
          'hiddenFallback' => true,
          'checked' => ! empty($piece['include_back']),
          'label' => $type === 'mantel' ? 'Include bottom panel' : 'Include 1/4" back',
          'inputClass' => 'admin-checkbox__input mw-preview',
        ])
      </div>
      <div data-mw-show="cabinet vanity">
        @include('admin.partials.checkbox', [
          'name' => 'pieces['.$pi.'][include_toe_kick]',
          'hiddenFallback' => true,
          'checked' => ! empty($piece['include_toe_kick']),
          'label' => 'Toe kick',
          'inputClass' => 'admin-checkbox__input mw-preview',
        ])
      </div>
    </div>
  </details>
</article>
