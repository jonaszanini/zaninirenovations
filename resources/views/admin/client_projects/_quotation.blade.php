{{--
  Quotation tab: list every ClientProjectQuotation (linked fence / standard /
  paving documents, plus archived legacy manual rows).

  Variables: $project (ClientProject)
--}}

@php
  use App\Models\ClientProjectQuotation;
  use App\Models\FenceQuotation;
  use App\Models\FlooringQuotation;
  use App\Models\PaintingQuotation;
  use App\Models\PavingQuotation;
  use App\Models\StairsQuotation;
  use App\Models\StandardQuotation;
  use App\Models\BasementQuotation;
  use App\Models\ShedQuotation;
  use App\Models\TrimmingQuotation;
  use App\Models\MillworkQuotation;
  use App\Models\TileQuotation;
  use App\Services\MoceanSmsService;
  use Illuminate\Support\Facades\URL;

  $quotationRows = $project->quotations->sortBy('id');
  $acceptedGrand = \App\Models\ClientProjectQuotation::acceptedGrandTotalForProject($project);
  $missingDelivery = $project->missingQuotationDeliveryChannels();
  $canDeliverByEmail = filled(trim((string) $project->client_email));
  $canDeliverBySms = \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) !== null;
  $moceanReady = app(MoceanSmsService::class)->isConfigured();
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
      Create a quotation or estimate from the Fence, Standard, Paving, Painting, Stairs, or Flooring calculators.
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
      $legacy = $quotation->isLegacyManualSource();
      $canSend = $hasContent && $canDeliverByEmail;
      $sendConfirmMessage = 'This will generate a new PDF and email it to '.$project->clientEmailRecipientsLabel().'. Continue?';
      $smsConfirmMessage = 'Send an SMS to '.$project->clientSmsRecipientsLabel().' with a secure link to view or download this quotation PDF?';
      $smsBlockers = [];
      if (! $hasContent) {
          $smsBlockers[] = 'Add at least one line item first.';
      }
      if (! $moceanReady) {
          $smsBlockers[] = 'SMS gateway is not configured on the server (MOCEAN_API_TOKEN).';
      }
      if (! $canDeliverBySms) {
          $smsBlockers[] = "Add the client's mobile phone number.";
      }
      $canSms = empty($smsBlockers) && ! $legacy;
      $smsTitle = $canSms
          ? 'Text the client a secure link to open this quotation PDF in the browser.'
          : implode(' ', $smsBlockers);
      $shareLinkUrl = $hasContent
          ? URL::temporarySignedRoute(
              'public.client-project.quotation-pdf',
              \App\Support\SignedPublicLink::expiresAt(),
              ['client_project' => $project, 'quotation' => $quotation],
          )
          : null;
      $typeLabel = $quotation->typeLabel();
      $title = $quotation->resolvedTitle();
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
              $summDesc = \Illuminate\Support\Str::limit(trim(strip_tags($wd)), 160);
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
          <span class="cp-quotation-accordion__chevron" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
          <div class="cp-quotation-accordion__core">
            <div class="cp-quotation-accordion__title-row">
              <span class="cp-quotation-accordion__title">{{ $title }}</span>
              <span class="cp-quotation-accordion__number">{{ $quotation->quotation_number }}</span>
            </div>
            <p class="cp-quotation-accordion__desc">{{ $summDesc }}</p>
          </div>
          <div class="cp-quotation-accordion__total-block">
            @if ($hasContent)
              <span class="cp-quotation-accordion__total-hint">Total w/ HST</span>
              <span class="cp-quotation-accordion__total">{{ \App\Models\ClientProject::formatMoney($grand) }}</span>
            @else
              <span class="cp-quotation-accordion__total cp-quotation-accordion__total--muted">—</span>
            @endif
          </div>
          <div class="cp-quotation-accordion__actions" onclick="event.stopPropagation();">
            @include('admin.client_projects._quotation_toolbar', [
              'project' => $project,
              'quotation' => $quotation,
              'legacy' => $legacy,
              'hasContent' => $hasContent,
              'canSend' => $canSend,
              'canSms' => $canSms,
              'smsTitle' => $smsTitle,
              'shareLinkUrl' => $shareLinkUrl,
              'sendConfirmMessage' => $sendConfirmMessage,
              'smsConfirmMessage' => $smsConfirmMessage,
              'deleteConfirm' => $deleteConfirm,
              'canDeliverByEmail' => $canDeliverByEmail,
              'canDeliverBySms' => $canDeliverBySms,
              'missingDelivery' => $missingDelivery,
            ])
          </div>
          <div class="cp-quotation-accordion__badges" data-total="{{ $hasContent ? \App\Models\ClientProject::formatMoney($grand) : '—' }}">
            @if ($legacy)
              <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy</span>
            @endif
            <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;">{{ $quotation->statusLabel() }}</span>
            @if ($quotation->acceptedSummary())
              <span class="cp-badge cp-badge-accepted">{{ $quotation->acceptedSummary() }}</span>
            @endif
            @if ($quotation->isSent())
              <span class="cp-badge" style="background:#22c55e20;color:#15803d;"><i class="fa fa-check"></i> Sent</span>
            @else
              <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Not emailed</span>
            @endif
            <span class="cp-contract-version">{{ $typeLabel }}</span>
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
                  <div class="cp-party-name">@include('admin.partials.client_directory_link', ['project' => $project, 'class' => 'cp-party-name__link'])</div>
                  <div>{{ $project->client_address ?: '—' }}</div>
                  <div>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</div>
                  <div>@include('admin.partials.client_directory_link', [
                    'project' => $project,
                    'label' => $project->client_email,
                    'fallbackMailto' => true,
                  ])</div>
                </div>
              </div>

              @php $supplyScope = $quotation->supplyScopePdfMeta(); @endphp
              @if ($supplyScope['show'] ?? false)
                @include('admin.client_projects._quotation_supply_scope', ['quotation' => $quotation])
              @else
                <p class="cp-quotation-intro">{{ $quotation->introPhrase() }}</p>
              @endif

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
                    @if (!empty($row['is_heading']))
                      <tr class="items-heading">
                        <td colspan="3">{!! nl2br(e($row['description'])) !!}</td>
                      </tr>
                    @else
                      <tr class="{{ ! empty($row['is_standalone']) ? 'items-standalone' : '' }}">
                        <td>
                          {!! nl2br(e($row['description'])) !!}
                          @if (!empty($row['hst_included']) && empty($row['apply_hst']))
                            <span class="items-hst-note">HST included</span>
                          @endif
                        </td>
                        <td class="cp-quotation-hst-col">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
                        <td class="amount">{{ \App\Models\ClientProject::formatMoney($row['amount']) }}</td>
                      </tr>
                    @endif
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
              @if ($quotation->acceptedSummary())
                <span class="cp-badge cp-badge-accepted">{{ $quotation->acceptedSummary() }}</span>
              @endif
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
            'canSms' => $canSms,
            'smsTitle' => $smsTitle,
            'shareLinkUrl' => $shareLinkUrl,
            'sendConfirmMessage' => $sendConfirmMessage,
            'smsConfirmMessage' => $smsConfirmMessage,
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
              <div class="cp-party-name">@include('admin.partials.client_directory_link', ['project' => $project, 'class' => 'cp-party-name__link'])</div>
              <div>{{ $project->client_address ?: '—' }}</div>
              <div>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</div>
              <div>@include('admin.partials.client_directory_link', [
                'project' => $project,
                'label' => $project->client_email,
                'fallbackMailto' => true,
              ])</div>
            </div>
          </div>

          @php $supplyScope = $quotation->supplyScopePdfMeta(); @endphp
          @if ($supplyScope['show'] ?? false)
            @include('admin.client_projects._quotation_supply_scope', ['quotation' => $quotation])
          @else
            <p class="cp-quotation-intro">{{ $quotation->introPhrase() }}</p>
          @endif

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
                @if (!empty($row['is_heading']))
                  <tr class="items-heading">
                    <td colspan="3">{!! nl2br(e($row['description'])) !!}</td>
                  </tr>
                @else
                  <tr class="{{ ! empty($row['is_standalone']) ? 'items-standalone' : '' }}">
                    <td>
                      {!! nl2br(e($row['description'])) !!}
                      @if (!empty($row['hst_included']) && empty($row['apply_hst']))
                        <span class="items-hst-note">HST included</span>
                      @endif
                    </td>
                    <td class="cp-quotation-hst-col">{{ \App\Models\ClientProjectQuotation::lineHstColumnLabel($row) }}</td>
                    <td class="amount">{{ \App\Models\ClientProject::formatMoney($row['amount']) }}</td>
                  </tr>
                @endif
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

  {{-- ─── View Drawers (one per quotation, rendered once per quotation, toggled by JS) ─── --}}
  @foreach ($quotationRows as $quotation)
    @php
      $drawerSourceModel = null;
      if ($quotation->source_id) {
          $drawerSourceModel = match ($quotation->source_type ?? '') {
              ClientProjectQuotation::SOURCE_PAVING   => PavingQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_FENCE    => FenceQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_STANDARD => StandardQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_STAIRS   => StairsQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_PAINTING => PaintingQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_FLOORING => FlooringQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_TILE     => TileQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_BASEMENT => BasementQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_SHED     => ShedQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_TRIMMING => TrimmingQuotation::find($quotation->source_id),
              ClientProjectQuotation::SOURCE_MILLWORK => MillworkQuotation::find($quotation->source_id),
              default                                  => null,
          };
      }
    @endphp
    @if ($quotation->hasContent())
      @include('admin.client_projects._quotation_view_drawer', [
          'project'     => $project,
          'quotation'   => $quotation,
          'sourceModel' => $drawerSourceModel,
      ])
    @endif
  @endforeach

  {{-- Duplicate quotation modal --}}
  <div id="cp-duplicate-modal-backdrop" class="cp-modal-backdrop" hidden onclick="cpCloseDuplicateModal()">
    <div class="cp-modal" style="max-width:420px;" onclick="event.stopPropagation();">
      <div class="cp-modal-header">
        <h3>Duplicate quotation</h3>
        <button type="button" class="cp-modal-close" onclick="cpCloseDuplicateModal()">
          <i class="fa fa-times"></i>
        </button>
      </div>
      <form id="cp-duplicate-form" method="post">
        @csrf
        <div class="cp-modal-body">
          <p class="admin-form-help" style="margin:0 0 12px;">
            Create a copy of <strong id="cp-duplicate-number"></strong> as a new draft.
          </p>
          <div class="admin-form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="duplicate_target" value="same" checked onchange="cpToggleDuplicateTarget(this)">
              <span>Same project (<span id="cp-duplicate-project-name">{{ $project->name }}</span>)</span>
            </label>
          </div>
          <div class="admin-form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="duplicate_target" value="other" onchange="cpToggleDuplicateTarget(this)">
              <span>Different project</span>
            </label>
          </div>
          <div id="cp-duplicate-project-picker" class="admin-form-group" style="display:none;margin-top:12px;">
            @include('admin.partials.search_select', [
              'id' => 'cp-duplicate-project-search',
              'name' => 'target_project_id',
              'placeholder' => 'Search projects…',
              'searchUrl' => route('admin.quotations.projects.search'),
              'searchFields' => ['label'],
              'emptyMessage' => 'No projects found',
              'maxOptions' => 20,
            ])
          </div>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost" onclick="cpCloseDuplicateModal()">Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-copy"></i> Duplicate
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  (function() {
    window.cpOpenDuplicateModal = function(projectId, quotationId, quotationNumber, projectName) {
      var backdrop = document.getElementById('cp-duplicate-modal-backdrop');
      var form = document.getElementById('cp-duplicate-form');
      var numberEl = document.getElementById('cp-duplicate-number');

      form.action = '/admin/client-projects/' + projectId + '/quotations/' + quotationId + '/duplicate';
      numberEl.textContent = quotationNumber;

      document.querySelector('input[name="duplicate_target"][value="same"]').checked = true;
      document.getElementById('cp-duplicate-project-picker').style.display = 'none';

      backdrop.hidden = false;
      document.body.classList.add('cp-modal-open');
    };

    window.cpCloseDuplicateModal = function() {
      document.getElementById('cp-duplicate-modal-backdrop').hidden = true;
      document.body.classList.remove('cp-modal-open');
    };

    window.cpToggleDuplicateTarget = function(radio) {
      var picker = document.getElementById('cp-duplicate-project-picker');
      picker.style.display = radio.value === 'other' ? 'block' : 'none';
    };

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        var backdrop = document.getElementById('cp-duplicate-modal-backdrop');
        if (backdrop && !backdrop.hidden) {
          cpCloseDuplicateModal();
        }
      }
    });
  })();
  </script>
@endif
