@component('emails.client_projects.layout', ['title' => 'Thank you for choosing Zanini Renovations'])
  <p>Hi {{ $firstName }},</p>
  <p>Thank you for accepting quote {{ $quoteNumber }} for {{ $projectName }}. We truly appreciate the trust you have placed in Zanini Renovations.</p>
  <p>We will be in touch shortly to take the next step together, including scheduling and the project details.</p>
  <p>{{ $questionLine }}</p>
  <p style="margin-top:24px;">Best regards,<br>
    Jonas Zanini<br>
    Zanini Renovations<br>
    {{ $businessEmail }} | zaninirenovations.ca
  </p>
@endcomponent
