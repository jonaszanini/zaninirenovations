{{--
  Read-only slide-over drawer for a single ClientProjectQuotation.

  Expects:
    $project    — ClientProject
    $quotation  — ClientProjectQuotation
    $sourceModel — the linked source model (FenceQuotation, PavingQuotation, etc.)
                   or null for legacy/unlinked.
--}}
@php
  use App\Models\ClientProjectQuotation;
  $drawerId  = 'qd-' . $quotation->id;
  $sourceType = $quotation->source_type ?? '';
@endphp

{{-- ─── Drawer Shell ──────────────────────────────────────────────────── --}}
<div
  id="{{ $drawerId }}"
  class="cp-view-drawer"
  role="dialog"
  aria-modal="true"
  aria-label="View {{ $quotation->resolvedTitle() }}"
  hidden
>
  <div class="cp-view-drawer__overlay" onclick="cpCloseQuotationDrawer('{{ $drawerId }}')"></div>

  <div class="cp-view-drawer__panel">

    {{-- Header --}}
    <div class="cp-view-drawer__header">
      <div class="cp-view-drawer__header-text">
        <span class="cp-view-drawer__type-label">{{ $quotation->typeLabel() }}</span>
        <h2 class="cp-view-drawer__title">{{ $quotation->quotation_number }}</h2>
        @if (filled($quotation->title))
          <p class="cp-view-drawer__subtitle">{{ $quotation->title }}</p>
        @endif
      </div>
      <button
        type="button"
        class="cp-view-drawer__close"
        onclick="cpCloseQuotationDrawer('{{ $drawerId }}')"
        aria-label="Close details panel"
      >
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
    </div>

    {{-- Scrollable body --}}
    <div class="cp-view-drawer__body">

      {{-- Quick meta row --}}
      <div class="cp-vd-meta-row">
        <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;">{{ $quotation->statusLabel() }}</span>
        @if ($quotation->isSent())
          <span class="cp-badge" style="background:#22c55e20;color:#15803d;"><i class="fa fa-check"></i> Sent</span>
        @endif
        @if ($quotation->isSent())
          <span class="cp-vd-meta-hint">Sent {{ \App\Models\ClientProject::formatDateTime($quotation->sent_at) }} · v{{ $quotation->version }}</span>
        @endif
        <span class="cp-vd-meta-hint">Saved {{ \App\Models\ClientProject::formatDateTime($quotation->updated_at) }}</span>
      </div>

      {{-- Work description (shown for all types) --}}
      @if (filled(trim((string) $quotation->work_description)))
        <div class="cp-vd-section">
          <div class="cp-vd-section__title">Description of work</div>
          <div class="cp-vd-field-value cp-vd-field-value--multiline">{!! nl2br(e($quotation->work_description)) !!}</div>
        </div>
      @endif

      {{-- ─── Type-specific details ───────────────────────────────────── --}}
      @if ($sourceModel !== null)
        @switch($sourceType)

          @case(ClientProjectQuotation::SOURCE_PAVING)
            @include('admin.client_projects._quotation_view_paving', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_FENCE)
            @include('admin.client_projects._quotation_view_fence', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_STANDARD)
            @include('admin.client_projects._quotation_view_standard', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_STAIRS)
            @include('admin.client_projects._quotation_view_stairs', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_PAINTING)
            @include('admin.client_projects._quotation_view_painting', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_FLOORING)
            @include('admin.client_projects._quotation_view_flooring', ['sourceModel' => $sourceModel])
          @break

          @case(ClientProjectQuotation::SOURCE_TILE)
            @include('admin.client_projects._quotation_view_tile', ['sourceModel' => $sourceModel])
          @break

          @default
            <div class="cp-vd-section">
              <p class="admin-form-help">No additional calculator details available for this quotation type.</p>
            </div>

        @endswitch
      @else
        <div class="cp-vd-section">
          <p class="admin-form-help">
            @if ($quotation->isLegacyManualSource())
              This is a read-only legacy document. Calculator details are not available.
            @else
              Calculator details are not available for this quotation.
            @endif
          </p>
        </div>
      @endif

      {{-- ─── Commute expense ─────────────────────────────────────────── --}}
      @php
        $hasCommute = $sourceModel !== null
          && property_exists($sourceModel, 'include_commute')
          && $sourceModel->include_commute
          && (float) ($sourceModel->commute_amount ?? 0) > 0;
      @endphp
      @if ($hasCommute)
        <div class="cp-vd-section">
          <div class="cp-vd-section__title"><i class="fa fa-road" aria-hidden="true"></i> Commute expense</div>
          <div class="cp-vd-grid">
            @php
              $tripPct  = (float) ($sourceModel->commute_trip_percent ?? 100);
              $trips    = (int) ($sourceModel->commute_estimated_trips ?? 1);
              $rtCost   = (float) ($sourceModel->commute_round_trip_cost ?? 0);
              $comAmt   = (float) ($sourceModel->commute_amount ?? 0);
            @endphp
            @if ($rtCost > 0)
              <div class="cp-vd-field">
                <span class="cp-vd-field__label">Round-trip fuel</span>
                <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($rtCost) }}</span>
              </div>
            @endif
            <div class="cp-vd-field">
              <span class="cp-vd-field__label">Charge %</span>
              <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($tripPct, 2), '0'), '.') }}%</span>
            </div>
            <div class="cp-vd-field">
              <span class="cp-vd-field__label">Estimated trips</span>
              <span class="cp-vd-field__value">{{ $trips }}</span>
            </div>
            <div class="cp-vd-field cp-vd-field--highlight">
              <span class="cp-vd-field__label">Added to quotation (incl. HST)</span>
              <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney($comAmt) }}</span>
            </div>
          </div>
        </div>
      @endif

      {{-- ─── Totals summary ──────────────────────────────────────────── --}}
      @php
        $hasContent = $quotation->hasContent();
        $items      = $hasContent ? $quotation->pdfLineItems() : [];
        $subtotal   = $hasContent ? $quotation->subtotal() : 0.0;
        $hst        = $hasContent ? $quotation->hst() : 0.0;
        $grand      = $hasContent ? $quotation->grandTotal() : 0.0;
      @endphp
      @if ($hasContent)
        <div class="cp-vd-section">
          <div class="cp-vd-section__title">Quotation totals</div>
          <table class="cp-vd-totals-table">
            <thead>
              <tr>
                <th>Description</th>
                <th class="cp-vd-totals-table__hst">HST</th>
                <th class="cp-vd-totals-table__amt">Amount</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($items as $row)
                <tr>
                  <td>{!! nl2br(e($row['description'])) !!}</td>
                  <td class="cp-vd-totals-table__hst">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
                  <td class="cp-vd-totals-table__amt">{{ \App\Models\ClientProject::formatMoney($row['amount']) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td class="cp-vd-totals-table__footer-label" colspan="2">Subtotal</td>
                <td class="cp-vd-totals-table__amt">{{ \App\Models\ClientProject::formatMoney($subtotal) }}</td>
              </tr>
              <tr>
                <td class="cp-vd-totals-table__footer-label" colspan="2">HST (13% on marked lines)</td>
                <td class="cp-vd-totals-table__amt">{{ \App\Models\ClientProject::formatMoney($hst) }}</td>
              </tr>
              <tr class="cp-vd-totals-table__grand">
                <td class="cp-vd-totals-table__footer-label" colspan="2">Total with HST</td>
                <td class="cp-vd-totals-table__amt">{{ \App\Models\ClientProject::formatMoney($grand) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif

      {{-- Footer action row --}}
      <div class="cp-vd-footer-actions">
        @unless ($quotation->isLegacyManualSource())
          <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
             class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-pencil" aria-hidden="true"></i> Edit in calculator
          </a>
        @endunless
        @if ($hasContent)
          <a href="{{ route('admin.client-projects.quotation.pdf', [$project, $quotation]) }}"
             target="_blank" rel="noopener"
             class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-download" aria-hidden="true"></i> Download PDF
          </a>
        @endif
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm"
                onclick="cpCloseQuotationDrawer('{{ $drawerId }}')">
          Close
        </button>
      </div>

    </div>{{-- /.cp-view-drawer__body --}}
  </div>{{-- /.cp-view-drawer__panel --}}
</div>{{-- /.cp-view-drawer --}}

@once
  {{-- ─── Shared CSS & JS (rendered once per page regardless of quotation count) --}}
  @push('head')
    <style>
      /* ── Slide-over drawer ───────────────────────────────────────────── */
      .cp-view-drawer {
        position: fixed;
        inset: 0;
        z-index: 1050;
        display: flex;
        justify-content: flex-end;
      }
      .cp-view-drawer[hidden] { display: none; }
      .cp-view-drawer.is-open { display: flex; }

      .cp-view-drawer__overlay {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        cursor: pointer;
        animation: cpVdFadeIn 0.18s ease;
      }

      .cp-view-drawer__panel {
        position: relative;
        width: min(620px, 100vw);
        height: 100%;
        background: #fff;
        display: flex;
        flex-direction: column;
        box-shadow: -6px 0 32px rgba(0,0,0,0.18);
        animation: cpVdSlideIn 0.22s cubic-bezier(.22,.68,0,1.2);
        overflow: hidden;
      }

      @keyframes cpVdFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
      }
      @keyframes cpVdSlideIn {
        from { transform: translateX(100%); }
        to   { transform: translateX(0); }
      }

      /* Header */
      .cp-view-drawer__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 20px 24px 16px;
        border-bottom: 1px solid #e5e7eb;
        flex-shrink: 0;
      }
      .cp-view-drawer__header-text { flex: 1 1 0; min-width: 0; }
      .cp-view-drawer__type-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #6b7280;
        display: block;
        margin-bottom: 4px;
      }
      .cp-view-drawer__title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
      }
      .cp-view-drawer__subtitle {
        margin: 4px 0 0;
        font-size: 13px;
        color: #6b7280;
      }
      .cp-view-drawer__close {
        flex-shrink: 0;
        background: none;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #6b7280;
        font-size: 14px;
        transition: background 0.15s, color 0.15s;
      }
      .cp-view-drawer__close:hover { background: #f3f4f6; color: #111827; }

      /* Body */
      .cp-view-drawer__body {
        flex: 1 1 0;
        overflow-y: auto;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
      }

      /* Meta row */
      .cp-vd-meta-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
      }
      .cp-vd-meta-hint {
        font-size: 12px;
        color: #9ca3af;
      }

      /* Section */
      .cp-vd-section {
        border-top: 1px solid #e5e7eb;
        padding-top: 16px;
      }
      .cp-vd-section__title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        margin: 0 0 12px;
      }

      /* Field grid */
      .cp-vd-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px 16px;
      }
      .cp-vd-field {
        display: flex;
        flex-direction: column;
        gap: 2px;
      }
      .cp-vd-field__label {
        font-size: 11px;
        font-weight: 600;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }
      .cp-vd-field__value {
        font-size: 14px;
        color: #111827;
        font-weight: 500;
      }
      .cp-vd-field--full { grid-column: 1 / -1; }
      .cp-vd-field--highlight .cp-vd-field__value {
        color: #024768;
        font-weight: 700;
        font-size: 15px;
      }

      .cp-vd-field-value--multiline {
        font-size: 14px;
        color: #374151;
        line-height: 1.6;
        white-space: pre-wrap;
      }

      /* Checkbox-style display */
      .cp-vd-bool {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
        color: #374151;
      }
      .cp-vd-bool .fa-check-square { color: #16a34a; }
      .cp-vd-bool .fa-square-o { color: #9ca3af; }

      /* Sides list (fence) */
      .cp-vd-sides-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
      }
      .cp-vd-side-chip {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 13px;
        color: #374151;
        font-weight: 500;
      }

      /* Materials table (standard) */
      .cp-vd-materials-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
      }
      .cp-vd-materials-table th,
      .cp-vd-materials-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #f0f2f5;
        text-align: left;
        vertical-align: top;
      }
      .cp-vd-materials-table th {
        background: #f8fafc;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
      }
      .cp-vd-materials-table .num { text-align: right; font-variant-numeric: tabular-nums; }

      /* Totals table */
      .cp-vd-totals-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
      }
      .cp-vd-totals-table th,
      .cp-vd-totals-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #f0f2f5;
        text-align: left;
        vertical-align: top;
      }
      .cp-vd-totals-table th {
        background: #f8fafc;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
      }
      .cp-vd-totals-table__hst,
      .cp-vd-totals-table__amt {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        width: 64px;
      }
      .cp-vd-totals-table__footer-label {
        color: #6b7280;
        font-size: 12px;
      }
      .cp-vd-totals-table__grand td {
        font-weight: 700;
        color: #024768;
        font-size: 15px;
        border-top: 2px solid #024768;
        padding-top: 10px;
      }
      .cp-vd-totals-table__grand .cp-vd-totals-table__footer-label { color: #024768; }

      /* Footer actions */
      .cp-vd-footer-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        border-top: 1px solid #e5e7eb;
        padding-top: 16px;
        margin-top: auto;
      }
    </style>
  @endpush

  @push('scripts')
    <script>
      function cpOpenQuotationDrawer(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.removeAttribute('hidden');
        el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        // Focus close button for accessibility
        var closeBtn = el.querySelector('.cp-view-drawer__close');
        if (closeBtn) closeBtn.focus();
      }
      function cpCloseQuotationDrawer(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('is-open');
        el.setAttribute('hidden', '');
        document.body.style.overflow = '';
      }
      // Close on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.cp-view-drawer.is-open').forEach(function(el) {
          cpCloseQuotationDrawer(el.id);
        });
      });
    </script>
  @endpush
@endonce
