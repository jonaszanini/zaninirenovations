<div class="tl-live-breakdown">
  <h4 class="tl-live-breakdown__title">Estimated Breakdown</h4>

  <div class="tl-live-breakdown__row">
    <span>Total Area (incl. waste):</span>
    <strong>{{ number_format($calculation['total_area'], 2) }} sq ft</strong>
  </div>

  @if($calculation['mortar_bags'] > 0)
  <div class="tl-live-breakdown__row">
    <span>Mortar ({{ $calculation['mortar_bags'] }} bags):</span>
    <strong>${{ number_format($calculation['mortar_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['grout_bags'] > 0)
  <div class="tl-live-breakdown__row">
    <span>Grout ({{ $calculation['grout_bags'] }} bags):</span>
    <strong>${{ number_format($calculation['grout_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['tile_total_price'] > 0)
  <div class="tl-live-breakdown__row">
    <span>Tile Material:</span>
    <strong>${{ number_format($calculation['tile_total_price'], 2) }}</strong>
  </div>
  @endif

  @if(!empty($calculation['edge_pieces']) && $calculation['edge_pieces'] > 0)
  <div class="tl-live-breakdown__row">
    <span>Tile Edge ({{ $calculation['edge_pieces'] }} × {{ (int) ($calculation['edge_piece_length_ft'] ?? 8) }}&nbsp;ft{{ !empty($calculation['edge_name']) ? ' — '.$calculation['edge_name'] : '' }}):</span>
    <strong>${{ number_format($calculation['edge_total_price'], 2) }}</strong>
  </div>
  @endif

  @if(!empty($calculation['include_demolition']) && $calculation['include_demolition'])
  <div class="tl-live-breakdown__row">
    <span>Demolition &amp; Prep:</span>
    <strong>${{ number_format($calculation['demolition_total'], 2) }}</strong>
  </div>
  @endif

  @if(!empty($calculation['building_labor_surcharge']) && $calculation['building_labor_surcharge'] > 0)
  <div class="tl-live-breakdown__row">
    <span>Building access (+{{ rtrim(rtrim(number_format((float) ($calculation['building_labor_percent'] ?? 30), 2, '.', ''), '0'), '.') }}% labour):</span>
    <strong>${{ number_format($calculation['building_labor_surcharge'], 2) }}</strong>
  </div>
  @endif

  <div class="tl-live-breakdown__row tl-live-breakdown__row--rule">
    <span>Labor:</span>
    <strong>${{ number_format($calculation['labor_total'], 2) }}</strong>
  </div>

  <div class="tl-live-breakdown__row">
    <span>Subtotal:</span>
    <strong>${{ number_format($calculation['subtotal'], 2) }}</strong>
  </div>

  <div class="tl-live-breakdown__row tl-live-breakdown__row--rule">
    <span>HST (13%):</span>
    <strong>${{ number_format($calculation['hst'], 2) }}</strong>
  </div>

  <div class="tl-live-breakdown__row tl-live-breakdown__row--grand" data-tl-grand-row>
    <span>Grand Total:</span>
    <span data-tl-grand-value>${{ number_format($calculation['grand_total'], 2) }}</span>
  </div>
</div>
