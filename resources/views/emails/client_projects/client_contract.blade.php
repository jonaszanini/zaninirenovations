@component('emails.client_projects.layout', ['title' => 'Your contract'])
  <p>Hi {{ $project->client_name }},</p>
  <p>
    Please find attached the contract for your project
    <strong>{{ $project->name }}</strong>.
  </p>
  <p>
    Take a moment to review it carefully. Once you're satisfied, please print, sign,
    and return a scanned copy to us — or simply reply to this email confirming your
    acceptance and we'll arrange signing.
  </p>
  <p>If you have any questions or would like to discuss anything in the contract, just reply to this email.</p>
  <p style="margin-top:24px;">Best regards,<br>
    {{ $siteName }}
  </p>
@endcomponent
