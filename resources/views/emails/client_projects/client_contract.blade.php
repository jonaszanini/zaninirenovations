@php
  // Build the acceptance-phrase placeholders from the project's quotation
  // if one exists. If the contract is being sent before a quotation was
  // created (uncommon but possible), fall back to a generic reference so
  // the client still has a well-formed sentence to copy/paste.
  $clientName = $project->client_name;

  if (isset($quotation) && $quotation) {
      $acceptanceRef = strtolower($quotation->typeLabel()).' '.$quotation->quotation_number;
      $acceptanceDateRaw = $quotation->sent_at ?? $quotation->updated_at ?? now();
  } else {
      $acceptanceRef = 'contract for project "'.$project->name.'"';
      $acceptanceDateRaw = now();
  }

  $acceptanceDate = \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($acceptanceDateRaw);
@endphp

@component('emails.client_projects.layout', ['title' => 'Your contract'])
  <p>Hi {{ $clientName }},</p>

  <p>
    Please find the contract for your project, <strong>{{ $project->name }}</strong>,
    attached to this email.
  </p>

  <p>
    Take a moment to review the document carefully. Once you are satisfied, please
    complete the signing process using one of the following methods:
  </p>

  <p style="margin:12px 0;">
    <strong>Option 1:</strong> Print, sign, and return a scanned copy to us.
  </p>

  <p style="margin:12px 0;">
    <strong>Option 2:</strong> Open the PDF, use the Add signature tool to sign the end,
    save, and reply with the signed file attached.
  </p>

  <p style="margin:12px 0;">
    <strong>Option 3:</strong> Simply reply to this email to confirm your acceptance by
    copying and pasting the following phrase exactly:
  </p>

  <p style="margin:12px 0 12px 16px;border-left:3px solid #e5e7eb;padding:8px 12px;background:#f9fafb;font-style:italic;">
    I {{ $clientName }} accept the terms and conditions outlined in the attached
    {{ $acceptanceRef }} dated {{ $acceptanceDate }}.
  </p>

  @if (!empty($calendarLink))
    @php
      $startFormatted = \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($project->start_date);
      $endFormatted = \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($project->end_date);
      $sameDay = $project->start_date && $project->end_date
          && \Illuminate\Support\Carbon::parse($project->start_date)->isSameDay(\Illuminate\Support\Carbon::parse($project->end_date));
    @endphp

    <p style="margin-top:24px;">
      <strong>Save the date:</strong>
      @if ($sameDay)
        Work is scheduled for <strong>{{ $startFormatted }}</strong>.
      @else
        Work is scheduled from <strong>{{ $startFormatted }}</strong> to
        <strong>{{ $endFormatted }}</strong>.
      @endif
    </p>

    <p style="margin:12px 0;">
      <a href="{{ $calendarLink }}"
         target="_blank"
         rel="noopener noreferrer"
         style="display:inline-block;padding:10px 18px;background:#1f4e5f;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
        Add to Google Calendar
      </a>
    </p>
  @endif

  <p>
    If you have any questions or would like to discuss any details of the contract,
    please reply to this email.
  </p>

  <p style="margin-top:24px;">
    Best regards,<br><br>
    Jonas Zanini<br>
    {{ $siteName }}
  </p>
@endcomponent
