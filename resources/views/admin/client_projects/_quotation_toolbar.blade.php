{{--
  Quotation action buttons.
  Desktop: View, Edit, PDF, Send as visible buttons; SMS, Link, Duplicate, Delete in ⋯ dropdown.
  Mobile: All actions in the ⋯ dropdown.
  Expects: $project, $quotation, $legacy, $hasContent, $canSend, $canSms,
  $smsTitle, $shareLinkUrl, $sendConfirmMessage, $smsConfirmMessage, $deleteConfirm,
  $missingDelivery (optional), $canDeliverByEmail, $canDeliverBySms
--}}
@php
  // Mobile dropdown: all actions
  $quotDropdownItems = [];
  if ($hasContent) {
      $quotDropdownItems[] = [
          'type' => 'button',
          'onclick' => "cpOpenQuotationDrawer('qd-{$quotation->id}')",
          'icon' => 'fa-eye',
          'label' => 'View',
      ];
  }
  if (! $legacy) {
      $quotDropdownItems[] = [
          'type' => 'link',
          'href' => route('admin.client-projects.quotation.edit', [$project, $quotation]),
          'icon' => 'fa-pencil',
          'label' => 'Edit',
      ];
  }
  if ($hasContent) {
      $quotDropdownItems[] = [
          'type' => 'link',
          'href' => route('admin.client-projects.quotation.pdf', [$project, $quotation]),
          'icon' => 'fa-download',
          'label' => 'PDF',
          'target' => '_blank',
      ];
  }
  if ($canSend && ! $legacy) {
      $quotDropdownItems[] = [
          'type' => 'form',
          'action' => route('admin.client-projects.quotation.send', [$project, $quotation]),
          'icon' => 'fa-paper-plane',
          'label' => $quotation->isSent() ? 'Resend' : 'Send',
          'confirm' => $sendConfirmMessage,
      ];
  }
  if (! $legacy && $canSms) {
      $quotDropdownItems[] = [
          'type' => 'form',
          'action' => route('admin.client-projects.quotation.sms', [$project, $quotation]),
          'icon' => 'fa-comment',
          'label' => 'SMS',
          'confirm' => $smsConfirmMessage,
      ];
  }
  if (! $legacy && $shareLinkUrl) {
      $quotDropdownItems[] = [
          'type' => 'copylink',
          'url' => $shareLinkUrl,
          'icon' => 'fa-link',
          'label' => 'Link',
      ];
  }
  if (! $legacy) {
      $quotDropdownItems[] = [
          'type' => 'button',
          'onclick' => "cpOpenDuplicateModal({$project->id}, {$quotation->id}, " . json_encode($quotation->quotation_number) . ")",
          'icon' => 'fa-copy',
          'label' => 'Duplicate',
      ];
      $quotDropdownItems[] = [
          'type' => 'form',
          'action' => route('admin.client-projects.quotation.destroy', [$project, $quotation]),
          'method' => 'DELETE',
          'icon' => 'fa-trash',
          'label' => 'Delete',
          'confirm' => $deleteConfirm,
          'class' => 'admin-actions-dropdown__item--danger',
      ];
  }

  // Desktop overflow dropdown: SMS, Link, Duplicate, Delete only
  $desktopOverflowItems = [];
  if (! $legacy && $canSms) {
      $desktopOverflowItems[] = [
          'type' => 'form',
          'action' => route('admin.client-projects.quotation.sms', [$project, $quotation]),
          'icon' => 'fa-comment',
          'label' => 'SMS',
          'confirm' => $smsConfirmMessage,
      ];
  }
  if (! $legacy && $shareLinkUrl) {
      $desktopOverflowItems[] = [
          'type' => 'copylink',
          'url' => $shareLinkUrl,
          'icon' => 'fa-link',
          'label' => 'Link',
      ];
  }
  if (! $legacy) {
      $desktopOverflowItems[] = [
          'type' => 'button',
          'onclick' => "cpOpenDuplicateModal({$project->id}, {$quotation->id}, " . json_encode($quotation->quotation_number) . ")",
          'icon' => 'fa-copy',
          'label' => 'Duplicate',
      ];
      $desktopOverflowItems[] = [
          'type' => 'form',
          'action' => route('admin.client-projects.quotation.destroy', [$project, $quotation]),
          'method' => 'DELETE',
          'icon' => 'fa-trash',
          'label' => 'Delete',
          'confirm' => $deleteConfirm,
          'class' => 'admin-actions-dropdown__item--danger',
      ];
  }
@endphp
<div class="cp-quotation-toolbar admin-table-actions">
  {{-- Desktop visible buttons: View, Edit, PDF, Send --}}
  <div class="cp-quotation-toolbar__desktop">
    @if ($hasContent)
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm"
              onclick="cpOpenQuotationDrawer('qd-{{ $quotation->id }}')"
              title="View calculator details in a side panel">
        <i class="fa fa-eye" aria-hidden="true"></i> View
      </button>
    @endif

    @unless ($legacy)
      <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
         class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit
      </a>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Read-only legacy document.">
        <i class="fa fa-ban"></i> Read-only
      </button>
    @endunless

    @if ($hasContent)
      <a href="{{ route('admin.client-projects.quotation.pdf', [$project, $quotation]) }}"
         target="_blank" rel="noopener"
         class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-download"></i> PDF
      </a>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before PDF is available.">
        <i class="fa fa-download"></i> PDF
      </button>
    @endif

    @if ($canSend && ! $legacy)
      <form action="{{ route('admin.client-projects.quotation.send', [$project, $quotation]) }}"
            method="post" class="cp-inline-form cp-quotation-toolbar__send-form"
            onsubmit='return confirm(@json($sendConfirmMessage));'>
        @csrf
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" title="Generate a fresh PDF and email it to the client.">
          <i class="fa fa-paper-plane"></i> {{ $quotation->isSent() ? 'Resend' : 'Send' }}
        </button>
      </form>
    @else
      @php
        $sendDisabledTitle = $legacy
            ? 'Legacy document — not sendable from this flow.'
            : (! $canDeliverByEmail
                ? "Add the client's email address to email this quotation. Use SMS or Copy link if you only have a phone."
                : 'Add at least one line item first.');
      @endphp
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
              title="{{ $sendDisabledTitle }}">
        <i class="fa fa-paper-plane"></i> Send
      </button>
    @endif

    {{-- Desktop overflow dropdown: SMS, Link, Duplicate, Delete --}}
    @if (count($desktopOverflowItems) > 0)
      <div class="admin-actions-dropdown cp-quotation-toolbar__overflow">
        <button type="button" class="admin-actions-dropdown__toggle" aria-label="More actions">
          <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
        </button>
        <div class="admin-actions-dropdown__menu">
          @foreach ($desktopOverflowItems as $item)
            @if ($item['type'] === 'form')
              <form action="{{ $item['action'] }}"
                    method="post"
                    class="admin-actions-dropdown__form"
                    @if (! empty($item['confirm'])) onsubmit="return confirm({{ json_encode($item['confirm']) }});" @endif>
                @csrf
                @if (! empty($item['method']) && $item['method'] === 'DELETE')
                  @method('DELETE')
                @endif
                <button type="submit" class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}">
                  <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                  {{ $item['label'] }}
                </button>
              </form>
            @elseif ($item['type'] === 'button')
              <button type="button"
                      class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
                      onclick="{{ $item['onclick'] }}">
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </button>
            @elseif ($item['type'] === 'copylink')
              <button type="button"
                      class="admin-actions-dropdown__item cp-quotation-copy-link"
                      data-cp-copy-quotation-link
                      data-copy-url="{{ $item['url'] }}">
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </button>
            @endif
          @endforeach
        </div>
      </div>
    @endif
  </div>

  {{-- Mobile: single ⋯ dropdown with all actions --}}
  @if (count($quotDropdownItems) > 0)
    <div class="admin-actions-dropdown cp-quotation-toolbar__mobile">
      <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions menu">
        <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
      </button>
      <div class="admin-actions-dropdown__menu">
        @foreach ($quotDropdownItems as $item)
          @if ($item['type'] === 'link')
            <a href="{{ $item['href'] }}"
               class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
               @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif>
              <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
              {{ $item['label'] }}
            </a>
          @elseif ($item['type'] === 'form')
            <form action="{{ $item['action'] }}"
                  method="post"
                  class="admin-actions-dropdown__form"
                  @if (! empty($item['confirm'])) onsubmit="return confirm({{ json_encode($item['confirm']) }});" @endif>
              @csrf
              @if (! empty($item['method']) && $item['method'] === 'DELETE')
                @method('DELETE')
              @endif
              <button type="submit" class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}">
                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
              </button>
            </form>
          @elseif ($item['type'] === 'button')
            <button type="button"
                    class="admin-actions-dropdown__item {{ $item['class'] ?? '' }}"
                    onclick="{{ $item['onclick'] }}">
              <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
              {{ $item['label'] }}
            </button>
          @elseif ($item['type'] === 'copylink')
            <button type="button"
                    class="admin-actions-dropdown__item cp-quotation-copy-link"
                    data-cp-copy-quotation-link
                    data-copy-url="{{ $item['url'] }}">
              <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
              {{ $item['label'] }}
            </button>
          @endif
        @endforeach
      </div>
    </div>
  @endif
</div>
