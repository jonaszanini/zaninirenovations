<div style="background:#024768; color:#fff; border-radius:6px; padding:16px;">
  <h4 style="margin:0 0 16px; font-size:16px; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:8px;">Estimated Breakdown</h4>
  
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Total Area (incl. waste):</span>
    <strong>{{ number_format($calculation['total_area'], 2) }} sq ft</strong>
  </div>

  @if($calculation['mortar_bags'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Mortar ({{ $calculation['mortar_bags'] }} bags):</span>
    <strong>${{ number_format($calculation['mortar_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['grout_bags'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Grout ({{ $calculation['grout_bags'] }} bags):</span>
    <strong>${{ number_format($calculation['grout_total_price'], 2) }}</strong>
  </div>
  @endif

  @if($calculation['tile_total_price'] > 0)
  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Tile Material:</span>
    <strong>${{ number_format($calculation['tile_total_price'], 2) }}</strong>
  </div>
  @endif

  <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.2);">
    <span>Labor:</span>
    <strong>${{ number_format($calculation['labor_total'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <span>Subtotal:</span>
    <strong>${{ number_format($calculation['subtotal'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.2);">
    <span>HST (13%):</span>
    <strong>${{ number_format($calculation['hst'], 2) }}</strong>
  </div>

  <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:600;">
    <span>Grand Total:</span>
    <span>${{ number_format($calculation['grand_total'], 2) }}</span>
  </div>
</div>
