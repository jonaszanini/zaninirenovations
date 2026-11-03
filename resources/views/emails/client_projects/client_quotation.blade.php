@php
  $typeLower = strtolower($quotation->typeLabel());
  $clientEmailMessage = $quotation->clientEmailMessageHtml();
  $acceptUrl = $acceptUrl ?? $quotation->signedAcceptUrl();
  $trimmedCustom = trim((string) ($customMessage ?? ''));
  if ($trimmedCustom !== '') {
      // Remove duplicate leading greeting if the custom message starts with "Hi <name>," / "Hello,"
      $trimmedCustom = trim((string) preg_replace('/^(hi|hello|dear)(\s+[^\r\n,]+)?,\s*/i', '', $trimmedCustom));
      // Remove duplicate boilerplate sentences that are always rendered in the closing paragraphs below
      $trimmedCustom = trim((string) preg_replace('/\bWe are fully insured\.?\s*/i', '', $trimmedCustom));
      $trimmedCustom = trim((string) preg_replace('/\bPlease note that building permits,\s*if required,\s*are an optional extra\.?\s*/i', '', $trimmedCustom));
      $trimmedCustom = trim((string) preg_replace('/\bFeel free to reach out if you have any questions or adjustments\.?\s*/i', '', $trimmedCustom));
  }
  $hasCustomMessage = $trimmedCustom !== '';
  $customHasAttachmentLine = $hasCustomMessage && stripos($trimmedCustom, 'attached') !== false;
@endphp

@component('emails.client_projects.layout', ['title' => $quotation->typeLabel().' – '.$project->name])
  <p>Hi {{ $project->client_name }},</p>

  @if ($hasCustomMessage)
    <div style="margin:0 0 16px 0;">{!! nl2br(e($trimmedCustom)) !!}</div>
    @if (! $customHasAttachmentLine)
      <p>Please find the detailed {{ $typeLower }} attached as a PDF for your review.</p>
    @endif
  @elseif ($clientEmailMessage)
    <div style="margin:0 0 16px 0;">{!! $clientEmailMessage !!}</div>
    <p>Please find the detailed {{ $typeLower }} attached as a PDF for your review.</p>
  @else
    <p>
      It was a pleasure discussing your <strong>{{ $project->name }}</strong> with you recently.
      Please find the detailed {{ $typeLower }} attached as a PDF for your review.
    </p>
  @endif

  @if (($photoAttachmentCount ?? 0) > 0)
    <p>The reference photos shown in the PDF are also attached to this email.</p>
  @endif

  <p>
    We are fully insured. This proposal outlines the scope of the work. All planned work is designed to
    meet or exceed the standards set by the Ontario Building Code (O.Reg. 332/12)
    to ensure the safety and longevity of your home.
    Please note that building permits, if required, are an optional extra.
  </p>

  <p>
    If you have any questions regarding it or would like to adjust any of the
    details, please feel free to reach out. I am happy to hop on a quick call or
    respond via email.
  </p>

  @if ($quotation->asksClientToReplyIAccept())
    <p>Please reply to this email with the words “I accept” to confirm this {{ $typeLower }}.</p>
  @endif

  <p style="margin:24px 0;">
    <a href="{{ $acceptUrl }}"
       style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;">
      Accept this quote
    </a>
  </p>
  <p style="margin:0 0 16px 0;font-size:13px;color:#6b7280;">
    Or open this link: <a href="{{ $acceptUrl }}" style="color:#024768;word-break:break-all;">{{ $acceptUrl }}</a>
  </p>

  <p>I look forward to the possibility of working together to bring this project to life.</p>

  @php
    $paymentTerms = \App\Services\QuotationPaymentSchedule::terms($quotation->payment_installments ?? null, $quotation->payment_percentages ?? null);
  @endphp
  <p style="margin:20px 0 8px 0;"><strong>Payment Schedule &amp; Terms</strong></p>
  <ul style="margin:0 0 16px 20px;padding:0;">
    @foreach ($paymentTerms as [$title, $detail])
      <li style="margin:8px 0;">
        <strong>{{ $title }}:</strong> {{ $detail }}
      </li>
    @endforeach
  </ul>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
@endcomponent
