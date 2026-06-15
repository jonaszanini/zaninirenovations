@component('emails.client_projects.layout', ['title' => 'Work begins tomorrow'])
  <p>Dear {{ $project->client_name }},</p>

  <p>
    This is a friendly reminder from <strong>{{ $siteName }}</strong> that we are scheduled to
    <strong>begin your project tomorrow</strong>.
  </p>

  <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
    <strong>Project:</strong> {{ $project->name }}<br>
    <strong>Start date:</strong> {{ $startFormatted }}
    @if ($project->end_date)
      <br><strong>Expected end:</strong> {{ \App\Support\EasternStandardTimeDisplay::formatCalendarLongDateWithWeekday($project->end_date) }}
    @endif
    @if (filled($project->client_address))
      <br><strong>Service address:</strong> {{ $project->client_address }}
    @endif
  </p>

  @if (!empty($calendarLink))
    <p style="margin:20px 0 8px 0;"><strong>Add to your calendar:</strong></p>
    <p style="margin:12px 0;">
      <a href="{{ $calendarLink }}"
         target="_blank"
         rel="noopener noreferrer"
         style="display:inline-block;padding:10px 18px;background:#024768;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
        Add to Google Calendar
      </a>
    </p>
  @endif

  <p>
    If you need to reach us before we arrive, reply to this email or call
    <a href="tel:+16478347570">(647) 834-7570</a>.
  </p>

  <p>We look forward to seeing you tomorrow.</p>

  <p style="margin-top:24px;">
    Best regards,<br><br>
    Jonas Zanini<br>
    Owner, {{ $siteName }}<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
