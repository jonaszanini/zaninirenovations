@php
  use App\Models\ClientProject;
  use App\Models\ClientProjectTimeEntry;

  $closedEntries = $project->timeEntries
      ->filter(fn ($e) => $e->hours !== null)
      ->sortBy([
          fn ($e) => $e->occurred_on?->format('Y-m-d') ?? '9999',
          'id',
      ])
      ->values();

  $openEntries = $project->timeEntries->filter->isOpen()->sortBy([
      fn ($e) => $e->occurred_on?->format('Y-m-d') ?? '9999',
      'id',
  ])->values();

  $rate = (float) ($project->hourly_rate ?? 0);
  $totalHours = (float) $closedEntries->sum('hours');
  $subtotal = $project->hourlyAmountEarned();
  // Match the admin Time tab: one rounding step on (subtotal × 1.13).
  $grandTotal = $project->hourlyFinalAmountAfterHst();
  $hst = round($grandTotal - $subtotal, 2);

  $fmtMoney = fn ($n) => ClientProject::formatMoney((float) $n);
@endphp

@component('emails.client_projects.layout', ['title' => 'Time log summary'])
  <p>Dear {{ $project->client_name }},</p>

  <p>
    Below is a summary of the time logged on your project,
    <strong>{{ $project->name }}</strong>, as of {{ \App\Models\ClientProject::formatDateTime(now()) }}.
  </p>

  @if (filled($project->client_address))
    <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
      <strong>Service address:</strong> {{ $project->client_address }}
    </p>
  @endif

  <p style="margin:20px 0 8px;font-size:14px;font-weight:700;color:#024768;">Agreed rate</p>
  <p style="margin:0 0 16px;">
    @if ($rate > 0)
      <strong>{{ $fmtMoney($rate) }}</strong> per hour + 13% HST (on labour).
    @else
      Hourly rate is not set in our records; totals below may show as zero until configured.
    @endif
  </p>

  <p style="margin:20px 0 8px;font-size:14px;font-weight:700;color:#024768;">Days worked (closed entries)</p>

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:14px;margin:0 0 20px;">
    <thead>
      <tr style="background:#024768;color:#ffffff;">
        <th align="left" style="padding:10px 8px;">Day</th>
        <th align="left" style="padding:10px 8px;">Date</th>
        <th align="left" style="padding:10px 8px;">Start</th>
        <th align="left" style="padding:10px 8px;">End</th>
        <th align="right" style="padding:10px 8px;">Hrs</th>
        <th align="left" style="padding:10px 8px;">Note</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($closedEntries as $entry)
        <tr style="background:{{ $loop->iteration % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap;">
            @if ($entry->occurred_on)
              {{ $entry->occurred_on->copy()->timezone(config('app.timezone'))->format('D') }}
            @else
              —
            @endif
          </td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProject::formatDate($entry->occurred_on) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProjectTimeEntry::formatTime($entry->start_time) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProjectTimeEntry::formatTime($entry->end_time) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format((float) $entry->hours, 2) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;color:#4b5563;">{{ $entry->note ? \Illuminate\Support\Str::limit($entry->note, 120) : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:15px;margin:0 0 24px;">
    <tr>
      <td style="padding:8px 0;border-top:2px solid #024768;"><strong>Total hours</strong></td>
      <td align="right" style="padding:8px 0;border-top:2px solid #024768;font-variant-numeric:tabular-nums;"><strong>{{ number_format($totalHours, 2) }}</strong></td>
    </tr>
    <tr>
      <td style="padding:8px 0;">Labour subtotal (before HST)</td>
      <td align="right" style="padding:8px 0;font-variant-numeric:tabular-nums;">{{ $fmtMoney($subtotal) }}</td>
    </tr>
    <tr>
      <td style="padding:8px 0;">HST (13%)</td>
      <td align="right" style="padding:8px 0;font-variant-numeric:tabular-nums;">{{ $fmtMoney($hst) }}</td>
    </tr>
    <tr>
      <td style="padding:10px 0 8px;font-size:16px;font-weight:700;color:#024768;border-top:1px solid #d1d5db;">Total with HST</td>
      <td align="right" style="padding:10px 0 8px;font-size:16px;font-weight:700;color:#024768;border-top:1px solid #d1d5db;font-variant-numeric:tabular-nums;">{{ $fmtMoney($grandTotal) }}</td>
    </tr>
  </table>

  @if ($openEntries->isNotEmpty())
    <p style="margin:20px 0 8px;font-size:14px;font-weight:700;color:#b45309;">In progress (not yet closed)</p>
    <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">These visits have a start time but no end time yet — hours are not included in the totals above.</p>
    <ul style="margin:0 0 20px;padding-left:20px;color:#374151;">
      @foreach ($openEntries as $entry)
        <li style="margin-bottom:6px;">
          @if ($entry->occurred_on)
            {{ $entry->occurred_on->copy()->timezone(config('app.timezone'))->format('D') }},
          @endif
          {{ ClientProject::formatDate($entry->occurred_on) }} —
          start {{ ClientProjectTimeEntry::formatTime($entry->start_time) }}
          @if (filled($entry->note))
            — <em>{{ \Illuminate\Support\Str::limit($entry->note, 100) }}</em>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  <p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.5;">
    This email is for your records. Official invoices (with payment instructions) are sent separately when billing is due.
    If anything looks incorrect or you have questions, please reply to this message or call us at (647) 834-7570.
  </p>

  <p style="margin:24px 0 0;">Best regards,</p>
  <p style="margin:4px 0 0;">
    <strong>Jonas Zanini</strong><br>
    Zanini Renovations<br>
    <a href="mailto:zaninirenovations@gmail.com" style="color:#024768;">zaninirenovations@gmail.com</a><br>
    <a href="https://www.zaninirenovations.ca" style="color:#024768;">www.zaninirenovations.ca</a>
  </p>
@endcomponent
