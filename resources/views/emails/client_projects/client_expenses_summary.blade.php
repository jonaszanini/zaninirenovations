@php
  use App\Models\ClientProject;

  $fmtMoney = fn ($n) => ClientProject::formatMoney((float) $n);
  $expenses = $project->expenses;
  $returns = $project->returns;
  $grossExpenses = $project->totalExpensesAmount();
  $returnsTotal = $project->totalReturnsAmount();
  $netMaterials = $project->totalSpent();
  $withReceipts = $expenses->filter(fn ($e) => filled($e->receipt_path))->count();
@endphp

@component('emails.client_projects.layout', ['title' => 'Project expenses'])
  <p>Dear {{ $project->client_name }},</p>

  <p>
    Please find below a summary of expenses recorded for
    <strong>{{ $project->name }}</strong>, as of {{ \App\Models\ClientProject::formatDateTime(now()) }}.
    @if ($returnsTotal > 0)
      Returns and credits are listed separately; the <strong>net materials total</strong> is expenses minus those returns.
    @endif
  </p>

  @if (filled($project->client_address))
    <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
      <strong>Project address:</strong> {{ $project->client_address }}
    </p>
  @endif

  @if ($withReceipts > 0)
    <p style="margin:16px 0;color:#374151;">
      <strong>{{ $withReceipts }}</strong> receipt photo{{ $withReceipts === 1 ? '' : 's' }} {{ $withReceipts === 1 ? 'is' : 'are' }} attached as WebP images. Each filename is <strong>Receipt-#</strong> where # is the number in the first column of the expenses table.
    </p>
  @endif

  <p style="margin:20px 0 8px 0;font-size:15px;font-weight:700;color:#111827;">Expenses</p>
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:14px;margin:0 0 20px 0;">
    <thead>
      <tr style="background:#024768;color:#ffffff;">
        <th align="center" style="padding:10px 8px;width:44px;">#</th>
        <th align="left" style="padding:10px 8px;">Date</th>
        <th align="left" style="padding:10px 8px;">Description</th>
        <th align="right" style="padding:10px 8px;">Amount</th>
        <th align="center" style="padding:10px 8px;">Receipt</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($expenses as $expense)
        <tr style="background:{{ $loop->iteration % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:600;">{{ $expense->expense_ref }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProject::formatDate($expense->occurred_on) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ $expense->description }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;font-variant-numeric:tabular-nums;">{{ $fmtMoney($expense->amount) }}</td>
          <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:center;">{{ filled($expense->receipt_path) ? 'Yes (Receipt-'.$expense->expense_ref.'.webp)' : '—' }}</td>
        </tr>
      @endforeach
      <tr style="background:#eef6fb;font-weight:600;">
        <td style="padding:12px 8px;border-top:1px solid #cbd5e1;"></td>
        <td colspan="2" style="padding:12px 8px;border-top:1px solid #cbd5e1;">Subtotal — expenses</td>
        <td style="padding:12px 8px;border-top:1px solid #cbd5e1;text-align:right;">{{ $fmtMoney($grossExpenses) }}</td>
        <td style="padding:12px 8px;border-top:1px solid #cbd5e1;"></td>
      </tr>
    </tbody>
  </table>

  @if ($returns->isNotEmpty())
    <p style="margin:20px 0 8px 0;font-size:15px;font-weight:700;color:#111827;">Returns / credits</p>
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:14px;margin:0 0 20px 0;">
      <thead>
        <tr style="background:#374151;color:#ffffff;">
          <th align="left" style="padding:10px 8px;">Date</th>
          <th align="left" style="padding:10px 8px;">Description</th>
          <th align="right" style="padding:10px 8px;">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($returns as $return)
          <tr style="background:{{ $loop->iteration % 2 === 0 ? '#f9fafb' : '#ffffff' }};">
            <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ ClientProject::formatDate($return->occurred_on) }}</td>
            <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;">{{ $return->description }}</td>
            <td style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;font-variant-numeric:tabular-nums;">−{{ $fmtMoney($return->amount) }}</td>
          </tr>
        @endforeach
        <tr style="background:#eef6fb;font-weight:600;">
          <td colspan="2" style="padding:12px 8px;border-top:1px solid #cbd5e1;">Subtotal — returns / credits</td>
          <td style="padding:12px 8px;border-top:1px solid #cbd5e1;text-align:right;">−{{ $fmtMoney($returnsTotal) }}</td>
        </tr>
      </tbody>
    </table>
  @endif

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:15px;margin:8px 0 24px 0;">
    <tr style="background:#f0f9ff;font-weight:700;">
      <td style="padding:14px 10px;border:2px solid #024768;color:#024768;">
        Net materials total (expenses − returns)
      </td>
      <td align="right" style="padding:14px 10px;border:2px solid #024768;color:#024768;white-space:nowrap;font-variant-numeric:tabular-nums;">
        {{ $fmtMoney($netMaterials) }}
      </td>
    </tr>
  </table>

  <p style="margin-top:24px;color:#6b7280;font-size:13px;">
    Questions? Reply to this email or contact us at the number on your contract.
  </p>
@endcomponent
