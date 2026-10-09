{{--
  Uniform quotation editor header (breadcrumb + actions + optional wizard nav).

  Required: $typeLabel, $listUrl
  Common:   $title, $project, $client, $clientName, $hubUrl, $cancelUrl,
            $priceConfigUrl, $formId, $saveId, $draftId, $saveLabel,
            $saveHotkey, $draftHotkey, $wizard
--}}
@php
  $qhTitle = $title ?? '';
  $qhTypeLabel = $typeLabel ?? 'Quotation';
  $qhProject = $project ?? null;
  $qhClient = $client ?? null;
  $qhClientName = trim((string) ($clientName ?? ''));
  if ($qhClientName === '' && $qhProject) {
      $qhClientName = trim((string) ($qhProject->client_name ?? ''));
  }
  if ($qhClientName === '' && $qhClient) {
      $qhClientName = trim((string) ($qhClient->name ?? ''));
  }
  if (! $qhClient && $qhProject && ! empty($qhProject->client_id)) {
      $qhClient = $qhProject->client;
  }
  if (! $qhProject && ! $qhClient && $qhClientName !== '') {
      $qhClient = \App\Models\Client::query()->where('name', $qhClientName)->first();
  }

  $qhHubUrl = $hubUrl ?? route('admin.quotations.index');
  $qhListUrl = $listUrl ?? $qhHubUrl;
  $qhCancelUrl = $cancelUrl ?? $qhListUrl;
  $qhPriceConfigUrl = $priceConfigUrl ?? null;
  $qhFormId = $formId ?? null;
  $qhSaveId = $saveId ?? null;
  $qhDraftId = $draftId ?? null;
  $qhShowDraft = isset($showDraft) ? (bool) $showDraft : filled($qhDraftId);
  $qhSaveLabel = $saveLabel ?? 'Save quotation';
  $qhSaveHotkey = (bool) ($saveHotkey ?? false);
  $qhDraftHotkey = (bool) ($draftHotkey ?? false);
  $qhWizard = $wizard ?? null;
  $qhFormAttr = filled($qhFormId) ? $qhFormId : null;

  $qhQuotationsUrl = $qhHubUrl;
  if (! $qhProject) {
      if ($qhClient) {
          $qhQuotationsUrl = route('admin.clients.edit', $qhClient);
      } else {
          $qhQuotationsUrl = $qhListUrl;
      }
  }

  $qhProjectUrl = $qhProject
      ? route('admin.client-projects.show', $qhProject).'#quotation'
      : null;
  $qhClientUrl = $qhClient ? route('admin.clients.edit', $qhClient) : null;
  $qhMiddleLabel = $qhProject
      ? (string) $qhProject->name
      : ($qhClientName !== '' ? $qhClientName : null);
  $qhMiddleUrl = $qhProject ? $qhProjectUrl : $qhClientUrl;
  $qhMiddleKind = $qhProject ? 'project' : ($qhMiddleLabel ? 'client' : null);
@endphp

<header class="qh" data-qh-header>
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

  @if (filled($qhTitle))
    <p class="qh-title">{{ $qhTitle }}</p>
  @endif

  <div class="qh-actions" role="group" aria-label="Quotation actions">
    <button type="submit"
            class="admin-btn admin-btn--primary admin-btn--sm qh-actions__save"
            @if ($qhSaveId) id="{{ $qhSaveId }}" @endif
            @if ($qhFormAttr) form="{{ $qhFormAttr }}" @endif
            @if ($qhSaveHotkey) data-hotkey-save @endif>
      <i class="fa fa-save" aria-hidden="true"></i> {{ $qhSaveLabel }}
    </button>
    @if ($qhShowDraft)
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm qh-actions__draft"
              @if ($qhDraftId) id="{{ $qhDraftId }}" @endif
              @if ($qhDraftHotkey) data-hotkey-save @endif>
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
         aria-label="Wizard steps">
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm"
              id="{{ $qhWizard['prevId'] ?? 'qh-prev' }}"
              disabled>← Back</button>
      <span class="br-step-nav__status qh-wizard__status"
            id="{{ $qhWizard['statusId'] ?? 'qh-step-status' }}">1 of {{ (int) ($qhWizard['count'] ?? 1) }}</span>
      <button type="button"
              class="admin-btn admin-btn--primary admin-btn--sm"
              id="{{ $qhWizard['nextId'] ?? 'qh-next' }}">→ Next</button>
    </div>
  @endif
</header>
