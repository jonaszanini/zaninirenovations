{{--
  Document and secondary actions that used to live in the old quote toolbar.
  Stays out of the Save / Draft / Cancel row.

  Optional: $purchaseListUrl, $purchaseListTitle, $isEdit, $quotation, $linkedCpq,
            $showHubLink, $hubUrl, $showCreateProject, $createProjectFormId,
            $layoutDiagramsUrl, $cutListUrl, $email, $sms,
            $grandTotalId, $grandTotalValue
--}}
@php
  $isEdit = ! empty($isEdit);
  $showHubLink = ! empty($showHubLink);
  $showCreateProject = ! empty($showCreateProject);
  $createProjectFormId = $createProjectFormId ?? null;
  $layoutDiagramsUrl = $layoutDiagramsUrl ?? null;
  $cutListUrl = $cutListUrl ?? null;
  $hubUrl = $hubUrl ?? route('admin.quotations.index');
  $grandTotalId = $grandTotalId ?? null;
  $grandTotalValue = $grandTotalValue ?? null;
  $email = is_array($email ?? null) ? $email : null;
  $sms = is_array($sms ?? null) ? $sms : null;
  $hasDocs = $isEdit;
  $hasExtras = $showHubLink
      || $showCreateProject
      || filled($layoutDiagramsUrl)
      || filled($cutListUrl)
      || $email
      || $sms
      || filled($grandTotalId);
@endphp
@if ($hasDocs || $hasExtras)
  <nav class="qh-more" data-qh-more aria-label="Quotation documents">
    @if ($showHubLink)
      <a href="{{ $hubUrl }}" class="admin-btn admin-btn--ghost admin-btn--sm" data-qh-quotation-types>
        <i class="fa fa-th" aria-hidden="true"></i> Quotation types
      </a>
    @endif
    @if ($showCreateProject && filled($createProjectFormId))
      <button type="submit"
              form="{{ $createProjectFormId }}"
              name="after_save"
              value="create_project"
              class="admin-btn admin-btn--ghost admin-btn--sm"
              data-qh-create-project>
        <i class="fa fa-folder-plus" aria-hidden="true"></i> Save &amp; create project
      </button>
    @endif
    @if (! empty($purchaseListUrl))
      <a href="{{ $purchaseListUrl }}"
         target="_blank"
         rel="noopener noreferrer"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         data-purchase-list-choice
         data-purchase-list-title="{{ $purchaseListTitle ?? 'Print material list' }}">
        <i class="fa fa-print" aria-hidden="true"></i> {{ $purchaseListTitle ?? 'Print material list' }}
      </a>
    @endif
    @if (filled($cutListUrl))
      <a href="{{ $cutListUrl }}"
         target="_blank"
         rel="noopener noreferrer"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         data-qh-cut-list>
        <i class="fa fa-print" aria-hidden="true"></i> Print cut list
      </a>
    @endif
    @if (filled($layoutDiagramsUrl))
      <a href="{{ $layoutDiagramsUrl }}"
         target="_blank"
         rel="noopener noreferrer"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         data-qh-layout-diagrams>
        <i class="fa fa-print" aria-hidden="true"></i> Print Layout Diagrams
      </a>
    @endif
    @include('admin.partials.quotation_view_pdf')
    @if ($email)
      @if (! empty($email['can']) && ! empty($email['formId']))
        <button type="submit"
                form="{{ $email['formId'] }}"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                title="Email quotation to client"
                aria-label="Email quotation to client"
                data-qh-email
                onclick='return confirm(@json($email['confirm'] ?? ''));'>
          <i class="fa fa-paper-plane" aria-hidden="true"></i> Email quotation to client
        </button>
      @elseif (! empty($email['can']) && ! empty($email['action']))
        <form action="{{ $email['action'] }}" method="post" class="cp-inline-form" data-qh-email-form
              onsubmit='return confirm(@json($email['confirm'] ?? ''));'>
          @csrf
          <button type="submit"
                  class="admin-btn admin-btn--ghost admin-btn--sm"
                  title="Email quotation to client"
                  aria-label="Email quotation to client"
                  data-qh-email>
            <i class="fa fa-paper-plane" aria-hidden="true"></i> Email quotation to client
          </button>
        </form>
      @else
        <span class="br-toolbar-disabled-tip" title="{{ $email['disabledTitle'] ?? '' }}">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm"
                  disabled
                  title="{{ $email['disabledTitle'] ?? '' }}"
                  aria-label="Email quotation to client. {{ $email['disabledTitle'] ?? '' }}"
                  data-qh-email>
            <i class="fa fa-paper-plane" aria-hidden="true"></i> Email quotation to client
          </button>
        </span>
      @endif
    @endif
    @if ($sms)
      @if (! empty($sms['can']) && ! empty($sms['action']))
        <form action="{{ $sms['action'] }}" method="post" class="cp-inline-form" data-qh-sms-form
              onsubmit='return confirm(@json($sms['confirm'] ?? ''));'>
          @csrf
          <button type="submit"
                  class="admin-btn admin-btn--ghost admin-btn--sm"
                  title="SMS quotation link to client"
                  aria-label="SMS quotation link to client"
                  data-qh-sms>
            <i class="fa fa-comment" aria-hidden="true"></i> SMS quotation link to client
          </button>
        </form>
      @else
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                disabled
                title="{{ $sms['disabledTitle'] ?? '' }}"
                aria-label="SMS quotation link to client"
                data-qh-sms>
          <i class="fa fa-comment" aria-hidden="true"></i> SMS quotation link to client
        </button>
      @endif
    @endif
    @if (filled($grandTotalId))
      <div class="br-toolbar-grand" id="{{ $grandTotalId }}" title="Live grand total (incl. HST)">
        <span class="br-toolbar-grand__label">Grand total</span>
        <strong class="br-toolbar-grand__amount" id="{{ $grandTotalId }}-amount">{{ \App\Models\ClientProject::formatMoney((float) $grandTotalValue) }}</strong>
      </div>
    @endif
  </nav>
@endif
