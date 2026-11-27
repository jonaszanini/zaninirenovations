{{-- Client-facing scope on the quotation PDF. One table row per bullet so
     DomPDF can break between lines. The heading, column titles, and first
     bullet stay together; later rows may move to the next page. --}}
@php
  $clientScope = (isset($clientScope) && is_array($clientScope))
      ? $clientScope
      : $quotation->clientScopeForPdf();

  $scopeLines = function (?string $body): array {
      $body = trim((string) $body);
      if ($body === '') {
          return [];
      }
      $lines = [];
      foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
          $line = trim($line);
          if ($line !== '') {
              $lines[] = $line;
          }
      }

      return $lines;
  };

  $sections = [];
  if ($clientScope) {
      $valueLeft = $scopeLines($clientScope['included'] ?? '');
      $valueRight = $scopeLines($clientScope['not_included'] ?? '');
      if ($valueLeft !== [] || $valueRight !== []) {
          $sections[] = [
              'kicker' => 'Value',
              'left_title' => 'Included',
              'right_title' => 'Not included',
              'left' => $valueLeft,
              'right' => $valueRight,
          ];
      }
      $termsLeft = $scopeLines($clientScope['assumptions'] ?? '');
      $termsRight = $scopeLines($clientScope['allowances'] ?? '');
      if ($termsLeft !== [] || $termsRight !== []) {
          $sections[] = [
              'kicker' => 'Special terms',
              'left_title' => 'Assumptions',
              'right_title' => 'Allowances',
              'left' => $termsLeft,
              'right' => $termsRight,
          ];
      }
  }
@endphp
@if ($sections !== [])
  <div class="quote-client-scope">
    @foreach ($sections as $section)
      @php $lineCount = max(count($section['left']), count($section['right'])); @endphp
      <table class="quote-client-scope-table" role="presentation">
        <tr class="quote-client-scope-head">
          <td colspan="2"><p class="quote-client-scope-kicker">{{ $section['kicker'] }}</p></td>
        </tr>
        <tr class="quote-client-scope-titles">
          <td class="quote-client-scope-block">
            @if ($section['left'] !== [])
              <p class="title">{{ $section['left_title'] }}</p>
            @endif
          </td>
          <td class="quote-client-scope-block">
            @if ($section['right'] !== [])
              <p class="title">{{ $section['right_title'] }}</p>
            @endif
          </td>
        </tr>
        @for ($i = 0; $i < $lineCount; $i++)
          <tr class="{{ $i === 0 ? 'quote-client-scope-first' : 'quote-client-scope-line' }}">
            <td class="quote-client-scope-block">
              @if (isset($section['left'][$i]))
                <ul><li>{{ $section['left'][$i] }}</li></ul>
              @endif
            </td>
            <td class="quote-client-scope-block">
              @if (isset($section['right'][$i]))
                <ul><li>{{ $section['right'][$i] }}</li></ul>
              @endif
            </td>
          </tr>
        @endfor
      </table>
    @endforeach
  </div>
@endif
