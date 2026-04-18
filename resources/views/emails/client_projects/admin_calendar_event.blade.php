@component('emails.client_projects.layout', ['title' => 'Project dates saved'])
  <p>Start and end dates were saved for <strong>{{ $project->name }}</strong>.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;">{{ $project->client_name }}</td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Start</td>
      <td style="padding:6px 0;">{{ \App\Models\ClientProject::formatDate($project->start_date) }}</td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">End</td>
      <td style="padding:6px 0;">{{ \App\Models\ClientProject::formatDate($project->end_date) }}</td>
    </tr>
    @if ($project->client_address)
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Location</td>
        <td style="padding:6px 0;">{{ $project->client_address }}</td>
      </tr>
    @endif
  </table>

  @if ($calendarUrl)
    <p style="margin:24px 0;">
      <a href="{{ $calendarUrl }}" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;">
        Add to Google Calendar
      </a>
    </p>
    <p style="font-size:12px;color:#6b7280;">Opens a new Google Calendar event pre-filled with the project details.</p>
  @endif

  <p style="margin-top:24px;">
    <a href="{{ url('/admin/client-projects/'.$project->id) }}" style="color:#024768;">Open project in admin</a>
  </p>
@endcomponent
