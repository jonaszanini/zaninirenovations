{{--
  Contract tab partial. Shows either:
    - an empty state with a big "Create Contract" button (if no contract yet),
    - or the rendered contract preview with edit/download/send actions.

  Variables expected:
    $project    – the ClientProject
    $contract   – the ClientProjectContract or null

  Validation: the "Send" button is disabled (and a warning displayed) until
  client_name, client_phone, client_email and client_address are all set.
--}}

@php
  $contract = $project->contract;
  $hasContent = $contract && $contract->hasContent();
  $missing = $project->missingContactFieldsForContract();
  $canSend = empty($missing) && $hasContent;
@endphp

@if (!$contract || !$hasContent)
  {{-- Empty state ----------------------------------------------------- --}}
  <div class="admin-card cp-contract-empty">
    <h3>Contract</h3>
    <p class="admin-form-help">
      Create the contract for this project. You'll be able to paste from Word
      or Google Docs and fully format the body — headings, lists, tables,
      bold, etc. Once it's ready you can download a PDF or email it directly
      to the client.
    </p>
    <div class="cp-contract-empty-action">
      <a href="{{ route('admin.client-projects.contract.edit', $project) }}"
         class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-file-text-o"></i> Create Contract
      </a>
    </div>
  </div>
@else
  {{-- Existing contract: header bar + preview ------------------------- --}}
  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>Contract</h3>
        <div class="cp-contract-meta-row">
          @if ($contract->isSent())
            <span class="cp-badge" style="background:#22c55e20;color:#15803d;">
              <i class="fa fa-check"></i> Sent {{ \App\Models\ClientProject::formatDateTime($contract->sent_at) }}
            </span>
            <span class="cp-contract-version">Version {{ $contract->version }}</span>
          @else
            <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Draft — not sent yet</span>
          @endif
          <span class="cp-contract-version">
            Last saved {{ \App\Models\ClientProject::formatDateTime($contract->updated_at) }}
          </span>
        </div>
      </div>

      <div class="cp-contract-actions">
        <a href="{{ route('admin.client-projects.contract.edit', $project) }}"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-pencil"></i> Edit Contract
        </a>
        <a href="{{ route('admin.client-projects.contract.pdf', $project) }}"
           target="_blank"
           rel="noopener"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-download"></i> Download PDF
        </a>

        @if ($canSend)
          <form action="{{ route('admin.client-projects.contract.send', $project) }}"
                method="post"
                class="cp-inline-form"
                onsubmit="return confirm('This will generate a new PDF, email it to the client, and save the PDF on the server. Continue?');">
            @csrf
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
              <i class="fa fa-paper-plane"></i>
              {{ $contract->isSent() ? 'Resend to Client' : 'Send to Client' }}
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--primary admin-btn--sm"
                  disabled
                  title="{{ empty($missing) ? 'Write the contract body first.' : 'Missing client info: '.implode(', ', $missing) }}">
            <i class="fa fa-paper-plane"></i> Send to Client
          </button>
        @endif
      </div>
    </div>

    @if (!empty($missing))
      <div class="cp-contract-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div>
          <strong>Cannot email this contract yet.</strong>
          The following client info is missing:
          <strong>{{ implode(', ', $missing) }}</strong>.
          <a href="{{ route('admin.client-projects.edit', $project) }}">
            Edit project details to add it.
          </a>
        </div>
      </div>
    @endif

    {{-- Inline preview of the contract body. Wrapped in .cp-contract-preview
         which scopes typography/spacing so the body looks like a document. --}}
    <div class="cp-contract-preview">
      <div class="cp-contract-preview-parties">
        <p style="margin:0 0 8px;">This Contract is entered into between:</p>
        <table class="cp-contract-parties-table">
          <tr>
            <td>
              <div class="cp-party-label">CONTRACTOR</div>
              <div class="cp-party-name">Jonas Zanini</div>
              <div><strong>ZANINI RENOVATIONS</strong></div>
              <div>53 Antonakos Dr, Carleton Place, ON K7C 0L1</div>
              <div>Phone: 647 834 7570</div>
              <div>Email: jonaszanini@gmail.com</div>
            </td>
            <td>
              <div class="cp-party-label">CLIENT</div>
              <div class="cp-party-name">{{ $project->client_name ?: '—' }}</div>
              <div>Phone: {{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</div>
              <div>Service Address: {{ $project->client_address ?: '—' }}</div>
              <div>Email: {{ $project->client_email ?: '—' }}</div>
            </td>
          </tr>
        </table>
      </div>

      <div class="cp-contract-preview-body">
        {!! $contract->html_content !!}
      </div>

      <div class="cp-contract-preview-signatures">
        <table>
          <tr>
            <td>
              <div class="cp-sig-line"></div>
              <div>Jonas Zanini — ZANINI RENOVATIONS</div>
              <div class="cp-sig-date">Date: ____________________</div>
            </td>
            <td>
              <div class="cp-sig-line"></div>
              <div>{{ $project->client_name ?: '____________________' }} — Client</div>
              <div class="cp-sig-date">Date: ____________________</div>
            </td>
          </tr>
        </table>
        <p class="cp-contract-entire">
          This contract represents the entire agreement between the parties and supersedes any prior negotiations or agreements. This contract may only be modified in writing and signed by both parties.
        </p>
      </div>
    </div>
  </div>
@endif
