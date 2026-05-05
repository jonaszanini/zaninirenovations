{{--
  Quotation tab: list every ClientProjectQuotation (linked fence / standard /
  paving documents, plus archived legacy manual rows).

  Variables: $project (ClientProject)
--}}

@php
  use App\Models\ClientProjectQuotation;

  $quotationRows = $project->quotations->sortBy('id');
  $acceptedGrand = \App\Models\ClientProjectQuotation::acceptedGrandTotalForProject($project);
  $missing = $project->missingClientContactFields();
  $quotationHub = route('admin.quotations.index', ['project' => $project->id]);
@endphp

<div class="admin-card" style="margin-bottom:1rem;">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
    <div>
      <h3 style="margin:0 0 0.25rem 0;">Quotations</h3>
      <p class="admin-form-help" style="margin:0;">
        Total of <strong>accepted</strong> quotations (excludes draft / sent / rejected):
        <strong>{{ \App\Models\ClientProject::formatMoney($acceptedGrand) }}</strong>
        @if ($quotationRows->isNotEmpty())
          <span class="cp-contract-version">· {{ $quotationRows->count() }} document{{ $quotationRows->count() === 1 ? '' : 's' }}</span>
        @endif
      </p>
    </div>
    <a href="{{ $quotationHub }}"
       class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> Create quotation
    </a>
  </div>
</div>

@if ($quotationRows->isEmpty())
  <div class="admin-card cp-contract-empty">
    <h3>Quotation</h3>
    <p class="admin-form-help">
      Create a quotation or estimate from the Fence, Standard, or Paving calculators.
      The first time you save, it attaches to this project automatically.
    </p>
    <div class="cp-contract-empty-action">
      <a href="{{ $quotationHub }}"
         class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-th"></i> Choose quotation type
      </a>
    </div>
  </div>
@else
  @foreach ($quotationRows as $quotation)
    @php
      $hasContent = $quotation->hasContent();
      $canSend = empty($missing) && $hasContent;
      $typeLabel = $quotation->typeLabel();
      $title = $quotation->title ?: $typeLabel;
      $legacy = $quotation->isLegacyManualSource();
    @endphp

    @if ($hasContent)
      @php
        $items = $quotation->cleanItems();
        $subtotal = $quotation->subtotal();
        $hst = $quotation->hst();
        $grand = $quotation->grandTotal();
      @endphp

      <div class="admin-card cp-contract-card" style="margin-bottom:1.25rem;">
        <div class="cp-contract-header">
          <div class="cp-contract-meta">
            <h3>
              {{ $title }}
              <span class="cp-quotation-number">{{ $quotation->quotation_number }}</span>
            </h3>
            <div class="cp-contract-meta-row">
              @if ($legacy)
                <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy (read-only)</span>
              @endif
              <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;">{{ $quotation->statusLabel() }}</span>
              @if ($quotation->isSent())
                <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
                  <i class="fa fa-check"></i> Sent {{ \App\Models\ClientProject::formatDateTime($quotation->sent_at) }}
                </span>
                <span class="cp-contract-version">Version {{ $quotation->version }}</span>
              @else
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Not emailed yet</span>
              @endif
              <span class="cp-contract-version">
                Last saved {{ \App\Models\ClientProject::formatDateTime($quotation->updated_at) }}
              </span>
            </div>
          </div>

          <div class="cp-contract-actions">
            @unless ($legacy)
              <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
                 class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-pencil"></i> Edit
              </a>
            @endunless

            @if ($legacy)
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Archived — open a new quotation from Quotations instead.">
                <i class="fa fa-ban"></i> Read-only
              </button>
            @endif

            <a href="{{ route('admin.client-projects.quotation.pdf', [$project, $quotation]) }}"
               target="_blank"
               rel="noopener"
               class="admin-btn admin-btn--ghost admin-btn--sm">
              <i class="fa fa-download"></i> PDF
            </a>

            @if ($canSend && ! $legacy)
              <form action="{{ route('admin.client-projects.quotation.send', [$project, $quotation]) }}"
                    method="post"
                    class="cp-inline-form"
                    onsubmit="return confirm('Email this document to the client?');">
                @csrf
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-paper-plane"></i>
                  {{ $quotation->isSent() ? 'Resend' : 'Send' }}
                </button>
              </form>
            @else
              <button type="button"
                      class="admin-btn admin-btn--primary admin-btn--sm"
                      disabled
                      title="{{ $legacy ? 'Legacy document — not emailable from this flow.' : (empty($missing) ? 'Add at least one line item first.' : 'Missing: '.implode(', ', $missing)) }}">
                <i class="fa fa-paper-plane"></i> Send
              </button>
            @endif
          </div>

          <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e7eb;">
            <form method="post"
                  action="{{ route('admin.client-projects.quotation.status', [$project, $quotation]) }}"
                  class="cp-inline-form"
                  style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              @csrf
              <label for="cp-q-status-{{ $quotation->id }}" class="admin-form-help" style="margin:0;">Workflow status</label>
              <select id="cp-q-status-{{ $quotation->id }}" name="status" class="admin-input" style="width:auto;min-width:160px;" onchange="this.form.submit()">
                <option value="{{ ClientProjectQuotation::STATUS_DRAFT }}" @selected($quotation->status === ClientProjectQuotation::STATUS_DRAFT)>Draft</option>
                <option value="{{ ClientProjectQuotation::STATUS_SENT }}" @selected($quotation->status === ClientProjectQuotation::STATUS_SENT)>Sent</option>
                <option value="{{ ClientProjectQuotation::STATUS_ACCEPTED }}" @selected($quotation->status === ClientProjectQuotation::STATUS_ACCEPTED)>Accepted</option>
                <option value="{{ ClientProjectQuotation::STATUS_REJECTED }}" @selected($quotation->status === ClientProjectQuotation::STATUS_REJECTED)>Rejected</option>
              </select>
            </form>
          </div>

          @if (!empty($missing) && ! $legacy)
            <div class="cp-contract-warning">
              <i class="fa fa-exclamation-triangle"></i>
              <div>
                <strong>Cannot email until client contact details are complete.</strong>
                <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project</a>
              </div>
            </div>
          @endif
        </div>

        <div class="cp-contract-preview cp-quotation-preview">
          <div class="cp-quotation-doc-meta">
            <div>
              <div class="cp-quotation-doc-title">{{ $typeLabel }} #{{ $quotation->quotation_number }}</div>
              <div class="cp-quotation-doc-date">Date: {{ now()->format('F j, Y') }}</div>
            </div>
            <div class="cp-quotation-doc-client">
              <div class="cp-party-label">CLIENT</div>
              <div class="cp-party-name">{{ $project->client_name ?: '—' }}</div>
              <div>{{ $project->client_address ?: '—' }}</div>
              <div>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</div>
              <div>{{ $project->client_email ?: '—' }}</div>
            </div>
          </div>

          <p class="cp-quotation-intro">{{ $quotation->introPhrase() }}</p>

          @php
            $previewWorkDesc = trim((string) ($quotation->work_description ?? ''));
          @endphp
          @if ($previewWorkDesc !== '')
            <div class="cp-quotation-work-description">
              <div class="cp-quotation-work-description-title">Description of the work</div>
              <div class="cp-quotation-work-description-body">{!! nl2br(e($previewWorkDesc)) !!}</div>
            </div>
          @endif

          <table class="cp-quotation-items">
            <thead>
              <tr>
                <th>Description</th>
                <th class="cp-quotation-hst-col">HST</th>
                <th class="amount">Amount</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($items as $row)
                <tr>
                  <td>{!! nl2br(e($row['description'])) !!}</td>
                  <td class="cp-quotation-hst-col">{{ !empty($row['apply_hst']) ? '13%' : '—' }}</td>
                  <td class="amount">{{ \App\Models\ClientProject::formatMoney($row['amount']) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <td class="totals-label" colspan="2">Subtotal</td>
                <td class="amount">{{ \App\Models\ClientProject::formatMoney($subtotal) }}</td>
              </tr>
              <tr>
                <td class="totals-label" colspan="2">HST (13% on marked lines)</td>
                <td class="amount">{{ \App\Models\ClientProject::formatMoney($hst) }}</td>
              </tr>
              <tr class="grand-row">
                <td class="totals-label" colspan="2">Total with HST</td>
                <td class="amount">{{ \App\Models\ClientProject::formatMoney($grand) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    @else
      <div class="admin-card cp-contract-empty" style="margin-bottom:1rem;">
        <h3>{{ $title }}</h3>
        @if ($legacy)
          <p class="admin-form-help">Legacy document with no line items on file. Open a new quotation from the hub if you need to replace it.</p>
          <a href="{{ $quotationHub }}" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-th"></i> Create quotation
          </a>
        @else
          <p class="admin-form-help">No line items yet.</p>
          <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
             class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-pencil"></i> Edit in calculator
          </a>
        @endif
      </div>
    @endif
  @endforeach
@endif
