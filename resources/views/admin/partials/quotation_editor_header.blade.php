{{--
  Uniform quotation editor header (breadcrumb + actions + optional wizard nav).

  Required: $typeLabel, $listUrl
  Common:   $project, $client, $clientId, $clientName, $hubUrl, $cancelUrl,
            $priceConfigUrl, $formId, $saveId, $draftId, $saveLabel,
            $saveHotkey, $draftHotkey, $wizard
--}}
@php
  $qhTypeLabel = $typeLabel ?? 'Quotation';
  $qhProject = $project ?? null;
  $qhClient = $client ?? null;
  $qhClientId = $clientId ?? request()->input('client_id') ?? request()->input('client');
  if (! $qhClient && filled($qhClientId) && is_numeric($qhClientId)) {
      $qhClient = \App\Models\Client::query()->find((int) $qhClientId);
  }
  if (! $qhClient && $qhProject && ! empty($qhProject->client_id)) {
      $qhClient = $qhProject->client;
  }
  $qhClientName = trim((string) ($clientName ?? ''));
  if ($qhClientName === '' && $qhProject) {
      $qhClientName = trim((string) ($qhProject->client_name ?? ''));
  }
  if ($qhClientName === '' && $qhClient) {
      $qhClientName = trim((string) ($qhClient->name ?? ''));
  }

  $qhHubUrl = $hubUrl ?? route('admin.quotations.index');
  $qhListUrl = $listUrl ?? $qhHubUrl;
  $qhCancelUrl = $cancelUrl ?? $qhListUrl;
  $qhPriceConfigUrl = $priceConfigUrl ?? null;
  $qhFormId = $formId ?? null;
  $qhSaveId = $saveId ?? null;
  $qhDraftId = $draftId ?? null;
  $qhShowDraft = isset($showDraft) ? (bool) $showDraft : filled($qhDraftId);
  // Default stays "Save quotation" (Painting). Non-painting editors pass
  // saveLabel "Publish" so header/footer/review share one accessible name.
  $qhSaveLabel = $saveLabel ?? 'Save quotation';
  $qhQuote = $quotation ?? null;
  $qhIsEdit = isset($isEdit) ? (bool) $isEdit : filled($qhQuote);
  $qhIsPublished = $qhIsEdit && $qhQuote && empty($qhQuote->is_draft);
  // Ctrl+S keeps the quote's current state on every type: new/draft →
  // Save as draft (never publish); published → Save quotation (never re-draft).
  if ($qhIsPublished) {
      $qhSaveHotkey = true;
      $qhDraftHotkey = false;
  } elseif ($qhShowDraft) {
      $qhSaveHotkey = false;
      $qhDraftHotkey = true;
  } else {
      $qhSaveHotkey = true;
      $qhDraftHotkey = false;
  }
  $qhWizard = $wizard ?? null;
  $qhFormAttr = filled($qhFormId) ? $qhFormId : null;

  $qhQuotationsUrl = $qhHubUrl;

  $qhProjectUrl = $qhProject
      ? route('admin.client-projects.show', $qhProject).'#quotation'
      : null;
  $qhClientUrl = $qhClient ? route('admin.clients.edit', $qhClient) : null;
  $qhMiddleLabel = $qhProject
      ? (string) $qhProject->name
      : ($qhClientName !== '' ? $qhClientName : null);
  $qhMiddleUrl = $qhProject ? $qhProjectUrl : $qhClientUrl;
  $qhMiddleKind = $qhProject ? 'project' : ($qhMiddleLabel ? 'client' : null);
  $qhFlash = \App\Support\QuotationEditorFlash::current(isset($errors) ? $errors : null);
@endphp

<header class="qh" data-qh-header>
  <div class="qh-crumb-row">
  <nav class="qh-crumb" aria-label="Breadcrumb">
    <ol class="qh-crumb__list">
      <li class="qh-crumb__item">
        <a class="qh-crumb__link qh-crumb__quotations" href="{{ $qhQuotationsUrl }}">Quotations</a>
      </li>
      @if ($qhMiddleLabel)
        <li class="qh-crumb__item" aria-hidden="true"><span class="qh-crumb__sep">›</span></li>
        <li class="qh-crumb__item">
          @if ($qhMiddleUrl)
            <a class="qh-crumb__link qh-crumb__{{ $qhMiddleKind }}" href="{{ $qhMiddleUrl }}">{{ $qhMiddleLabel }}</a>
          @else
            <span class="qh-crumb__{{ $qhMiddleKind }}">{{ $qhMiddleLabel }}</span>
          @endif
        </li>
      @endif
      <li class="qh-crumb__item" aria-hidden="true"><span class="qh-crumb__sep">›</span></li>
      <li class="qh-crumb__item">
        <span class="qh-crumb__current qh-crumb__type" aria-current="page">{{ $qhTypeLabel }}</span>
      </li>
    </ol>
  </nav>
  @include('admin.partials.quotation_flash_status', ['qhFlash' => $qhFlash])
  </div>

  <div class="qh-actions" role="group" aria-label="Quotation actions">
    <button type="submit"
            class="admin-btn admin-btn--primary admin-btn--sm qh-actions__save"
            @if ($qhSaveId) id="{{ $qhSaveId }}" @endif
            @if ($qhFormAttr) form="{{ $qhFormAttr }}" @endif
            @if ($qhSaveHotkey) data-hotkey-save @endif
            data-qh-save-intent="publish"
            aria-label="{{ $qhSaveLabel }}">
      <i class="fa fa-save" aria-hidden="true"></i> {{ $qhSaveLabel }}
    </button>
    @if ($qhShowDraft)
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm qh-actions__draft"
              @if ($qhDraftId) id="{{ $qhDraftId }}" @endif
              @if ($qhFormAttr) form="{{ $qhFormAttr }}" @endif
              @if ($qhDraftHotkey) data-hotkey-save @endif
              data-qh-save-intent="draft"
              aria-label="Save as draft">
        Save as draft
      </button>
    @endif
    <button type="button"
            class="admin-btn admin-btn--ghost admin-btn--sm qh-actions__cancel"
            data-qh-cancel="{{ $qhCancelUrl }}">Cancel</button>
  </div>

  @if (is_array($qhWizard) && ! empty($qhWizard['navId']))
    <div class="qh-wizard br-step-nav"
         id="{{ $qhWizard['navId'] }}"
         role="group"
         aria-label="Wizard steps"
         @if (! empty($qhWizard['hidden'])) hidden @endif>
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm"
              id="{{ $qhWizard['prevId'] ?? 'qh-prev' }}"
              disabled>← Back</button>
      <span class="br-step-nav__status"
            id="{{ $qhWizard['statusId'] ?? 'qh-step-status' }}">1 of {{ (int) ($qhWizard['count'] ?? 1) }}</span>
      <button type="button"
              class="admin-btn admin-btn--primary admin-btn--sm"
              id="{{ $qhWizard['nextId'] ?? 'qh-next' }}">→ Next</button>
    </div>
  @endif
</header>
@include('admin.partials.quotation_flash_toast', ['qhFlash' => $qhFlash])
