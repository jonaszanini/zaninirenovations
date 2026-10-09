{{--
  Standard labeled admin form field.

  @param string       $label      Visible label text (required)
  @param string       $name       Input name (required)
  @param string       $type       text|email|url|password|date|number|tel|search|textarea|select (default: text)
  @param string|null  $id         Defaults to admin-field-{name}
  @param bool         $required   Show required asterisk and HTML required attribute
  @param string|null  $value      Field value (old() handled by caller when null)
  @param string|null  $help       Help text shown on the info-tip next to the label
  @param string       $inputClass Extra classes on the control (e.g. cp-money-input)
  @param array        $attrs      Extra HTML attributes on input/textarea/select
  @param slot         options     For type=select: <option> elements
--}}
@php
  $type = $type ?? 'text';
  $id = $id ?? 'admin-field-'.preg_replace('/[^a-z0-9_-]+/i', '-', $name);
  $required = $required ?? false;
  $inputClass = trim('admin-input '.($inputClass ?? ''));
  $attrs = $attrs ?? [];
  $invalid = $errors->has($name);
@endphp
<div class="admin-form-group">
  <span class="admin-label-with-tip">
    <label for="{{ $id }}">
      {{ $label }}
      @if ($required)<span class="cp-required">*</span>@endif
    </label>
    @if (! empty($help))
      @include('admin.partials.info_tip', ['text' => $help, 'tipLabel' => $label])
    @endif
  </span>

  @if ($type === 'textarea')
    <textarea id="{{ $id }}" name="{{ $name }}"
              class="{{ $inputClass }}@if($invalid) is-invalid @endif"
              @if ($required) required @endif
              @foreach ($attrs as $attrKey => $attrVal) {{ $attrKey }}="{{ $attrVal }}" @endforeach>{{ $value ?? old($name) }}</textarea>
  @elseif ($type === 'select')
    <select id="{{ $id }}" name="{{ $name }}"
            class="{{ $inputClass }}@if($invalid) is-invalid @endif"
            @if ($required) required @endif
            @foreach ($attrs as $attrKey => $attrVal) {{ $attrKey }}="{{ $attrVal }}" @endforeach>
      {{ $slot ?? '' }}
    </select>
  @else
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
           value="{{ $value ?? old($name) }}"
           class="{{ $inputClass }}@if($invalid) is-invalid @endif"
           @if ($required) required @endif
           @foreach ($attrs as $attrKey => $attrVal) {{ $attrKey }}="{{ $attrVal }}" @endforeach>
  @endif

  @error($name)
    <p class="admin-form-error">{{ $message }}</p>
  @enderror
</div>
