{{--
  Reusable admin search-select (type-in-field combobox).

  Required:
    $id

  Common:
    $name            Hidden input name (omit if the value should not post)
    $value           Selected id
    $label           Visible field label (omit or empty to hide)
    $labelHint       Optional hint (string or [tipText|tipHtml, tipLabel]) for _cp_field_hint
    $placeholder
    $ariaLabel
    $required        bool
    $wrapperClass
    $hideLabel       bool

  Data:
    $searchUrl       Remote JSON search (?q=)
    $optionsSource   id of a <script type="application/json"> with local options
    $options         list of {id, name, label, ...} when not using $optionsSource
    $selectedOption  full option object for the current value
    $selectedLabel   fallback display text
    $searchFields    list of keys to filter locally
    $metaKeys        keys for the second line (email, phone_display, sku, price_label)
    $fill            map of optionKey => element id to copy into on choose
    $fillSkipIf      CSS selector — skip fill on init if that field has a value
    $addressInputId / $addressHintId  optional Places / directory hint wiring
    $emptyMessage / $emptyQueryMessage
    $attrs           extra HTML attributes on the root (data-preview-target, …)
    $maxOptions
    $listParent      "body" to portal the list (tables / overflow-clipped parents)
    $trailing         Optional HTML after the input (e.g. a manage-clients button)
    $homeDepotPull   bool — offer “Pull SKU from Home Depot” for catalog pickers
--}}
@php
  $id = $id ?? 'admin-ss-'.substr(uniqid(), -6);
  $name = $name ?? null;
  $value = $value ?? '';
  $label = $label ?? null;
  $hideLabel = ! empty($hideLabel) || $label === null || $label === '';
  $placeholder = $placeholder ?? 'Search…';
  $ariaLabel = $ariaLabel ?? ($label ?: 'Search');
  $required = ! empty($required);
  $wrapperClass = trim('admin-search-select '.($wrapperClass ?? ''));
  $selectedOption = $selectedOption ?? null;
  $selectedLabel = $selectedLabel ?? '';
  if (is_array($selectedOption)) {
      $selectedLabel = $selectedOption['name'] ?? ($selectedOption['label'] ?? $selectedLabel);
      if ($value === '' || $value === null) {
          $value = $selectedOption['id'] ?? $value;
      }
  }
  $searchFields = $searchFields ?? ['label', 'name', 'email', 'phone', 'sku', 'vendor'];
  $metaKeys = $metaKeys ?? ['email', 'phone_display', 'sku', 'price_label'];
  $attrs = $attrs ?? [];
  $trailing = $trailing ?? '';
  $homeDepotPull = isset($homeDepotPull)
    ? ! empty($homeDepotPull)
    : (! empty($optionsSource) && str_contains((string) $optionsSource, 'catalog'));
@endphp

<div class="{{ $hideLabel ? '' : 'admin-form-group' }} {{ $wrapperClass }}"
     id="{{ $id }}"
     data-admin-search-select
     @if (! empty($searchUrl)) data-search-url="{{ $searchUrl }}" @endif
     @if (! empty($optionsSource)) data-options-source="{{ $optionsSource }}" @endif
     @if (empty($optionsSource) && ! empty($options)) data-options='@json($options)' @endif
     data-search-fields='@json($searchFields)'
     data-meta-keys='@json($metaKeys)'
     @if (! empty($fill)) data-fill='@json($fill)' @endif
     @if (! empty($fillSkipIf)) data-fill-skip-if="{{ $fillSkipIf }}" @endif
     @if (! empty($addressInputId)) data-address-input-id="{{ $addressInputId }}" @endif
     @if (! empty($addressHintId)) data-address-hint-id="{{ $addressHintId }}" @endif
     @if (! empty($emptyMessage)) data-empty-message="{{ $emptyMessage }}" @endif
     @if (! empty($emptyQueryMessage)) data-empty-query-message="{{ $emptyQueryMessage }}" @endif
     @if (! empty($maxOptions)) data-max-options="{{ (int) $maxOptions }}" @endif
     @if (! empty($listParent)) data-list-parent="{{ $listParent }}" @endif
     @if ($homeDepotPull) data-hd-import-url="{{ route('admin.materials.import-from-home-depot') }}" @endif
     @if (is_array($selectedOption) && ($selectedOption['id'] ?? null)) data-initial='@json($selectedOption)' @endif
     @foreach ($attrs as $attrName => $attrValue)
       {{ $attrName }}="{{ $attrValue }}"
     @endforeach
  >
  @unless ($hideLabel)
    <span class="admin-label-with-tip{{ ! empty($labelHint) ? ' cp-label-with-hint' : '' }}">
      <label for="{{ $id }}-input">{{ $label }}</label>
      @if (! empty($labelHint))
        @php
          $ssHint = is_array($labelHint) ? $labelHint : ['tipText' => $labelHint];
          if (isset($ssHint['text']) && empty($ssHint['tipText'])) {
              $ssHint['tipText'] = $ssHint['text'];
          }
          if (isset($ssHint['html']) && empty($ssHint['tipHtml'])) {
              $ssHint['tipHtml'] = $ssHint['html'];
          }
          $ssHint['tipLabel'] = $ssHint['tipLabel'] ?? $label;
          unset($ssHint['id'], $ssHint['label'], $ssHint['text'], $ssHint['html']);
        @endphp
        @include('admin.client_projects._cp_field_hint', $ssHint)
      @endif
    </span>
  @endunless

  <input type="hidden"
         class="admin-search-select__value"
         id="{{ $id }}-value"
         @if ($name) name="{{ $name }}" @endif
         value="{{ $value }}"
         @if ($required) data-required="1" @endif>

  <div class="admin-search-select__row">
    <div class="admin-search-select__control">
      <input type="text"
             id="{{ $id }}-input"
             class="admin-input admin-search-select__input"
             value="{{ $selectedLabel }}"
             placeholder="{{ $placeholder }}"
             inputmode="search"
             autocomplete="off"
             autocorrect="off"
             spellcheck="false"
             role="combobox"
             aria-expanded="false"
             aria-autocomplete="list"
             aria-controls="{{ $id }}-list"
             aria-label="{{ $ariaLabel }}">
      <button type="button"
              class="admin-search-select__clear"
              hidden
              aria-label="Clear selection">
        <span aria-hidden="true">&times;</span>
      </button>
      <ul id="{{ $id }}-list"
          class="admin-search-select__list"
          role="listbox"
          aria-label="{{ $ariaLabel }} results"
          hidden></ul>
    </div>
    @if ($trailing !== '')
      {!! $trailing !!}
    @endif
  </div>
</div>
