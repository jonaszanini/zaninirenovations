{{--
  Standard admin checkbox — checkbox on the left, label on the right, brand blue when checked.
  Use this partial (or copy the markup) for every new checkbox in the admin.

  @param string       $name            Input name (required)
  @param string|null  $id              Input id
  @param string       $value           Input value (default: 1)
  @param bool         $checked
  @param string|null  $label           Label HTML (right of checkbox)
  @param string       $class           Extra classes on <label>
  @param string       $inputClass      Extra classes on <input>
  @param bool         $hiddenFallback  Emit hidden 0 before checkbox (for Laravel boolean fields)
  @param array        $attrs           Extra HTML attributes on input
--}}
@php
  $value = $value ?? '1';
  $checked = $checked ?? false;
  $class = trim('admin-checkbox ' . ($class ?? ''));
  $inputClass = trim('admin-checkbox__input ' . ($inputClass ?? ''));
  $hiddenFallback = $hiddenFallback ?? false;
  $attrs = $attrs ?? [];
@endphp
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
  @if (isset($label))
    <span class="admin-checkbox__text">{!! $label !!}</span>
  @endif
</label>
