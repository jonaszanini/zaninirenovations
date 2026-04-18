@component('emails.client_projects.layout', ['title' => 'Quote request received'])
  <p>Hi {{ $project->client_name }},</p>
  <p>
    Thank you for your quote request. We have started working on your
    <strong>{{ $project->name }}</strong> quote and will be in touch shortly.
  </p>
  <p>If you need to share any additional information in the meantime, just reply to this email.</p>
  <p style="margin-top:24px;">Best regards,<br>
    {{ $siteName }}
  </p>
@endcomponent
