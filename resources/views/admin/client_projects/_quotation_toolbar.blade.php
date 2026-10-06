{{--
  Quotation action buttons (Edit, PDF, Send, SMS, Copy link, Delete).
  Expects: $project, $quotation, $legacy, $hasContent, $canSend, $canSms,
  $smsTitle, $shareLinkUrl, $sendConfirmMessage, $smsConfirmMessage, $deleteConfirm,
  $missingDelivery (optional), $canDeliverByEmail, $canDeliverBySms
--}}
@php
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
          'label' => 'Copy link',
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
@endphp
<div class="cp-quotation-toolbar admin-table-actions">
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
  @endunless

  @if ($legacy)
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Read-only legacy document.">
      <i class="fa fa-ban"></i> Read-only
    </button>
  @endif

  @if ($hasContent)
    <a href="{{ route('admin.client-projects.quotation.pdf', [$project, $quotation]) }}"
       target="_blank" rel="noopener"
       class="admin-btn admin-btn--ghost admin-btn--sm">
      <i class="fa fa-download"></i> PDF
    </a>
  @else
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before PDF is available.">
      <i class="fa fa-download"></i> PDF
    </a>
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

  @unless ($legacy)
    @if ($canSms)
      <form action="{{ route('admin.client-projects.quotation.sms', [$project, $quotation]) }}"
            method="post" class="cp-inline-form"
            onsubmit='return confirm(@json($smsConfirmMessage));'>
        @csrf
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="{{ $smsTitle }}">
          <i class="fa fa-comment"></i> SMS
        </button>
      </form>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="{{ $smsTitle }}">
        <i class="fa fa-comment"></i> SMS
      </button>
    @endif

    @if ($shareLinkUrl)
      <button type="button"
              class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-copy-link"
              data-cp-copy-quotation-link
              data-copy-url="{{ $shareLinkUrl }}"
              title="Copy a secure link to share via Messenger, Instagram, etc.">
        <i class="fa fa-link"></i> Link
      </button>
    @else
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" disabled title="Add line items before a share link is available.">
        <i class="fa fa-link"></i> Link
      </button>
    @endif
  @endunless

  @unless ($legacy)
    <button type="button"
            class="admin-btn admin-btn--ghost admin-btn--sm"
            onclick="cpOpenDuplicateModal({{ $project->id }}, {{ $quotation->id }}, @json($quotation->quotation_number))"
            title="Copy this quotation as a new draft (same project or different)">
      <i class="fa fa-copy"></i> Duplicate
    </button>
    <form action="{{ route('admin.client-projects.quotation.destroy', [$project, $quotation]) }}"
          method="post" class="cp-inline-form"
          onsubmit='return confirm(@json($deleteConfirm));'>
      @csrf
      @method('DELETE')
      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" title="Remove this document from the project and delete the linked calculator quote.">
        <i class="fa fa-trash"></i> Delete
      </button>
    </form>
  @endunless

  {{-- Mobile actions dropdown --}}
  @if (count($quotDropdownItems) > 0)
    <div class="admin-actions-dropdown">
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
