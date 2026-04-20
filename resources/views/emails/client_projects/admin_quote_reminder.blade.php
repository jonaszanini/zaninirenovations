@component('emails.client_projects.layout', ['title' => 'Quote still pending'])
  <p><strong>Reminder:</strong> a quote still needs to be prepared and sent for this project.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Project</td>
      <td style="padding:6px 0;"><strong>{{ $project->name }}</strong></td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;">{{ $project->client_name }}</td>
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
    @if ($project->client_address)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Address</td>
        <td style="padding:6px 0;">{{ $project->client_address }}</td>
      </tr>
    @endif
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Created</td>
      <td style="padding:6px 0;">{{ $project->created_at->format('d/m/Y H:i') }}</td>
    </tr>
  </table>

  @if ($project->notes)
    <p style="margin-top:16px;"><strong>Notes:</strong></p>
    <p style="white-space:pre-wrap;background:#f9fafb;padding:12px;border-radius:6px;border:1px solid #e5e7eb;">{{ $project->notes }}</p>
  @endif

  <p style="margin-top:24px;">
    <a href="{{ url('/admin/client-projects/'.$project->id) }}" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
      Open project in admin
    </a>
  </p>
@endcomponent
