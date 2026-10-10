@extends('admin.layouts.app')

@php
  use App\Models\ClientProject;
  use App\Models\EtransferNotice;
@endphp

@section('heading', 'Incoming e-transfers')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-etransfers.css') }}?v={{ @filemtime(public_path('css/admin-etransfers.css')) ?: time() }}">
@endpush

@section('content')
  <div class="et-page">
    <div class="admin-toolbar">
      <p class="admin-toolbar-lede">Match Interac e-Transfers to open invoices, then record the payment in one click. Nothing is marked paid until you confirm.</p>
      <div class="admin-toolbar-actions">
        <button type="button" class="admin-btn admin-btn--primary" data-et-open-paste>
          <i class="fa fa-clipboard" aria-hidden="true"></i> Paste email
        </button>
      </div>
    </div>

    <div class="et-tabs" role="tablist" aria-label="E-transfer status">
      <a href="{{ route('admin.financials.etransfers', ['status' => EtransferNotice::STATUS_PENDING]) }}" class="admin-btn admin-btn--sm {{ $status === EtransferNotice::STATUS_PENDING ? 'admin-btn--primary' : 'admin-btn--ghost' }}">
        Pending ({{ $counts[EtransferNotice::STATUS_PENDING] }})
      </a>
      <a href="{{ route('admin.financials.etransfers', ['status' => EtransferNotice::STATUS_MATCHED]) }}" class="admin-btn admin-btn--sm {{ $status === EtransferNotice::STATUS_MATCHED ? 'admin-btn--primary' : 'admin-btn--ghost' }}">
        Matched ({{ $counts[EtransferNotice::STATUS_MATCHED] }})
      </a>
      <a href="{{ route('admin.financials.etransfers', ['status' => EtransferNotice::STATUS_IGNORED]) }}" class="admin-btn admin-btn--sm {{ $status === EtransferNotice::STATUS_IGNORED ? 'admin-btn--primary' : 'admin-btn--ghost' }}">
        Ignored ({{ $counts[EtransferNotice::STATUS_IGNORED] }})
      </a>
    </div>

    @if ($rows->isEmpty())
      <div class="admin-card et-empty">
        @if ($status === EtransferNotice::STATUS_PENDING)
          <p>No pending e-transfers. Paste a notification email, or wait for the inbox watcher to POST to the API.</p>
        @else
          <p>No {{ $status }} e-transfers.</p>
        @endif
      </div>
    @else
      @foreach ($rows as $row)
        @php
          /** @var \App\Models\EtransferNotice $notice */
          $notice = $row['notice'];
          $best = $row['best'];
          $bestRecordable = ! empty($row['bestRecordable']);
          $alreadyPaid = ! empty($row['alreadyPaid']) || ! empty($best['already_paid']);
          $ambiguous = ! empty($row['ambiguous']);
          $ambiguousReason = $row['ambiguousReason'] ?? null;
        @endphp
        <article class="et-card">
          <div class="et-card__meta">
            <div class="et-card__top">
              <span class="et-card__amount">{{ ClientProject::formatMoney((float) $notice->amount) }} {{ $notice->currency }}</span>
              <span class="et-card__date">{{ optional($notice->received_at)->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
            </div>
            <div class="et-card__sender">
              {{ $notice->sender_name }}
              @if ($notice->sender_email)
                <div class="et-card__sender-email">{{ $notice->sender_email }}</div>
              @endif
            </div>
            @if ($notice->message)
              <p class="et-card__message">{{ $notice->message }}</p>
            @endif
            @if ($notice->reference_number)
              <div class="et-card__ref">Ref {{ $notice->reference_number }}</div>
            @endif
          </div>

          <div class="et-card__match">
            @if ($status === EtransferNotice::STATUS_PENDING)
              @if ($ambiguous)
                <div class="et-card__match-label">
                  Ambiguous match
                  <x-admin.info-tip label="Ambiguous match">
                    @if ($ambiguousReason === 'cited')
                      The memo cites more than one open invoice. Choose which one this payment should cover.
                    @else
                      Two or more open invoices have this amount. Choose one below — nothing is preselected.
                    @endif
                  </x-admin.info-tip>
                </div>
                <div class="et-card__warning" role="status">
                  @if ($ambiguousReason === 'cited')
                    The memo cites several open invoices. This payment may cover more than one — choose which to record.
                  @else
                    Several open invoices match this amount. Choose one to record.
                  @endif
                </div>
              @elseif ($best)
                <div class="et-card__match-label">{{ $alreadyPaid ? 'Invoice in memo' : 'Best match' }}</div>
                <div class="et-card__match-title">
                  {{ $best['invoice_number'] }}
                  · {{ $alreadyPaid ? 'Already paid' : ClientProject::formatMoney($best['outstanding']) }}
                </div>
                <div class="et-card__match-sub">{{ $best['client_name'] }} · {{ $best['project_name'] }}</div>
                <div class="et-chips">
                  @foreach ($best['reasons'] as $reason)
                    <span class="et-chip {{ $reason === 'Already paid' ? 'et-chip--paid' : '' }}">{{ $reason }}</span>
                  @endforeach
                </div>
              @else
                <div class="et-card__match-label">
                  No confident match
                  <x-admin.info-tip label="No confident match">
                    A recent invoice by itself is not enough. We need an invoice number in the memo, a matching amount, or a matching client name or email before suggesting one.
                  </x-admin.info-tip>
                </div>
              @endif
            @elseif ($notice->invoice)
              <div class="et-card__match-label">Recorded on</div>
              <div class="et-card__match-title">{{ $notice->invoice->invoice_number }}</div>
              <div class="et-card__match-sub">
                <a href="{{ route('admin.client-projects.show', $notice->invoice->client_project_id) }}#invoice" class="admin-link">Open invoice</a>
              </div>
            @else
              <div class="et-card__match-sub">No invoice linked.</div>
            @endif
          </div>

          <div class="et-card__actions">
            @if ($status === EtransferNotice::STATUS_PENDING)
              <form method="post" action="{{ route('admin.financials.etransfers.record', $notice) }}" class="et-record-form" id="et-record-{{ $notice->id }}" data-et-record>
                @csrf
                <div class="et-picker">
                  @include('admin.partials.search_select', [
                    'id' => 'et-inv-'.$notice->id,
                    'name' => 'invoice_id',
                    'label' => 'Invoice',
                    'placeholder' => 'Choose a different invoice…',
                    'searchUrl' => route('admin.financials.etransfers.invoices.search'),
                    'optionsSource' => 'et-invoice-options',
                    'selectedOption' => $bestRecordable ? [
                      'id' => $best['invoice_id'],
                      'name' => trim($best['invoice_number'].' · '.$best['client_name']),
                    ] : null,
                    'value' => $bestRecordable ? ($best['invoice_id'] ?? '') : '',
                    'metaKeys' => [],
                    'listParent' => 'body',
                    'emptyMessage' => 'No open invoices',
                    'emptyQueryMessage' => 'Type an invoice number or client',
                  ])
                </div>
              </form>
              <div class="et-card__btns">
                <button type="submit" form="et-record-{{ $notice->id }}" class="admin-btn admin-btn--primary" @disabled(! $bestRecordable)>
                  Record payment
                </button>
                <form method="post" action="{{ route('admin.financials.etransfers.ignore', $notice) }}" class="et-ignore-form">
                  @csrf
                  <button type="submit" class="admin-btn admin-btn--ghost">Ignore</button>
                </form>
              </div>
              @if ($alreadyPaid)
                <div class="et-card__record-note" data-et-record-note>
                  Record disabled — invoice already paid
                  <x-admin.info-tip label="Already paid">
                    This invoice is already marked paid, so Record payment stays off until you pick a different open invoice.
                  </x-admin.info-tip>
                </div>
              @endif
            @endif
          </div>
        </article>
      @endforeach
    @endif
  </div>

  <script type="application/json" id="et-invoice-options">@json($invoiceOptions)</script>

  <div class="cp-modal-backdrop" id="et-paste-modal" @if ($errors->any() && old('raw_text')) @else hidden @endif>
    <div class="cp-modal" role="dialog" aria-labelledby="et-paste-title" aria-modal="true">
      <div class="cp-modal-header et-paste-header">
        <h3 id="et-paste-title">Paste Interac email</h3>
        <button type="button" class="cp-modal-close et-paste-close" data-et-close-paste aria-label="Close">&times;</button>
      </div>
      <form method="post" action="{{ route('admin.financials.etransfers.store') }}" class="et-paste-form">
        @csrf
        <div class="cp-modal-body et-paste-grid">
          <div class="admin-form-group">
            <label for="et-raw-text">Email text</label>
            <textarea id="et-raw-text" name="raw_text" class="admin-input" required placeholder="Paste the Interac notification here">{{ old('raw_text') }}</textarea>
          </div>
          <details class="et-paste-advanced" @if (old('amount') || old('sender_name') || old('sender_email') || old('reference_number') || old('message') || old('received_at')) open @endif>
            <summary>Optional overrides</summary>
            <div class="et-paste-fields">
              <div class="admin-form-group">
                <label for="et-amount">Amount</label>
                <input type="text" id="et-amount" name="amount" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" value="{{ old('amount') }}" placeholder="0.00" data-cp-money-entry="direct">
              </div>
              <div class="admin-form-group">
                <label for="et-ref">Reference</label>
                <input type="text" id="et-ref" name="reference_number" class="admin-input" value="{{ old('reference_number') }}">
              </div>
              <div class="admin-form-group et-paste-fields__sender">
                <label for="et-sender-name">Sender name</label>
                <input type="text" id="et-sender-name" name="sender_name" class="admin-input" value="{{ old('sender_name') }}">
              </div>
              <div class="admin-form-group et-paste-fields__sender">
                <label for="et-sender-email">Sender email</label>
                <input type="email" id="et-sender-email" name="sender_email" class="admin-input" value="{{ old('sender_email') }}">
              </div>
              <div class="admin-form-group et-paste-fields__wide">
                <label for="et-message">Message</label>
                <textarea id="et-message" name="message" class="admin-input" rows="2">{{ old('message') }}</textarea>
              </div>
              <div class="admin-form-group et-paste-fields__wide">
                <label for="et-received">Received</label>
                <input type="datetime-local" id="et-received" name="received_at" class="admin-input" value="{{ old('received_at') }}">
              </div>
            </div>
          </details>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost" data-et-close-paste>Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary">Save notice</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-etransfers.js') }}?v={{ @filemtime(public_path('js/admin-etransfers.js')) ?: time() }}"></script>
@endpush
