@php
  $contactName = \App\Support\BusinessContact::name();
  $contactPhone = \App\Support\BusinessContact::phoneDisplay();
  $contactPhoneHref = \App\Support\BusinessContact::phoneHref();
  $contactEmail = \App\Support\BusinessContact::email();
@endphp
<p class="qa-contact">
  {{ $contactName }}
  @if ($contactPhone !== '')
    ·
    @if ($contactPhoneHref !== '')
      <a href="{{ $contactPhoneHref }}">{{ $contactPhone }}</a>
    @else
      {{ $contactPhone }}
    @endif
  @endif
  @if ($contactEmail !== '')
    · <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
  @endif
</p>
