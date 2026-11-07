@php
  $title = trim((string) ($title ?? ''));
  $body = trim((string) ($body ?? ''));
  $lines = $body === '' ? [] : (preg_split('/\r\n|\r|\n/', $body) ?: []);
@endphp
@if ($title !== '' && $lines !== [])
  <div class="quote-client-scope-block">
    <p class="title">{{ $title }}</p>
    <ul>
      @foreach ($lines as $line)
        @if (trim($line) !== '')
          <li>{{ trim($line) }}</li>
        @endif
      @endforeach
    </ul>
  </div>
@endif
