{{-- Viewport toast for quotation save/publish. Auto-hides via admin-quotation-flash.js. --}}
@php
  $qhFlash = $qhFlash ?? \App\Support\QuotationEditorFlash::current(isset($errors) ? $errors : null);
  $qhFlashType = $qhFlash['type'] ?? 'success';
  $qhFlashMessage = trim((string) ($qhFlash['message'] ?? ''));
  $qhFlashIcon = \App\Support\QuotationEditorFlash::icon($qhFlashType);
@endphp
<div class="admin-toast admin-toast--top-right qh-flash-toast"
     data-admin-toast-host
     data-qh-flash-toast
     data-qh-flash-duration="6000"
     data-placement="top-right"
     role="status"
     aria-live="polite"
     @if ($qhFlashMessage === '') hidden @endif>
  @if ($qhFlashMessage !== '')
    <div class="admin-toast__card admin-toast--{{ $qhFlashType }}">
      <i class="fa {{ $qhFlashIcon }} admin-toast__icon" aria-hidden="true"></i>
      <div class="admin-toast__body">
        <div class="admin-toast__title">{{ $qhFlashMessage }}</div>
      </div>
      <button type="button" class="admin-toast__close" data-admin-toast-close aria-label="Dismiss">
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
    </div>
  @endif
</div>
