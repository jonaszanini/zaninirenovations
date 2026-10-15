{{--
  Standard admin checkbox — checkbox on the left, label on the right, brand blue when checked.
  Use this partial (or copy the markup) for every new checkbox in the admin.

  @param string       $name            Input name (required unless this is a JS-only toggle)
  @param string|null  $id              Input id (derived from $name when omitted + $info is set)
  @param string       $checkboxValue   Input value (default: 1). Named this way so a
                                      parent @foreach ($rows as $value => $label) cannot leak
                                      its loop key into the posted checkbox value.
  @param bool         $checked
  @param string|null  $label           Label HTML (right of checkbox)
  @param string|null  $info            Optional explanation shown on the info icon
  @param string       $class           Extra classes on the common row wrapper (and on <label> when there is no tip)
  @param string       $inputClass      Extra classes on <input> (default: admin-checkbox__input)
  @param bool         $hiddenFallback  Emit hidden 0 before checkbox (for Laravel boolean fields)
  @param bool         $clientFilled    Show “filled from client form” icon
  @param array        $attrs           Extra HTML attributes on input
--}}
@php
  $name = $name ?? '';
  // Never read $value here. Parent views often do @foreach ($options as $value => $label),
  // and that key stays in scope for later @includes. Using it made this checkbox post
  // "whatsapp" (the last Contact via option) instead of "1", which fails boolean validation
  // even when the box is checked.
  $checkboxValue = (isset($checkboxValue) && $checkboxValue !== '') ? $checkboxValue : '1';
  $checked = $checked ?? false;
  $extraClass = trim((string) ($class ?? ''));
  $class = trim('admin-checkbox '.$extraClass);
  $inputClass = trim('admin-checkbox__input '.($inputClass ?? ''));
  $hiddenFallback = $hiddenFallback ?? false;
  $attrs = $attrs ?? [];
  $clientFilled = $clientFilled ?? false;
  $hasInfo = ! empty($info);
  if ($hasInfo && empty($id)) {
    $id = \App\Support\AdminInfoTipInclude::controlIdFromName($name)
      ?? ('admin-checkbox-'.str_replace('.', '', uniqid('', true)));
  }
  $rawLabel = isset($label) ? (string) $label : '';
  $plainLabel = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rawLabel), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  $labelIsHtml = \App\Support\AdminInfoTipInclude::labelIsHtml($rawLabel);
  $split = $hasInfo ? \App\Support\AdminInfoTipInclude::splitLabelHtml($rawLabel) : ['lead' => '', 'last' => '', 'lastIsHtml' => false];
  $tailWord = $hasInfo ? $split['last'] : '';
  $leadHtml = $hasInfo ? $split['lead'] : $rawLabel;
  $tailIsHtml = $hasInfo && ! empty($split['lastIsHtml']);
  $innerClass = $hasInfo
    ? trim('admin-checkbox'.(str_contains($extraClass, 'admin-checkbox--block') ? ' admin-checkbox--block' : ''))
    : $class;
  $rowClass = trim('admin-label-with-tip admin-checkbox-row '.$extraClass);
@endphp
@if ($hasInfo)<span class="{{ $rowClass }}">@endif
<label class="{{ $innerClass }}" @if (! empty($id)) for="{{ $id }}" @endif>
  @if ($hiddenFallback && $name !== '')
    <input type="hidden" name="{{ $name }}" value="0">
  @endif
  <input type="checkbox"
         @if ($name !== '') name="{{ $name }}" @endif
         value="{{ $checkboxValue }}"
         @if (! empty($id)) id="{{ $id }}" @endif
         class="{{ $inputClass }}"
         @checked($checked)
         @foreach ($attrs as $attrKey => $attrVal) {{ $attrKey }}="{{ $attrVal }}" @endforeach>
  @php
    echo '<span class="admin-checkbox__mark" aria-hidden="true"></span>';
    if (! empty($clientFilled)) {
      echo view('admin.quotations.basement._client_filled', ['force' => true])->render();
    }
    if (isset($label)) {
      echo '<span class="admin-checkbox__text">';
      echo $labelIsHtml ? $leadHtml : e($leadHtml);
      echo '</span>';
    }
  @endphp
</label>@if ($hasInfo)<span class="admin-label-with-tip__end">
    @if ($tailWord !== '')
      <label for="{{ $id }}" class="admin-checkbox__tail">@if ($tailIsHtml){!! $tailWord !!}@else{{ $tailWord }}@endif</label>
    @endif
    @include('admin.partials.info_tip', ['tipText' => $info, 'tipLabel' => $plainLabel !== '' ? $plainLabel : 'this field', 'tipHtml' => null, 'tipId' => null])
  </span>
</span>
@endif
