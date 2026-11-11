{{-- Compact save status inside the quotation breadcrumb card. --}}
@php
  $qhFlash = $qhFlash ?? \App\Support\QuotationEditorFlash::current(isset($errors) ? $errors : null);
  $qhFlashType = $qhFlash['type'] ?? 'success';
  $qhFlashCompact = trim((string) ($qhFlash['compact'] ?? ''));
  $qhFlashIcon = \App\Support\QuotationEditorFlash::icon($qhFlashType);
@endphp
<p class="qh-flash-status qh-flash-status--{{ $qhFlashType }}"
   data-qh-flash-status
   data-qh-flash-status-type="{{ $qhFlashType }}"
   @if ($qhFlashCompact === '') hidden @endif>
  <i class="fa {{ $qhFlashIcon }}" data-qh-flash-status-icon aria-hidden="true"></i>
  <span data-qh-flash-status-text>{{ $qhFlashCompact }}</span>
</p>
