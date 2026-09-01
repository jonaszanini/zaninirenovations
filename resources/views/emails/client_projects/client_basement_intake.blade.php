@component('emails.client_projects.layout', ['title' => 'Project details – '.$quotation->quote_number])
  <p>Hi {{ $clientName }},</p>

  <p>
    Please answer a short questionnaire so we can finish the quotation for
    <strong>{{ $quotation->displayTitle() }}</strong>.
    The form uses simple yes/no and multiple-choice questions — it only takes a few minutes.
  </p>

  <p style="margin:24px 0;">
    <a href="{{ $formUrl }}"
       style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:4px;font-weight:700;">
      Open the questionnaire
    </a>
  </p>

  <p style="font-size:13px;color:#6b7280;">
    You can also copy this link:<br>
    <a href="{{ $formUrl }}">{{ $formUrl }}</a>
  </p>

  <p>Please submit it only once. If anything is unclear, reply to this email or call (647) 834-7570.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
