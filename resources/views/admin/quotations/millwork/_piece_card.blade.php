@php
  use App\Services\Millwork\MillworkPartBuilder;
  $pi = $pi ?? 0;
  $piece = is_array($piece ?? null) ? $piece : [];
  $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
  $sets = is_array($piece['sets'] ?? null) ? $piece['sets'] : [['qty' => 1, 'unit' => 'in']];
  $unit = ($piece['unit'] ?? 'in') === 'ft' ? 'ft' : 'in';
@endphp
<article class="mw-piece admin-card" data-mw-piece data-mw-type="{{ $type }}">
  <div class="mw-piece__head">
    <h4 class="mw-piece__title">Piece <span data-mw-piece-n>{{ is_numeric($pi) ? ((int) $pi + 1) : '1' }}</span></h4>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-remove-piece aria-label="Remove piece">
      <i class="fa fa-trash"></i> Remove
    </button>
  </div>

  <div class="admin-form-group">
    <label>@include('admin.partials.info_tip', ['text' => 'Pick the millwork type. Fields below change to match.']) Type</label>
    <div class="sh-choice-grid sh-choice-grid--3">
      @foreach (MillworkPartBuilder::TYPES as $key => $label)
        <label class="sh-choice">
          <input type="radio" name="pieces[{{ $pi }}][type]" value="{{ $key }}" class="mw-preview" data-mw-type-input
                 @checked($type === $key)>
          <span>{{ $label }}</span>
        </label>
      @endforeach
    </div>
  </div>

  <div class="admin-form-grid-2" data-mw-show="kallax cabinet bench">
    <div class="admin-form-group">
      <label>How many</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][qty]" value="{{ $piece['qty'] ?? 1 }}">
    </div>
    <div class="admin-form-group">
      <label>Units</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][unit]">
        <option value="in" @selected($unit === 'in')>Inches</option>
        <option value="ft" @selected($unit === 'ft')>Feet</option>
      </select>
    </div>
    <div class="admin-form-group" data-mw-show="kallax cabinet">
      <label>Width</label>
      <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][width]" value="{{ $piece['width'] ?? '' }}">
    </div>
    <div class="admin-form-group" data-mw-show="bench">
      <label>Length</label>
      <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][length]" value="{{ $piece['length'] ?? '' }}">
    </div>
    <div class="admin-form-group" data-mw-show="kallax cabinet bench">
      <label>Height</label>
      <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][height]" value="{{ $piece['height'] ?? '' }}">
    </div>
    <div class="admin-form-group" data-mw-show="kallax cabinet bench">
      <label>Depth</label>
      <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][depth]" value="{{ $piece['depth'] ?? '' }}">
    </div>
  </div>

  <div class="admin-form-grid-2" data-mw-show="kallax">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Cubes across. Vertical partitions = columns − 1.']) Columns</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][columns]" value="{{ $piece['columns'] ?? 2 }}">
    </div>
    <div class="admin-form-group">
      <label>Rows</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][rows]" value="{{ $piece['rows'] ?? 2 }}">
    </div>
  </div>

  <div class="admin-form-grid-2" data-mw-show="cabinet">
    <div class="admin-form-group">
      <label>Left side</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][left_side]">
        <option value="panel" @selected(($piece['left_side'] ?? 'panel') === 'panel')>Panel</option>
        <option value="wall" @selected(($piece['left_side'] ?? '') === 'wall')>Against wall (no panel)</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Right side</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][right_side]">
        <option value="panel" @selected(($piece['right_side'] ?? 'panel') === 'panel')>Panel</option>
        <option value="wall" @selected(($piece['right_side'] ?? '') === 'wall')>Against wall (no panel)</option>
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
      <label>Door count</label>
      <input type="number" min="1" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][door_count]" value="{{ $piece['door_count'] ?? 2 }}">
    </div>
    <div class="admin-form-group">
      <label>Overlay</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][overlay]">
        <option value="full" @selected(($piece['overlay'] ?? 'full') === 'full')>Full overlay</option>
        <option value="half" @selected(($piece['overlay'] ?? '') === 'half')>Half overlay</option>
        <option value="inset" @selected(($piece['overlay'] ?? '') === 'inset')>Inset</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Hinge type</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][hinge_style]">
        <option value="overlay" @selected(($piece['hinge_style'] ?? 'overlay') === 'overlay')>Overlay</option>
        <option value="inset" @selected(($piece['hinge_style'] ?? '') === 'inset')>Inset</option>
        <option value="soft_close" @selected(($piece['hinge_style'] ?? '') === 'soft_close')>Soft-close</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Fixed shelves</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][shelf_count]" value="{{ $piece['shelf_count'] ?? 1 }}">
    </div>
    <div class="admin-form-group">
      <label>Drawers</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][drawer_count]" value="{{ $piece['drawer_count'] ?? 0 }}">
    </div>
  </div>

  <div class="admin-form-grid-2" data-mw-show="bench">
    <div class="admin-form-group">
      <label>Sides</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][sides]">
        <option value="panel" @selected(($piece['sides'] ?? 'panel') === 'panel')>Panel sides</option>
        <option value="wall" @selected(($piece['sides'] ?? '') === 'wall')>Walls (no side panels)</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Below the seat</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][below]">
        <option value="divisions" @selected(($piece['below'] ?? 'divisions') === 'divisions')>Divisions</option>
        <option value="drawers" @selected(($piece['below'] ?? '') === 'drawers')>Drawers</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Division count</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][division_count]" value="{{ $piece['division_count'] ?? 2 }}">
    </div>
    <div class="admin-form-group">
      <label>Drawer count</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][drawer_count]" value="{{ $piece['drawer_count'] ?? 0 }}">
    </div>
  </div>

  <div data-mw-show="shelf floating_shelf cabinet_door">
    <h4 class="br-review-heading">Size sets</h4>
    <p class="admin-form-help">Add as many size groups as you need (for example 2 of 1×8 ft and 3 of 1×4 ft).</p>
    <div data-mw-set-list>
      @foreach ($sets as $si => $set)
        @include('admin.quotations.millwork._set_row', ['pi' => $pi, 'si' => $si, 'set' => is_array($set) ? $set : [], 'unit' => $unit])
      @endforeach
    </div>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-add-set style="margin-top:8px;">
      <i class="fa fa-plus"></i> Add size set
    </button>
  </div>

  <div class="admin-form-grid-2" style="margin-top:12px;">
    <div class="admin-form-group">
      <label>Finish</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][finish]">
        <option value="none" @selected(($piece['finish'] ?? 'none') === 'none')>No paint / stain</option>
        <option value="paint" @selected(($piece['finish'] ?? '') === 'paint')>Painted</option>
        <option value="stain" @selected(($piece['finish'] ?? '') === 'stain')>Stained</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Fasteners</label>
      <select class="admin-input mw-preview" name="pieces[{{ $pi }}][fastener]">
        <option value="screws" @selected(($piece['fastener'] ?? 'screws') === 'screws')>Screws</option>
        <option value="nails" @selected(($piece['fastener'] ?? '') === 'nails')>Nails</option>
      </select>
    </div>
    <div class="admin-form-group">
      <label>Pulls / handles (qty)</label>
      <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][pull_qty]" value="{{ $piece['pull_qty'] ?? 0 }}">
    </div>
  </div>

  @include('admin.partials.checkbox', [
    'name' => 'pieces['.$pi.'][edge_banding]',
    'hiddenFallback' => true,
    'checked' => array_key_exists('edge_banding', $piece) ? ! empty($piece['edge_banding']) : true,
    'label' => 'Edge banding',
    'info' => 'Shrinks the raw cut so the finished size matches after banding.',
    'inputClass' => 'admin-checkbox__input mw-preview',
  ])
  <div data-mw-show="floating_shelf kallax cabinet bench">
    @include('admin.partials.checkbox', [
      'name' => 'pieces['.$pi.'][include_back]',
      'hiddenFallback' => true,
      'checked' => ! empty($piece['include_back']),
      'label' => 'Include 1/4" back',
      'inputClass' => 'admin-checkbox__input mw-preview',
    ])
  </div>
  <div data-mw-show="cabinet">
    @include('admin.partials.checkbox', [
      'name' => 'pieces['.$pi.'][include_toe_kick]',
      'hiddenFallback' => true,
      'checked' => ! empty($piece['include_toe_kick']),
      'label' => 'Toe kick',
      'inputClass' => 'admin-checkbox__input mw-preview',
    ])
  </div>
</article>
