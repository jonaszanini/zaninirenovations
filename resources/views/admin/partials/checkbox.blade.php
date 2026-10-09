{{--
  Standard admin checkbox — checkbox on the left, label on the right, brand blue when checked.
  Use this partial (or copy the markup) for every new checkbox in the admin.

  @param string       $name            Input name (required)
  @param string|null  $id              Input id
  @param string       $value           Input value (default: 1)
  @param bool         $checked
  @param string|null  $label           Label HTML (right of checkbox)
  @param string|null  $info            Optional explanation shown on the info icon
  @param string       $class           Extra classes on <label>
  @param string       $inputClass      Extra classes on <input> (default: admin-checkbox__input)
  @param bool         $hiddenFallback  Emit hidden 0 before checkbox (for Laravel boolean fields)
  @param bool         $clientFilled    Show “filled from client form” icon
  @param array        $attrs           Extra HTML attributes on input
--}}
@php
  $value = $value ?? '1';
  $checked = $checked ?? false;
  $class = trim('admin-checkbox ' . ($class ?? ''));
  $inputClass = trim('admin-checkbox__input ' . ($inputClass ?? ''));
  $hiddenFallback = $hiddenFallback ?? false;
  $attrs = $attrs ?? [];
  $clientFilled = $clientFilled ?? false;
@endphp
@if (! empty($info))<span class="admin-label-with-tip">@endif
<label class="{{ $class }}" @if (! empty($id)) for="{{ $id }}" @endif>
  @if ($hiddenFallback)
    <input type="hidden" name="{{ $name }}" value="0">
  @endif
  <input type="checkbox"
         name="{{ $name }}"
         value="{{ $value }}"
         @if (! empty($id)) id="{{ $id }}" @endif
         class="{{ $inputClass }}"
         @checked($checked)
         @foreach ($attrs as $attrKey => $attrVal) {{ $attrKey }}="{{ $attrVal }}" @endforeach>
  <span class="admin-checkbox__mark" aria-hidden="true"></span>
  @if (! empty($clientFilled))
    @include('admin.quotations.basement._client_filled', ['force' => true])
  @endif
  @if (isset($label))
    <span class="admin-checkbox__text">{!! $label !!}</span>
  @endif
</label>
@if (! empty($info))
  @include('admin.partials.info_tip', ['tipText' => $info, 'tipLabel' => trim(strip_tags($label ?? 'this field'))])
</span>
@endif
