@php
    $prefix = $prefix ?? 'bsm-sync';
    $syncableCount = (int) ($syncableCount ?? 0);
    $usesSerpApi = (bool) ($usesSerpApi ?? false);
    $mode = $mode ?? 'both';
    $buttonClass = $buttonClass ?? 'admin-btn admin-btn--ghost admin-btn--sm';
    $showButton = in_array($mode, ['button', 'both'], true);
    $showProgress = in_array($mode, ['progress', 'both'], true);
@endphp
@if ($showButton)
<span class="bsm-sync-toolbar-item">
  <button type="button"
          id="{{ $prefix }}-prices-btn"
          class="{{ $buttonClass }}"
          data-bsm-material-sync
          data-progress-prefix="{{ $prefix }}"
          data-start-url="{{ route('admin.quotations.basement.materials.sync.start') }}"
          data-step-url="{{ route('admin.quotations.basement.materials.sync.step') }}"
          data-step-delay-ms="{{ (int) config('materials.scraper.admin_step_delay_ms', 800) }}"
          data-label-idle="Update basement material prices"
          data-label-running="Syncing…"
          @disabled($syncableCount === 0)>
    <i class="fa fa-refresh"></i> Update basement material prices
  </button>
  <x-admin.info-tip label="Update basement material prices">
    Refreshes catalog prices for materials linked from <strong>Basement price configuration</strong> and <strong>Shared prices</strong> rows used by basement ({{ number_format($syncableCount) }} syncable).
    @if ($usesSerpApi)
      Uses SerpApi for Home Depot product URLs and Amazon Canada ASINs. Saved quotations keep their stored prices.
    @else
      Uses the store price API on this server. Saved quotations keep their stored prices.
    @endif
  </x-admin.info-tip>
</span>
@endif
@if ($showProgress)
  <div id="{{ $prefix }}-progress" class="admin-card admin-card--narrow mat-bulk-progress mat-sync-all-progress bsm-sync-progress" hidden aria-live="polite" style="margin-top:16px;">
    <div class="mat-bulk-progress__head">
      <h3 class="mat-bulk-progress__title" id="{{ $prefix }}-phase-label">Sync in progress</h3>
      <span class="mat-bulk-progress__counter" id="{{ $prefix }}-counter">0 / 0</span>
    </div>
    <div class="mat-bulk-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="{{ $prefix }}-progressbar">
      <div class="mat-bulk-progress__bar" id="{{ $prefix }}-bar" style="width:0%"></div>
    </div>
    <p class="mat-bulk-progress__message" id="{{ $prefix }}-message">Preparing…</p>
    <dl class="mat-bulk-progress__stats" id="{{ $prefix }}-stats">
      <div><dt>Updated</dt><dd id="{{ $prefix }}-updated">0</dd></div>
      <div><dt>Unchanged</dt><dd id="{{ $prefix }}-unchanged">0</dd></div>
      <div><dt>Failed</dt><dd id="{{ $prefix }}-failed">0</dd></div>
      <div><dt>Skipped</dt><dd id="{{ $prefix }}-skipped">0</dd></div>
      <div><dt>Remaining</dt><dd id="{{ $prefix }}-remaining">0</dd></div>
    </dl>
    <ul class="mat-bulk-progress__log" id="{{ $prefix }}-log"></ul>
    <div class="mat-bulk-progress__done" id="{{ $prefix }}-done" hidden>
      <p class="mat-bulk-progress__summary" id="{{ $prefix }}-summary"></p>
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="{{ $prefix }}-reload">Reload page</button>
    </div>
  </div>
@endif
