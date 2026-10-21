@component('emails.client_projects.layout', ['title' => 'Quote accepted'])
  <p><strong>{{ $project->client_name ?: 'The client' }}</strong> has accepted quote <strong>{{ $quotation->displayQuoteNumber() }}</strong> for {{ $project->name }} ({{ $project->referenceCode() }}).</p>

  <p>They used the Accept this quote link from the quotation email or PDF. The quote status has been changed to <strong>{{ $quotation->statusLabel() }}</strong>. The project status is now <strong>{{ $project->statusLabel() }}</strong>.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;">{{ $project->client_name ?: '—' }}</td>
    </tr>
    @if ($project->client_email)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Email</td>
        <td style="padding:6px 0;"><a href="mailto:{{ $project->client_email }}" style="color:#024768;">{{ $project->client_email }}</a></td>
      </tr>
    @endif
    @if ($project->client_phone)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Phone</td>
        <td style="padding:6px 0;">{{ \App\Models\ClientProject::formatPhone($project->client_phone) }}</td>
      </tr>
    @endif
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Quote</td>
      <td style="padding:6px 0;">{{ $quotation->displayQuoteNumber() }}</td>
    </tr>
  </table>

  <p style="margin-top:24px;">
    <a href="{{ url('/admin/client-projects/'.$project->id) }}" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
      Open project in admin
    </a>
  </p>
@endcomponent
