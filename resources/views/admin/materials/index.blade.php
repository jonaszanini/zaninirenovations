@extends('admin.layouts.app')

@section('heading', 'Materials')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.materials.index') }}" class="mat-toolbar-search">
      <input type="search" name="q" value="{{ $q }}" placeholder="Search name, SKU, vendor…" class="admin-input mat-toolbar-search__input">
      @if ($categories->isNotEmpty())
        <select name="category" class="admin-input mat-toolbar-search__category">
          <option value="">All categories</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
          @endforeach
        </select>
      @endif
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <div class="mat-toolbar-actions">
      <a href="{{ route('admin.materials.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> Add material
      </a>
      <p class="admin-form-help mat-toolbar-total">
        {{ number_format($totalMaterials) }} {{ $totalMaterials === 1 ? 'material' : 'materials' }} in database
      </p>
    </div>
  </div>

  <details class="admin-accordion admin-card admin-card--narrow mat-bulk-import" @if(old('skus')) open @endif>
    <summary class="admin-accordion__summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="admin-accordion__title">Bulk import Home Depot SKUs</span>
    </summary>
    <div class="admin-accordion__panel">
      <form id="mat-bulk-form" method="post" action="{{ route('admin.materials.bulk-import') }}"
            data-start-url="{{ route('admin.materials.bulk-import.start') }}"
            data-step-url="{{ route('admin.materials.bulk-import.step') }}">
        @csrf
        <div class="admin-form-group">
          <label for="mat-bulk-skus">SKUs or product URLs</label>
          <textarea id="mat-bulk-skus" name="skus" rows="8" class="admin-input @error('skus') is-invalid @enderror" aria-label="SKU list, one per line" placeholder="Paste one SKU or product URL per line…">{{ old('skus') }}</textarea>
          @error('skus') <p class="admin-form-error">{{ $message }}</p> @enderror
          <p class="admin-form-help">
            One SKU (e.g. <code>1000108771</code>) or full Home Depot product URL per line.
            Blank lines are ignored. If the same SKU appears more than once in your paste, only the first is imported; the rest are skipped. SKUs already in the catalog are updated.
          </p>
        </div>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="mat-bulk-category">Category</label>
            <input type="text" id="mat-bulk-category" name="category" value="{{ old('category') }}" class="admin-input" list="mat-bulk-category-list" placeholder="e.g. Lumber">
            @if ($categories->isNotEmpty())
              <datalist id="mat-bulk-category-list">
                @foreach ($categories as $cat)
                  <option value="{{ $cat }}">
                @endforeach
              </datalist>
            @endif
          </div>
          <div class="admin-form-group">
            <label for="mat-bulk-vendor">Vendor</label>
            <input type="text" id="mat-bulk-vendor" name="vendor" value="{{ old('vendor', 'Home Depot') }}" class="admin-input">
          </div>
        </div>
        <div class="admin-form-group">
          @include('admin.partials.checkbox', [
            'name' => 'sync_prices',
            'id' => 'mat-bulk-sync',
            'checked' => old('sync_prices'),
            'label' => 'Sync prices after import',
          ])
          <p class="admin-form-help">
            When checked, fetches name and price from the Home Depot store API for each imported item (~2&nbsp;s each).
            For large lists (30+ SKUs), leave unchecked and sync later from the admin or via <code>php artisan materials:sync-prices</code> on your PC.
          </p>
        </div>
        <div id="mat-bulk-progress" class="mat-bulk-progress" hidden aria-live="polite">
          <div class="mat-bulk-progress__head">
            <h3 class="mat-bulk-progress__title" id="mat-bulk-phase-label">Import in progress</h3>
            <span class="mat-bulk-progress__counter" id="mat-bulk-counter">0 / 0</span>
          </div>
          <div class="mat-bulk-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="mat-bulk-progressbar">
            <div class="mat-bulk-progress__bar" id="mat-bulk-bar" style="width:0%"></div>
          </div>
          <p class="mat-bulk-progress__message" id="mat-bulk-message">Preparing…</p>
          <dl class="mat-bulk-progress__stats" id="mat-bulk-stats">
            <div><dt>Created</dt><dd id="mat-stat-created">0</dd></div>
            <div><dt>Updated</dt><dd id="mat-stat-updated">0</dd></div>
            <div><dt>Synced</dt><dd id="mat-stat-sync-ok">0</dd></div>
            <div><dt>Sync failed</dt><dd id="mat-stat-sync-failed">0</dd></div>
          </dl>
          <ul class="mat-bulk-progress__log" id="mat-bulk-log"></ul>
          <div class="mat-bulk-progress__done" id="mat-bulk-done" hidden>
            <p class="mat-bulk-progress__summary" id="mat-bulk-summary"></p>
            <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="mat-bulk-reload">Reload page</button>
          </div>
        </div>
        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary" id="mat-bulk-submit">Import SKUs</button>
        </div>
      </form>
    </div>
  </details>

  <div class="admin-card">
    <form id="mat-bulk-delete-form" method="post" action="{{ route('admin.materials.bulk-destroy') }}"
          onsubmit="return confirm('Delete the selected materials permanently? This cannot be undone.');">
      @csrf
      <div class="mat-bulk-actions" id="mat-bulk-actions" hidden>
        <span class="mat-bulk-actions__count" id="mat-selected-count">0 selected</span>
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" id="mat-bulk-delete-btn" disabled>
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
                         aria-label="Select {{ $material->name }}">
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
                <strong>{{ $material->name }}</strong>
                @if ($material->product_url)
                  <br><a href="{{ $material->product_url }}" target="_blank" rel="noopener" class="admin-text-link mat-product-link">Product page</a>
                @endif
              </td>
              <td>{{ $material->category ?: '—' }}</td>
              <td>{{ $material->vendor ?: '—' }}</td>
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
                  @if ($material->hasProductUrl())
                    <form method="post" action="{{ route('admin.materials.sync-price', $material) }}" class="mat-sync-inline-form">
                      @csrf
                      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Sync price from Home Depot">
                        <i class="fa fa-refresh"></i> Sync
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="admin-form-help">No materials yet. Add your first item or bulk-import Home Depot SKUs above.</td>
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
      var bulkForm = document.getElementById('mat-bulk-form');
      if (bulkForm) {
        var csrf = bulkForm.querySelector('input[name="_token"]');
        var progressEl = document.getElementById('mat-bulk-progress');
        var submitBtn = document.getElementById('mat-bulk-submit');
        var phaseLabel = document.getElementById('mat-bulk-phase-label');
        var counterEl = document.getElementById('mat-bulk-counter');
        var barEl = document.getElementById('mat-bulk-bar');
        var progressbarEl = document.getElementById('mat-bulk-progressbar');
        var messageEl = document.getElementById('mat-bulk-message');
        var logEl = document.getElementById('mat-bulk-log');
        var doneEl = document.getElementById('mat-bulk-done');
        var summaryEl = document.getElementById('mat-bulk-summary');
        var reloadBtn = document.getElementById('mat-bulk-reload');
        var statCreated = document.getElementById('mat-stat-created');
        var statUpdated = document.getElementById('mat-stat-updated');
        var statSyncOk = document.getElementById('mat-stat-sync-ok');
        var statSyncFailed = document.getElementById('mat-stat-sync-failed');

        function postJson(url, body) {
          return fetch(url, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrf ? csrf.value : '',
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

        function postForm(url, formData) {
          return fetch(url, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf ? csrf.value : '',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
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

        function updateProgress(data) {
          var overallTotal = data.overall_total || 1;
          var overallCurrent = data.overall_current || 0;
          var pct = overallTotal > 0 ? Math.round((overallCurrent / overallTotal) * 100) : 0;

          if (phaseLabel) {
            phaseLabel.textContent = data.phase_label || 'Working…';
          }
          if (counterEl) {
            if (data.phase === 'import') {
              counterEl.textContent = 'Import ' + (data.current || 0) + ' / ' + (data.phase_total || 0);
            } else if (data.phase === 'sync') {
              counterEl.textContent = 'Sync ' + (data.current || 0) + ' / ' + (data.phase_total || 0);
            } else if (data.phase === 'done') {
              counterEl.textContent = 'Complete';
            } else {
              counterEl.textContent = (data.overall_current || 0) + ' / ' + (data.overall_total || 0);
            }
          }
          if (barEl) {
            barEl.style.width = pct + '%';
          }
          if (progressbarEl) {
            progressbarEl.setAttribute('aria-valuenow', String(pct));
          }
          if (messageEl) {
            messageEl.textContent = data.message || '';
          }
          if (data.stats) {
            if (statCreated) statCreated.textContent = String(data.stats.created || 0);
            if (statUpdated) statUpdated.textContent = String(data.stats.updated || 0);
            if (statSyncOk) statSyncOk.textContent = String(data.stats.sync_ok || 0);
            if (statSyncFailed) statSyncFailed.textContent = String(data.stats.sync_failed || 0);
          }
          if (logEl && Array.isArray(data.log)) {
            logEl.innerHTML = '';
            data.log.slice().reverse().forEach(function (line) {
              var li = document.createElement('li');
              li.textContent = line;
              logEl.appendChild(li);
            });
          }
        }

        function setRunning(running) {
          if (submitBtn) {
            submitBtn.disabled = running;
            submitBtn.textContent = running ? 'Importing…' : 'Import SKUs';
          }
        }

        function showError(msg) {
          if (messageEl) {
            messageEl.textContent = msg;
            messageEl.classList.add('mat-bulk-progress__message--error');
          }
          setRunning(false);
        }

        bulkForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (!progressEl) return;

          var startUrl = bulkForm.getAttribute('data-start-url');
          var stepUrl = bulkForm.getAttribute('data-step-url');
          if (!startUrl || !stepUrl) return;

          progressEl.hidden = false;
          if (doneEl) doneEl.hidden = true;
          if (messageEl) messageEl.classList.remove('mat-bulk-progress__message--error');
          if (logEl) logEl.innerHTML = '';
          setRunning(true);

          var formData = new FormData(bulkForm);
          if (!formData.has('sync_prices')) {
            formData.append('sync_prices', '0');
          }

          postForm(startUrl, formData)
            .then(function (startData) {
              updateProgress(startData);
              if (startData.skip_note && messageEl) {
                messageEl.textContent = startData.message + ' ' + startData.skip_note;
              }

              function runStep(token) {
                return postJson(stepUrl, { token: token }).then(function (stepData) {
                  updateProgress(stepData);
                  if (stepData.done) {
                    setRunning(false);
                    if (doneEl) doneEl.hidden = false;
                    if (summaryEl) summaryEl.textContent = stepData.summary || stepData.message || 'Done.';
                    return stepData;
                  }
                  return runStep(token);
                });
              }

              return runStep(startData.token);
            })
            .catch(function (err) {
              showError(err.message || 'Import failed.');
            });
        });

        if (reloadBtn) {
          reloadBtn.addEventListener('click', function () {
            window.location.reload();
          });
        }
      }

      var deleteForm = document.getElementById('mat-bulk-delete-form');
      if (deleteForm) {
        var selectAll = document.getElementById('mat-select-all');
        var rowChecks = deleteForm.querySelectorAll('.mat-row-select');
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
