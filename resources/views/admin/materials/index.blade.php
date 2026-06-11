@extends('admin.layouts.app')

@section('heading', 'Materials')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar mat-toolbar">
    <form method="get" action="{{ route('admin.materials.index') }}" class="mat-toolbar-search">
      <input type="search" name="q" value="{{ $q }}" placeholder="Search name, SKU, vendor…" class="admin-input mat-toolbar-search__input">
      @if ($categories->isNotEmpty())
        <select name="category" class="admin-input mat-toolbar-search__category">
          <option value="">All categories</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected((int) ($categoryId ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
          @endforeach
        </select>
      @endif
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <div class="mat-toolbar-actions">
      <a href="{{ route('admin.materials.config') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Config
      </a>
      <button type="button" id="mat-sync-all-btn" class="admin-btn admin-btn--ghost admin-btn--sm"
              data-start-url="{{ route('admin.materials.sync-all.start') }}"
              data-step-url="{{ route('admin.materials.sync-all.step') }}"
              @disabled($syncableCount === 0)>
        <i class="fa fa-refresh"></i> Sync all
      </button>
      <a href="{{ route('admin.materials.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> Add material
      </a>
    </div>
  </div>

  <div id="mat-sync-all-progress" class="admin-card admin-card--narrow mat-bulk-progress mat-sync-all-progress" hidden aria-live="polite">
    <div class="mat-bulk-progress__head">
      <h3 class="mat-bulk-progress__title" id="mat-sync-all-phase-label">Sync in progress</h3>
      <span class="mat-bulk-progress__counter" id="mat-sync-all-counter">0 / 0</span>
    </div>
    <div class="mat-bulk-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="mat-sync-all-progressbar">
      <div class="mat-bulk-progress__bar" id="mat-sync-all-bar" style="width:0%"></div>
    </div>
    <p class="mat-bulk-progress__message" id="mat-sync-all-message">Preparing…</p>
    <dl class="mat-bulk-progress__stats" id="mat-sync-all-stats">
      <div><dt>Synced</dt><dd id="mat-sync-all-ok">0</dd></div>
      <div><dt>Failed</dt><dd id="mat-sync-all-failed">0</dd></div>
      <div><dt>Skipped</dt><dd id="mat-sync-all-skipped">0</dd></div>
      <div><dt>Remaining</dt><dd id="mat-sync-all-remaining">0</dd></div>
    </dl>
    <ul class="mat-bulk-progress__log" id="mat-sync-all-log"></ul>
    <div class="mat-bulk-progress__done" id="mat-sync-all-done" hidden>
      <p class="mat-bulk-progress__summary" id="mat-sync-all-summary"></p>
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="mat-sync-all-reload">Reload page</button>
    </div>
  </div>

  <div class="admin-card">
    <p class="mat-table-meta">
      {{ number_format($totalMaterials) }} {{ $totalMaterials === 1 ? 'material' : 'materials' }} in database
      @if ($syncableCount > 0)
        · {{ number_format($syncableCount) }} syncable
        @if (($homeDepotSyncableCount ?? 0) > 0 || ($amazonSyncableCount ?? 0) > 0)
          ({{ number_format($homeDepotSyncableCount ?? 0) }} Home Depot{{ ($amazonSyncableCount ?? 0) > 0 ? ', '.number_format($amazonSyncableCount).' Amazon' : '' }})
        @endif
      @endif
      @if ($usesSerpApi)
        · admin sync via SerpApi
      @endif
      @if ($needsSyncCount > 0)
        · {{ number_format($needsSyncCount) }} need sync
      @endif
    </p>

    <form id="mat-bulk-delete-form" method="post" action="{{ route('admin.materials.bulk-destroy') }}"
          onsubmit="return confirm('Delete the selected materials permanently? This cannot be undone.');">
      @csrf
      <div class="mat-bulk-actions" id="mat-bulk-actions" hidden>
        <span class="mat-bulk-actions__count" id="mat-selected-count">0 selected</span>
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" id="mat-bulk-delete-btn" disabled>
          <i class="fa fa-trash"></i> Delete selected
        </button>
      </div>
    </form>
    <div class="admin-table-scroll">
      <table class="admin-table mat-table">
        <thead>
          <tr>
            <th class="mat-col-select" scope="col">
              @if ($materials->isNotEmpty())
                <label class="admin-checkbox mat-select-all-label">
                  <input type="checkbox" id="mat-select-all" class="admin-checkbox__input" aria-label="Select all on this page">
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                </label>
              @endif
            </th>
            <th class="mat-col-image" scope="col"><span class="admin-sr-only">Image</span></th>
            <th>Name</th>
            <th>Category</th>
            <th>Vendor</th>
            <th>SKU</th>
            <th class="mat-col-price">Price</th>
            <th>Last synced</th>
            <th style="width:90px;">Active</th>
            <th class="mat-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($materials as $material)
            <tr class="{{ $material->is_active ? '' : 'mat-row--inactive' }}">
              <td class="mat-col-select">
                <label class="admin-checkbox mat-row-select-label">
                  <input type="checkbox" form="mat-bulk-delete-form" name="ids[]" value="{{ $material->id }}" class="admin-checkbox__input mat-row-select"
                         aria-label="Select {{ $material->displayName() }}">
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                </label>
              </td>
              <td class="mat-col-image">
                @if ($material->image_url)
                  <img src="{{ $material->image_url }}" alt="" class="mat-thumb" loading="lazy" width="48" height="48">
                @else
                  <span class="mat-thumb mat-thumb--empty" aria-hidden="true"><i class="fa fa-image"></i></span>
                @endif
              </td>
              <td>
                <strong>{{ $material->displayName() }}</strong>
                @if (filled($material->short_name))
                  <br><small class="admin-form-help mat-store-name">{{ $material->name }}</small>
                @endif
                @if ($material->product_url)
                  <br><a href="{{ $material->product_url }}" target="_blank" rel="noopener" class="admin-text-link mat-product-link">Product page</a>
                @endif
              </td>
              <td>{{ $material->categoryName() ?: '—' }}</td>
              <td>{{ $material->vendorName() ?: '—' }}</td>
              <td>{{ $material->store_sku ?: '—' }}</td>
              <td class="mat-col-price">{{ $material->formattedPrice() }}</td>
              <td>
                @if ($material->last_scraped_at)
                  <span class="mat-scrape-badge mat-scrape-badge--{{ $material->last_scrape_status ?? 'unknown' }}">
                    {{ $material->last_scrape_status }}
                  </span>
                  <br><small class="admin-form-help">{{ \App\Support\EasternStandardTimeDisplay::formatInstantCalendarDate($material->last_scraped_at) }}</small>
                @else
                  <span class="admin-form-help">—</span>
                @endif
              </td>
              <td>{{ $material->is_active ? 'Yes' : 'No' }}</td>
              <td class="mat-col-actions">
                <div class="mat-row-actions">
                  <a href="{{ route('admin.materials.edit', $material) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                    <i class="fa fa-pencil"></i> Edit
                  </a>
                  @if ($material->isSerpApiSyncable())
                    <form method="post" action="{{ route('admin.materials.sync-price', $material) }}" class="mat-sync-inline-form">
                      @csrf
                      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm"
                              title="{{ $material->isAmazonSyncable() ? 'Sync price from Amazon Canada' : 'Sync price from Home Depot' }}">
                        <i class="fa fa-refresh"></i> Sync
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="admin-form-help">No materials yet. <a href="{{ route('admin.materials.create') }}">Add your first item</a> or use <a href="{{ route('admin.materials.config') }}">Config</a> to bulk-import Home Depot SKUs.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($materials->hasPages())
      <div style="margin-top:16px;">{{ $materials->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      function getCsrfToken() {
        var el = document.querySelector('#mat-bulk-delete-form input[name="_token"]');
        return el ? el.value : '';
      }

      function postJson(url, body, csrf) {
        return fetch(url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(body),
          credentials: 'same-origin'
        }).then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok) {
              var err = new Error(data.message || 'Request failed');
              err.data = data;
              err.status = res.status;
              throw err;
            }
            return data;
          });
        });
      }

      var syncAllBtn = document.getElementById('mat-sync-all-btn');
      if (syncAllBtn) {
        var syncProgressEl = document.getElementById('mat-sync-all-progress');
        var syncPhaseLabel = document.getElementById('mat-sync-all-phase-label');
        var syncCounterEl = document.getElementById('mat-sync-all-counter');
        var syncBarEl = document.getElementById('mat-sync-all-bar');
        var syncProgressbarEl = document.getElementById('mat-sync-all-progressbar');
        var syncMessageEl = document.getElementById('mat-sync-all-message');
        var syncLogEl = document.getElementById('mat-sync-all-log');
        var syncDoneEl = document.getElementById('mat-sync-all-done');
        var syncSummaryEl = document.getElementById('mat-sync-all-summary');
        var syncReloadBtn = document.getElementById('mat-sync-all-reload');
        var syncOkEl = document.getElementById('mat-sync-all-ok');
        var syncFailedEl = document.getElementById('mat-sync-all-failed');
        var syncSkippedEl = document.getElementById('mat-sync-all-skipped');
        var syncRemainingEl = document.getElementById('mat-sync-all-remaining');

        function updateSyncAllProgress(data) {
          var total = data.phase_total || 1;
          var current = data.current || 0;
          var pct = total > 0 ? Math.round((current / total) * 100) : 0;

          if (syncPhaseLabel) {
            syncPhaseLabel.textContent = data.phase_label || 'Syncing…';
          }
          if (syncCounterEl) {
            if (data.phase === 'done') {
              syncCounterEl.textContent = current + ' / ' + total + ' complete';
            } else {
              syncCounterEl.textContent = current + ' / ' + total + ' · ' + (data.remaining ?? (total - current)) + ' remaining';
            }
          }
          if (syncBarEl) {
            syncBarEl.style.width = pct + '%';
          }
          if (syncProgressbarEl) {
            syncProgressbarEl.setAttribute('aria-valuenow', String(pct));
          }
          if (syncMessageEl) {
            syncMessageEl.textContent = data.message || '';
            syncMessageEl.classList.remove('mat-bulk-progress__message--error');
          }
          if (data.stats) {
            if (syncOkEl) syncOkEl.textContent = String(data.stats.ok || 0);
            if (syncFailedEl) syncFailedEl.textContent = String(data.stats.failed || 0);
            if (syncSkippedEl) syncSkippedEl.textContent = String(data.stats.skipped || 0);
            if (syncRemainingEl) syncRemainingEl.textContent = String(data.remaining ?? Math.max(0, total - current));
          }
          if (syncLogEl && Array.isArray(data.log)) {
            syncLogEl.innerHTML = '';
            data.log.slice().reverse().forEach(function (line) {
              var li = document.createElement('li');
              li.textContent = line;
              syncLogEl.appendChild(li);
            });
          }
        }

        function setSyncAllRunning(running) {
          syncAllBtn.disabled = running;
          syncAllBtn.innerHTML = running
            ? '<i class="fa fa-refresh"></i> Syncing…'
            : '<i class="fa fa-refresh"></i> Sync all';
        }

        syncAllBtn.addEventListener('click', function () {
          var startUrl = syncAllBtn.getAttribute('data-start-url');
          var stepUrl = syncAllBtn.getAttribute('data-step-url');
          if (!startUrl || !stepUrl || !syncProgressEl) return;

          var csrf = getCsrfToken();
          syncProgressEl.hidden = false;
          if (syncDoneEl) syncDoneEl.hidden = true;
          if (syncLogEl) syncLogEl.innerHTML = '';
          setSyncAllRunning(true);

          postJson(startUrl, {}, csrf)
            .then(function (startData) {
              updateSyncAllProgress(startData);

              function runStep(token) {
                return postJson(stepUrl, { token: token }, csrf).then(function (stepData) {
                  updateSyncAllProgress(stepData);
                  if (stepData.done) {
                    setSyncAllRunning(false);
                    if (syncDoneEl) syncDoneEl.hidden = false;
                    if (syncSummaryEl) syncSummaryEl.textContent = stepData.summary || stepData.message || 'Done.';
                    return stepData;
                  }
                  return runStep(token);
                });
              }

              return runStep(startData.token);
            })
            .catch(function (err) {
              setSyncAllRunning(false);
              if (syncMessageEl) {
                syncMessageEl.textContent = err.message || 'Sync failed.';
                syncMessageEl.classList.add('mat-bulk-progress__message--error');
              }
            });
        });

        if (syncReloadBtn) {
          syncReloadBtn.addEventListener('click', function () {
            window.location.reload();
          });
        }
      }

      var deleteForm = document.getElementById('mat-bulk-delete-form');
      if (deleteForm) {
        var selectAll = document.getElementById('mat-select-all');
        var rowChecks = document.querySelectorAll('.mat-row-select');
        var actionsBar = document.getElementById('mat-bulk-actions');
        var countEl = document.getElementById('mat-selected-count');
        var deleteBtn = document.getElementById('mat-bulk-delete-btn');

        function selectedCount() {
          var n = 0;
          rowChecks.forEach(function (cb) {
            if (cb.checked) n++;
          });
          return n;
        }

        function updateBulkActions() {
          var n = selectedCount();
          if (actionsBar) {
            actionsBar.hidden = n === 0;
          }
          if (countEl) {
            countEl.textContent = n === 1 ? '1 selected' : n + ' selected';
          }
          if (deleteBtn) {
            deleteBtn.disabled = n === 0;
          }
          if (selectAll && rowChecks.length > 0) {
            selectAll.checked = n > 0 && n === rowChecks.length;
            selectAll.indeterminate = n > 0 && n < rowChecks.length;
          }
        }

        rowChecks.forEach(function (cb) {
          cb.addEventListener('change', updateBulkActions);
        });

        if (selectAll) {
          selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (cb) {
              cb.checked = selectAll.checked;
            });
            updateBulkActions();
          });
        }

        updateBulkActions();
      }
    })();
  </script>
@endpush
