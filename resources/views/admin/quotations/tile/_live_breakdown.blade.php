<div style="background:#fff; color:#334155; border-radius:6px; padding:16px; border:1px solid #e2e8f0;">
  <h4 style="margin:0 0 16px; font-size:16px; border-bottom:1px solid #e2e8f0; padding-bottom:8px; color:#0f172a;">Estimated Breakdown</h4>
  
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Total Area (incl. waste):</span>
    <strong style="color:#0f172a;">{{ number_format($calculation['total_area'], 2) }} sq ft</strong>
  </div>

  @if($calculation['mortar_bags'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Mortar ({{ $calculation['mortar_bags'] }} bags):</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['mortar_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['grout_bags'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Grout ({{ $calculation['grout_bags'] }} bags):</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['grout_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['tile_total_price'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Tile Material:</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['tile_total_price'], 2) }}</strong>
  </div>
  @endif

  @if(!empty($calculation['include_demolition']) && $calculation['include_demolition'])
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Demolition & Prep:</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['demolition_total'], 2) }}</strong>
  </div>
  @endif

  <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
    <span>Labor:</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['labor_total'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Subtotal:</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['subtotal'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
    <span>HST (13%):</span>
    <strong style="color:#0f172a;">${{ number_format($calculation['hst'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:600; color:#0f172a;">
    <span>Grand Total:</span>
    <span>${{ number_format($calculation['grand_total'], 2) }}</span>
  </div>
</div>
