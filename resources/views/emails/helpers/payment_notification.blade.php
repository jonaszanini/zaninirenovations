@php
  use App\Models\ClientProject;
  use App\Models\ClientProjectHelperTimeEntry;

  $entries = $payment->timeEntries
      ->sortBy([
          fn ($e) => $e->occurred_on?->format('Y-m-d') ?? '9999',
          'id',
      ])
      ->values();

  $fmtMoney = fn ($n) => ClientProject::formatMoney((float) $n);
  $timeEntries = $entries->filter(fn ($e) => ! $e->isExpense());
  $expenseEntries = $entries->filter(fn ($e) => $e->isExpense());
  $hasMixedRates = $timeEntries
      ->map(fn ($e) => $e->effectiveHourlyRate())
      ->unique()
      ->count() > 1;
@endphp

@component('emails.client_projects.layout', ['title' => 'Payment notification'])
  <p>Hi {{ $helper->name }},</p>

  <p>
    This confirms payment for your work on
    <strong>{{ $project->name }}</strong>.
  </p>

  <p style="margin:12px 0;">
    <strong>Payment method:</strong> {{ $payment->paymentMethodLabel() }}<br>
    <strong>Paid on:</strong> {{ ClientProject::formatDate($payment->paid_at) }}<br>
    @if ((float) $payment->total_hours > 0)
      <strong>Total hours:</strong> {{ number_format((float) $payment->total_hours, 2) }}<br>
      @if (! $hasMixedRates && (float) $payment->hourly_rate > 0)
        <strong>Rate:</strong> {{ $fmtMoney((float) $payment->hourly_rate) }} / hour<br>
      @endif
    @endif
    <strong>Amount paid:</strong> {{ $fmtMoney((float) $payment->total_amount) }}
  </p>

  @if ($timeEntries->isNotEmpty())
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px;">
      <thead>
        <tr>
          <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Date</th>
          <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Start</th>
          <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">End</th>
          <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Hours</th>
          @if ($hasMixedRates)
            <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Rate</th>
          @endif
          <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($timeEntries as $entry)
          <tr>
            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProject::formatDate($entry->occurred_on) }}</td>
            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on) }}</td>
            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on) }}</td>
            <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ number_format((float) $entry->hours, 2) }}</td>
            @if ($hasMixedRates)
              <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $fmtMoney($entry->effectiveHourlyRate()) }}</td>
            @endif
            <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $fmtMoney($entry->lineAmount()) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if ($expenseEntries->isNotEmpty())
    <p style="margin:16px 0 8px;font-weight:600;">Reimbursements</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 16px;font-size:14px;">
      <thead>
        <tr>
          <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Date</th>
          <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Description</th>
          <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($expenseEntries as $entry)
          <tr>
            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProject::formatDate($entry->occurred_on) }}</td>
            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $entry->expense_description }}</td>
            <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $fmtMoney($entry->lineAmount()) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <p style="margin-top:20px;">
    Please confirm you received this payment by replying to this email with
    <strong>“I received”</strong> or <strong>“Amount received”</strong>,
    or use the button below:
  </p>

  <p style="text-align:center;margin:24px 0;">
    <a href="{{ $acknowledgeUrl }}"
       style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;">
      Confirm payment received
    </a>
  </p>

  <p style="font-size:13px;color:#6b7280;">
    If you have any questions about this payment, reply to this email and we will get back to you.
  </p>
@endcomponent
