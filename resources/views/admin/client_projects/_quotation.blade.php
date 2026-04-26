{{--
  Quotation tab partial. Shows either:
    - an empty state with a big "Create Quotation" button (if no quotation yet),
    - or the rendered quotation preview with edit/download/send actions.

  Variables expected:
    $project    – the ClientProject

  Validation: the "Send" button is disabled (and a warning displayed) until
  client_name, client_phone, client_email and client_address are all set
  AND the quotation has at least one line item.
--}}

@php
  $quotation = $project->quotation;
  $hasContent = $quotation && $quotation->hasContent();
  $missing = $project->missingClientContactFields();
  $canSend = empty($missing) && $hasContent;
@endphp

@if (!$quotation || !$hasContent)
  {{-- Empty state ----------------------------------------------------- --}}
  <div class="admin-card cp-contract-empty">
    <h3>Quotation</h3>
    <p class="admin-form-help">
      Create the quotation (or estimate) for this project. Add line items
      with the price excluding HST. Apply 13&nbsp;% HST per line (defaults on);
      uncheck for tax-exempt lines. When you're ready, you can download a
      PDF or email it directly to the client.
    </p>
    <div class="cp-contract-empty-action">
      <a href="{{ route('admin.client-projects.quotation.edit', $project) }}"
         class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-file-text-o"></i> Create Quotation
      </a>
    </div>
  </div>
@else
  {{-- Existing quotation: header bar + preview ----------------------- --}}
  @php
    $items = $quotation->cleanItems();
    $subtotal = $quotation->subtotal();
    $hst = $quotation->hst();
    $grand = $quotation->grandTotal();
    $typeLabel = $quotation->typeLabel();
  @endphp

  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>{{ $typeLabel }} <span class="cp-quotation-number">{{ $quotation->quotation_number }}</span></h3>
        <div class="cp-contract-meta-row">
          @if ($quotation->isSent())
            <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
              <i class="fa fa-check"></i> Sent {{ \App\Models\ClientProject::formatDateTime($quotation->sent_at) }}
            </span>
            <span class="cp-contract-version">Version {{ $quotation->version }}</span>
          @else
            <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Draft — not sent yet</span>
          @endif
          <span class="cp-contract-version">
            Last saved {{ \App\Models\ClientProject::formatDateTime($quotation->updated_at) }}
          </span>
        </div>
      </div>

      <div class="cp-contract-actions">
        <a href="{{ route('admin.client-projects.quotation.edit', $project) }}"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-pencil"></i> Edit {{ $typeLabel }}
        </a>
        <a href="{{ route('admin.client-projects.quotation.pdf', $project) }}"
           target="_blank"
           rel="noopener"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-download"></i> Download PDF
        </a>

        @if ($canSend)
          <form action="{{ route('admin.client-projects.quotation.send', $project) }}"
                method="post"
                class="cp-inline-form"
                onsubmit="return confirm('This will generate a new PDF, email it to the client, and save the PDF on the server. Continue?');">
            @csrf
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
              <i class="fa fa-paper-plane"></i>
              {{ $quotation->isSent() ? 'Resend to Client' : 'Send to Client' }}
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--primary admin-btn--sm"
                  disabled
                  title="{{ empty($missing) ? 'Add at least one line item first.' : 'Missing client info: '.implode(', ', $missing) }}">
            <i class="fa fa-paper-plane"></i> Send to Client
          </button>
        @endif
      </div>
    </div>

    @if (!empty($missing))
      <div class="cp-contract-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div>
          <strong>Cannot email this {{ strtolower($typeLabel) }} yet.</strong>
          The following client info is missing:
          <strong>{{ implode(', ', $missing) }}</strong>.
          <a href="{{ route('admin.client-projects.edit', $project) }}">
            Edit project details to add it.
          </a>
        </div>
      </div>
    @endif

    {{-- Inline preview of the quotation, mirroring what the PDF looks like. --}}
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
              <td>{{ $row['description'] }}</td>
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

      <div class="cp-quotation-closing">
        <ul>
          <li>Prices are firm for 10 days.</li>
          <li>Work schedule is based on weather permitting days (if outside work).</li>
          <li>Zanini Renovations carries a liability insurance.</li>
          <li>Warrantee on workmanship: 1 year.</li>
          <li>Warrantee on workmanship: damages caused by external forces (&ldquo;Act of God&rdquo;) such as weather are not covered under warrantee.</li>
          <li>If you have any questions, please do not hesitate to call.</li>
        </ul>
        <p>We trust this quote meets with your approval and we are favoured with your order.</p>
        <p>
          Best regards,<br>
          Jonas Zanini<br>
          Tel: (647) 834-7570<br>
          Zanini Renovations<br>
          E-mail: zaninirenovations@gmail.com<br>
          Website: www.zaninirenovations.ca
        </p>
      </div>
    </div>
  </div>
@endif
