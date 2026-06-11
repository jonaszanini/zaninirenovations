{{--
  Quotation tab: list every ClientProjectQuotation (linked fence / standard /
  paving documents, plus archived legacy manual rows).

  Variables: $project (ClientProject)
--}}

@php
  use App\Models\ClientProjectQuotation;

  $quotationRows = $project->quotations->sortBy('id');
  $acceptedGrand = \App\Models\ClientProjectQuotation::acceptedGrandTotalForProject($project);
  $missingDelivery = $project->missingQuotationDeliveryChannels();
  $canDeliverByEmail = filled(trim((string) $project->client_email));
  $canDeliverBySms = \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) !== null;
  $quotationHub = route('admin.quotations.index', ['project' => $project->id]);
@endphp

<div class="admin-card" style="margin-bottom:1rem;">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
    <div>
      <h3 style="margin:0 0 0.25rem 0;">Quotations</h3>
      <p class="admin-form-help cp-form-help-flush">
        Total of <strong>accepted</strong> quotations (excludes draft / sent / rejected):
        <strong>{{ \App\Models\ClientProject::formatMoney($acceptedGrand) }}</strong>
        @if ($quotationRows->isNotEmpty())
          <span class="cp-contract-version">· {{ $quotationRows->count() }} document{{ $quotationRows->count() === 1 ? '' : 's' }}</span>
        @endif
      </p>
      @if ($quotationRows->count()> 1)
        <p class="admin-form-help" style="margin:8px 0 0;">
          Each quotation is collapsed to a short summary — expand one to view the full PDF-style preview and workflow status.
        </p>
      @endif
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
  @php
    $useQuotationAccordion = $quotationRows->count()> 1;
  @endphp
  @foreach ($quotationRows as $quotation)
    @php
      $hasContent = $quotation->hasContent();
      $canSend = $hasContent && ($canDeliverByEmail || $canDeliverBySms);
      $sendConfirmMessage = $canDeliverByEmail
          ? 'Email this document to '.$project->clientEmailRecipientsLabel().'?'
          : 'Send an SMS with a secure PDF link to '.\App\Models\ClientProject::formatPhone($project->client_phone).'?';
      $typeLabel = $quotation->typeLabel();
      $title = $quotation->resolvedTitle();
      $legacy = $quotation->isLegacyManualSource();
      $deleteConfirm = 'Delete '.$title.' ('.$quotation->quotation_number.') from this project? This also removes the calculator quotation from the Quotations hub.';
      $items = $hasContent ? $quotation->pdfLineItems() : [];
      $subtotal = $hasContent ? $quotation->subtotal() : 0.0;
      $hst = $hasContent ? $quotation->hst() : 0.0;
      $grand = $hasContent ? $quotation->grandTotal() : 0.0;
      $summDesc = $title;
      if ($hasContent && count($items)> 0) {
          $firstDesc = trim((string) ($items[0]['description'] ?? ''));
          if ($firstDesc !== '') {
              $summDesc = \Illuminate\Support\Str::limit($firstDesc, 160);
          }
      }
      if ($summDesc === $title) {
          $wd = trim((string) ($quotation->work_description ?? ''));
          if ($wd !== '') {
              $summDesc = \Illuminate\Support\Str::limit($wd, 160);
          }
      }
      if (! $hasContent) {
          $summDesc = 'No line items yet — open the calculator to add scope.';
      }
      $previewWorkDesc = trim((string) ($quotation->work_description ?? ''));
    @endphp

    @if ($useQuotationAccordion)
      {{-- Compact accordion when several quotations exist on one project --}}
      <details class="cp-quotation-accordion admin-card cp-contract-card">
        <summary class="cp-quotation-accordion__summary">
          <span class="cp-quotation-accordion__chevron admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
          <div class="cp-quotation-accordion__core">
            <div class="cp-quotation-accordion__title-row">
              @include('admin.client_projects._quotation_heading_primary', [
                  'quotation' => $quotation,
                  'title' => $title,
                  'headingClass' => 'cp-quotation-accordion__title',
              ])
              <div class="cp-quotation-accordion__total-block">
                @if ($hasContent)
                  <span class="cp-quotation-accordion__total-hint">Total w/ HST</span>
                  <span class="cp-quotation-accordion__total">{{ \App\Models\ClientProject::formatMoney($grand) }}</span>
                @else
                  <span class="cp-quotation-accordion__total cp-quotation-accordion__total--muted">—</span>
                @endif
              </div>
            </div>
            <p class="cp-quotation-accordion__desc">{{ $summDesc }}</p>
            <div class="cp-quotation-accordion__badges">
              @if ($legacy)
                <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy</span>
              @endif
              <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;">{{ $quotation->statusLabel() }}</span>
              @if ($quotation->isSent())
                <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
                  <i class="fa fa-check"></i> Sent
                </span>
              @else
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Not emailed</span>
              @endif
              <span class="cp-contract-version">{{ $typeLabel }}</span>
            </div>
          </div>
          <div class="cp-quotation-accordion__actions" onclick="event.stopPropagation();">
            @include('admin.client_projects._quotation_toolbar', [
              'project' => $project,
              'quotation' => $quotation,
              'legacy' => $legacy,
              'hasContent' => $hasContent,
              'canSend' => $canSend,
              'sendConfirmMessage' => $sendConfirmMessage,
              'deleteConfirm' => $deleteConfirm,
              'canDeliverByEmail' => $canDeliverByEmail,
              'canDeliverBySms' => $canDeliverBySms,
              'missingDelivery' => $missingDelivery,
            ])
          </div>
        </summary>

        <div class="cp-quotation-accordion__panel">
          <div class="cp-contract-meta-row cp-quotation-accordion__panel-meta">
            @if ($quotation->isSent())
              <span class="cp-contract-version">Sent {{ \App\Models\ClientProject::formatDateTime($quotation->sent_at) }} · Version {{ $quotation->version }}</span>
            @endif
            <span class="cp-contract-version">Last saved {{ \App\Models\ClientProject::formatDateTime($quotation->updated_at) }}</span>
          </div>

          @include('admin.client_projects._quotation_controls', [
              'project' => $project,
              'quotation' => $quotation,
              'legacy' => $legacy,
          ])

          @include('admin.client_projects._quotation_pdf_photos_form', [
              'project' => $project,
              'quotation' => $quotation,
              'legacy' => $legacy,
          ])

          @if (!$canDeliverByEmail && !$canDeliverBySms && ! $legacy)
            <div class="cp-contract-warning">
              <i class="fa fa-exclamation-triangle"></i>
              <div>
                <strong>Cannot send until at least one delivery channel exists (email or SMS-capable phone).</strong>
                <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project</a>
              </div>
            </div>
          @endif

          @if ($hasContent)
            <div class="cp-contract-preview cp-quotation-preview">
              <div class="cp-quotation-doc-meta">
                <div>
                  @include('admin.client_projects._quotation_preview_doc_heading', [
                      'quotation' => $quotation,
                      'typeLabel' => $typeLabel,
                  ])
                  <div class="cp-quotation-doc-date">Date: {{ \App\Support\EasternStandardTimeDisplay::todayLongDate() }}</div>
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

              @include('admin.client_projects._quotation_work_description', ['previewWorkDesc' => $previewWorkDesc])

              @if ($quotation->hasSubtotalOverrideActive())
                <p class="admin-form-help cp-breakdown-intro">
                  Subtotal below is overridden. Line items still reflect calculator totals (sum
                  {{ \App\Models\ClientProject::formatMoney($quotation->lineItemsSubtotal()) }}).
                </p>
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
                      <td class="cp-quotation-hst-col">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
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
          @else
            <div class="cp-quotation-accordion__empty-body">
              @if ($legacy)
                <p class="admin-form-help">Legacy document with no line items on file.</p>
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
        </div>
      </details>
    @elseif ($hasContent)
      <div class="admin-card cp-contract-card cp-quotation-card" style="margin-bottom:1.25rem;">
        <div class="cp-quotation-card__top">
          <div class="cp-contract-meta">
            @include('admin.client_projects._quotation_heading_primary', [
                'quotation' => $quotation,
                'title' => $title,
                'headingClass' => '',
            ])
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

          @include('admin.client_projects._quotation_toolbar', [
            'project' => $project,
            'quotation' => $quotation,
            'legacy' => $legacy,
            'hasContent' => $hasContent,
            'canSend' => $canSend,
            'sendConfirmMessage' => $sendConfirmMessage,
            'deleteConfirm' => $deleteConfirm,
            'canDeliverByEmail' => $canDeliverByEmail,
            'canDeliverBySms' => $canDeliverBySms,
            'missingDelivery' => $missingDelivery,
          ])
        </div>

        @include('admin.client_projects._quotation_controls', [
            'project' => $project,
            'quotation' => $quotation,
            'legacy' => $legacy,
        ])

        @include('admin.client_projects._quotation_pdf_photos_form', [
            'project' => $project,
            'quotation' => $quotation,
            'legacy' => $legacy,
        ])

        @if (!$canDeliverByEmail && !$canDeliverBySms && ! $legacy)
          <div class="cp-contract-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <div>
              <strong>Cannot send until at least one delivery channel exists (email or SMS-capable phone).</strong>
              <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project</a>
            </div>
          </div>
        @endif

        <div class="cp-contract-preview cp-quotation-preview">
          <div class="cp-quotation-doc-meta">
            <div>
              @include('admin.client_projects._quotation_preview_doc_heading', [
                  'quotation' => $quotation,
                  'typeLabel' => $typeLabel,
              ])
              <div class="cp-quotation-doc-date">Date: {{ \App\Support\EasternStandardTimeDisplay::todayLongDate() }}</div>
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

          @include('admin.client_projects._quotation_work_description', ['previewWorkDesc' => $previewWorkDesc])

          @if ($quotation->hasSubtotalOverrideActive())
            <p class="admin-form-help cp-breakdown-intro">
              Subtotal below is overridden. Line items still reflect calculator totals (sum
              {{ \App\Models\ClientProject::formatMoney($quotation->lineItemsSubtotal()) }}).
            </p>
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
                  <td class="cp-quotation-hst-col">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
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
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
            <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
               class="admin-btn admin-btn--primary admin-btn--sm">
              <i class="fa fa-pencil"></i> Edit in calculator
            </a>
            @unless ($legacy)
              <form action="{{ route('admin.client-projects.quotation.destroy', [$project, $quotation]) }}"
                    method="post"
                    class="cp-inline-form"
                    onsubmit='return confirm(@json($deleteConfirm));'>
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                  <i class="fa fa-trash"></i> Delete
                </button>
              </form>
            @endunless
          </div>
        @endif
      </div>
    @endif
  @endforeach
@endif
