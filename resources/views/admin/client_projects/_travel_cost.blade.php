<div class="admin-card cp-travel-cost-card" id="cp-travel-cost" data-cp-travel-cost data-url="{{ route('admin.client-projects.travel-estimate', $project) }}">
  <div class="cp-travel-cost-card__head">
    <h3><i class="fa fa-car" aria-hidden="true"></i> Estimated travel cost</h3>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-travel-refresh title="Recalculate distance">
      <i class="fa fa-refresh"></i> Refresh
    </button>
  </div>
  <p class="admin-form-help">Round trip from your home / office to this project, using the active vehicle’s L/100 km and your company gas price.</p>
  <div class="cp-travel-cost-card__body" data-cp-travel-body aria-live="polite">
    <p class="cp-travel-cost-card__loading" data-cp-travel-loading>Calculating travel estimate…</p>
    <div class="cp-travel-cost-card__result" data-cp-travel-result hidden></div>
    <p class="cp-travel-cost-card__error admin-form-error" data-cp-travel-error hidden></p>
  </div>
  <div class="cp-travel-cost-card__foot">
    <a href="{{ route('admin.vehicles.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-travel-cost-card__manage">
      <i class="fa fa-cog" aria-hidden="true"></i>
      Manage vehicles &amp; home address
    </a>
  </div>
</div>
