{{--
  Full-width send-blocker note under an invoice table row, so the yellow
  tip never sits in the Actions cell (where it covered ⋯ / STATUS).

  $colspan — number of columns in the parent table.
--}}
@php
  use App\Models\ClientProjectInvoice;
  use App\Services\MoceanSmsService;

  $warnInvoice = $invoice ?? null;
  $warnCanSend = true;
  $warnCanSms = true;
  $warnNeedsDueDate = false;
  $warnNeedsInvoiceEdit = false;
  $warnNeedsProjectEdit = false;
  $warnBlockersForDisplay = [];

  if ($warnInvoice && ! $warnInvoice->isPaid()) {
      $moceanReady = app(MoceanSmsService::class)->isConfigured();
      $contentBlockers = [];
      $completeItems = array_filter(
          $warnInvoice->cleanItems(),
          fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
      );
      if (count($completeItems) === 0) {
          $contentBlockers[] = 'Add at least one line item with a description and amount.';
      }
      $warnNeedsDueDate = empty($warnInvoice->due_on);
      if ($warnNeedsDueDate) {
          $contentBlockers[] = 'Set a payment due date.';
      }

      $contactBlockersForEmail = [];
      if (blank(trim((string) $project->client_email))) {
          $contactBlockersForEmail[] = "Add the client's email address.";
      }

      $sendBlockers = array_merge($contactBlockersForEmail, $contentBlockers);
      $warnCanSend = empty($sendBlockers);

      $smsBlockers = $contentBlockers;
      if (! $moceanReady) {
          $smsBlockers[] = 'SMS gateway is not configured on the server (MOCEAN_API_TOKEN).';
      }
      if (\App\Models\ClientProject::phoneDigitsForSms($project->client_phone) === null) {
          $smsBlockers[] = "Add the client's mobile phone number.";
      }
      $warnCanSms = empty($smsBlockers);

      $warnBlockersForDisplay = array_values(array_filter(
          array_values(array_unique(array_merge($sendBlockers, $smsBlockers))),
          fn (string $hint) => ! str_contains(strtolower($hint), 'payment due date')
      ));
      $warnNeedsInvoiceEdit = count($contentBlockers) > 0;
      $warnNeedsProjectEdit = count($contactBlockersForEmail) > 0
          || \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) === null;
  }
@endphp
@if ($warnInvoice && ! $warnInvoice->isPaid() && (! $warnCanSend || ! $warnCanSms))
  <tr class="cp-invoice-warning-row">
    <td colspan="{{ $colspan }}">
      <div class="cp-contract-warning cp-invoice-send-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div class="cp-invoice-send-warning-body">
          @php
            $disabledChannels = [];
            if (! $warnCanSend) { $disabledChannels[] = 'Email'; }
            if (! $warnCanSms) { $disabledChannels[] = 'SMS'; }
          @endphp
          <p class="cp-invoice-send-warning-lead">
            <strong>{{ implode(' and ', $disabledChannels) }} disabled.</strong> Address the items below:
          </p>
          <ul class="cp-invoice-send-blockers">
            @if ($warnNeedsDueDate)
              <li>Set a <strong>payment due date</strong></li>
            @endif
            @foreach ($warnBlockersForDisplay as $hint)
              <li>{{ $hint }}</li>
            @endforeach
          </ul>
          <p class="cp-invoice-send-warning-actions">
            @if ($warnNeedsInvoiceEdit)
              <a href="{{ route('admin.client-projects.invoices.edit', [$project, $warnInvoice]) }}"
                 class="admin-btn admin-btn--ghost admin-btn--sm cp-invoice-send-warning-link">
                <i class="fa fa-pencil"></i> Edit invoice
              </a>
            @endif
            @if ($warnNeedsProjectEdit)
              <a href="{{ route('admin.client-projects.edit', $project) }}"
                 class="admin-btn admin-btn--ghost admin-btn--sm cp-invoice-send-warning-link">
                <i class="fa fa-pencil"></i> Edit project info
              </a>
            @endif
          </p>
        </div>
      </div>
    </td>
  </tr>
@endif
