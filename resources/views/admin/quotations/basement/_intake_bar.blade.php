@if (! empty($quotation) && $quotation->supportsClientIntake())
  @php
    $intakeUrl = $quotation->clientIntakeUrl();
    $intakeEmail = $quotation->intakeClientEmail();
    $intakeSubmitted = $quotation->isClientIntakeSubmitted();
    $intakeSent = $quotation->client_intake_sent_at;
  @endphp
  <div class="admin-card br-intake-bar">
    <div class="br-intake-bar__head">
      <strong>Client questionnaire</strong>
      @if ($intakeSubmitted)
        <span class="br-intake-bar__chip br-intake-bar__chip--done">Submitted {{ $quotation->client_intake_submitted_at->timezone(config('app.timezone', 'America/Toronto'))->format('M j, Y g:ia') }}</span>
      @elseif ($intakeSent)
        <span class="br-intake-bar__chip">Link sent {{ $intakeSent->timezone(config('app.timezone', 'America/Toronto'))->format('M j, Y') }}</span>
      @else
        <span class="br-intake-bar__chip">Link ready</span>
      @endif
    </div>
    <p class="admin-form-help">Send this link so the client can fill fixtures, sizes, and who supplies materials. You can still edit every field after they submit.</p>
    <div class="br-intake-bar__actions">
      <input type="text" class="admin-input" id="br-intake-url" readonly value="{{ $intakeUrl }}" aria-label="Client form link">
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-intake-copy">
        <i class="fa fa-clipboard" aria-hidden="true"></i> Copy link
      </button>
      @if ($intakeEmail)
        <form method="post" action="{{ route('admin.quotations.basement.intake.email', $quotation) }}">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-envelope" aria-hidden="true"></i> Email link
          </button>
        </form>
      @endif
      @if ($intakeSubmitted)
        <form method="post" action="{{ route('admin.quotations.basement.intake.reopen', $quotation) }}" onsubmit="return confirm('Allow the client to submit this form again?');">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Allow submit again</button>
        </form>
      @endif
    </div>
  </div>
@endif
