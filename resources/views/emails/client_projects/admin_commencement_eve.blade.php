@component('emails.client_projects.layout', ['title' => 'Work begins tomorrow'])
  <p><strong>Reminder:</strong> this project is scheduled to <strong>begin tomorrow</strong>.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Project</td>
      <td style="padding:6px 0;"><strong>{{ $project->name }}</strong></td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;">{{ $project->client_name }}</td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Start date</td>
      <td style="padding:6px 0;"><strong>{{ $startFormatted }}</strong></td>
    </tr>
    @if ($project->end_date)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">End date</td>
        <td style="padding:6px 0;">{{ $project->end_date->format('l, F j, Y') }}</td>
      </tr>
    @endif
    @if ($project->client_phone)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Phone</td>
        <td style="padding:6px 0;">{{ \App\Models\ClientProject::formatPhone($project->client_phone) }}</td>
      </tr>
    @endif
  </table>

  <p style="margin-top:24px;">
    <a href="{{ url('/admin/client-projects/'.$project->id) }}" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
      Open project in admin
    </a>
  </p>
@endcomponent
