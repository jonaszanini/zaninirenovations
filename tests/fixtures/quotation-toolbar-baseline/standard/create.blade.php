Pinned from 86e68a3e0185e1af7e4793200d770ffa9b80f923:resources/views/admin/quotations/standard/create.blade.php
        <i class="fa fa-th"></i> Quotation types
      </a>
      @include('admin.partials.quotation_back_to_project', ['project' => $projectForReturn])
    </div>
    <div class="pq-sidebar-actions">
      @if ($isEdit)
        <a href="{{ route('admin.quotations.standard.purchase-list', $quotation) }}"
           target="_blank" rel="noopener noreferrer"
           class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
           data-purchase-list-choice
           data-purchase-list-title="Print material list"
           title="Print material list"
           aria-label="Print material list">
          <i class="fa fa-print" aria-hidden="true"></i>
        </a>
        @include('admin.partials.quotation_view_pdf', ['iconOnly' => true])

        @if ($canSendEmail)
          <form action="{{ route('admin.client-projects.quotation.send', [$sendProject, $linkedCpq]) }}"
                method="post" class="cp-inline-form"
                onsubmit='return confirm(@json($sendEmailConfirm));'>
            @csrf
            <button type="submit"
                    class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                    title="Email quotation to client"
                    aria-label="Email quotation to client">
              <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                  disabled
                  title="{{ $sendEmailDisabledTitle }}"
                  aria-label="Email quotation to client">
            <i class="fa fa-paper-plane" aria-hidden="true"></i>
          </button>
        @endif

        @if ($canSendSms)
          <form action="{{ route('admin.client-projects.quotation.sms', [$sendProject, $linkedCpq]) }}"
                method="post" class="cp-inline-form"
                onsubmit='return confirm(@json($sendSmsConfirm));'>
            @csrf
            <button type="submit"
                    class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                    title="SMS quotation link to client"
                    aria-label="SMS quotation link to client">
              <i class="fa fa-comment" aria-hidden="true"></i>
            </button>
          </form>
        @else
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm admin-btn--icon"
                  disabled
                  title="{{ $sendSmsDisabledTitle }}"
                  aria-label="SMS quotation link to client">
