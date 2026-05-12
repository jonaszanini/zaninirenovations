@php
  use App\Models\ClientProject;

  $fmtMoney = fn ($n) => ClientProject::formatMoney((float) $n);
  $expenses = $project->expenses;
  $total = (float) $expenses->sum('amount');
  $withReceipts = $expenses->filter(fn ($e) => filled($e->receipt_path))->count();
@endphp

@component('emails.client_projects.layout', ['title' => 'Project expenses'])
  <p>Dear {{ $project->client_name }},</p>

  <p>
    Please find below a summary of expenses recorded for
    <strong>{{ $project->name }}</strong>, as of {{ \App\Models\ClientProject::formatDateTime(now()) }}.
  </p>

  @if (filled($project->client_address))
    <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
      <strong>Project address:</strong> {{ $project->client_address }}
    </p>
  @endif

  @if ($withReceipts > 0)
    <p style="margin:16px 0;color:#374151;">
      <strong>{{ $withReceipts }}</strong> receipt photo{{ $withReceipts === 1 ? '' : 's' }} {{ $withReceipts === 1 ? 'is' : 'are' }} attached to this email as WebP images.
    </p>
  @endif

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:14px;margin:20px 0;">
    <thead>
      <tr style="background:#024768;color:#ffffff;">
        <th align="left" style="padding:10px 8px;">Date</th>
        <th align="left" style="padding:10px 8px;">Description</th>
        <th align="right" style="padding:10px 8px;">Amount</th>
        <th align="center" style="padding:10px 8px;">Receipt</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($expenses as $expense)
        <tr style="background:{{ $loop->iteration % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProject::formatDate($expense->occurred_on) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ $expense->description }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;font-variant-numeric:tabular-nums;">{{ $fmtMoney($expense->amount) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:center;">{{ filled($expense->receipt_path) ? 'Yes (attached)' : '—' }}</td>
        </tr>
      @endforeach
      <tr style="background:#f0f9ff;font-weight:700;">
        <td colspan="2" style="padding:12px 8px;border-top:2px solid #024768;">Total</td>
        <td style="padding:12px 8px;border-top:2px solid #024768;text-align:right;">{{ $fmtMoney($total) }}</td>
        <td style="padding:12px 8px;border-top:2px solid #024768;"></td>
      </tr>
    </tbody>
  </table>

  <p style="margin-top:24px;color:#6b7280;font-size:13px;">
    Questions? Reply to this email or contact us at the number on your contract.
  </p>
@endcomponent
