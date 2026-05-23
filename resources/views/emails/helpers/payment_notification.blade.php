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
    <strong>Rate:</strong> {{ $fmtMoney((float) $payment->hourly_rate) }} / hour<br>
    <strong>Total hours:</strong> {{ number_format((float) $payment->total_hours, 2) }}<br>
    <strong>Amount paid:</strong> {{ $fmtMoney((float) $payment->total_amount) }}
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px;">
    <thead>
      <tr>
        <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Date</th>
        <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">Start</th>
        <th align="left" style="padding:8px;border-bottom:2px solid #e5e7eb;">End</th>
        <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Hours</th>
        <th align="right" style="padding:8px;border-bottom:2px solid #e5e7eb;">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($entries as $entry)
        <tr>
          <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProject::formatDate($entry->occurred_on) }}</td>
          <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on) }}</td>
          <td style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on) }}</td>
          <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ number_format((float) $entry->hours, 2) }}</td>
          <td align="right" style="padding:8px;border-bottom:1px solid #f3f4f6;">{{ $fmtMoney($entry->lineAmount((float) $payment->hourly_rate)) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

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
