@php
  $initialClientForPicker = $initialClientForPicker ?? null;
  $initialClientId = (int) old('client_id', ($initialClientForPicker['id'] ?? 0));
  $initialLabel = '';
  if (is_array($initialClientForPicker) && $initialClientId > 0) {
      $initialLabel = $initialClientForPicker['name'] ?? ($initialClientForPicker['label'] ?? '');
  }
@endphp

<div class="admin-form-group cp-client-directory-picker">
  <label for="cp-client-combobox-input" class="cp-label-with-hint">
    Client from directory
    @include('admin.client_projects._cp_field_hint', [
      'text' => 'Type a name, email, or phone, then tap a match. That fills the fields below. If you skip this, a new directory entry is created from the name you enter.',
    ])
  </label>

  <input type="hidden"
         name="client_id"
         id="cp-client-id"
         value="{{ $initialClientId > 0 ? $initialClientId : '' }}">

  <div class="cp-client-assign">
    <div class="cp-client-combobox"
         id="cp-client-combobox"
         data-search-url="{{ $clientsSearchUrl }}"
         @if ($initialClientId > 0 && is_array($initialClientForPicker))
           data-initial='@json($initialClientForPicker)'
         @endif>
      <div class="cp-client-combobox__control">
        <input type="text"
               id="cp-client-combobox-input"
               class="admin-input cp-client-combobox__input"
               value="{{ $initialLabel }}"
               placeholder="Search name, email, or phone"
               inputmode="search"
               autocomplete="off"
               autocorrect="off"
               spellcheck="false"
               role="combobox"
               aria-expanded="false"
               aria-autocomplete="list"
               aria-controls="cp-client-combobox-list"
               aria-label="Search clients in the directory">
        <button type="button"
                id="cp-client-combobox-clear"
                class="cp-client-combobox__clear"
                hidden
                aria-label="Clear selected client">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <ul id="cp-client-combobox-list"
          class="cp-client-combobox__list"
          role="listbox"
          aria-label="Matching clients"
          hidden></ul>
    </div>
    <a href="{{ route('admin.clients.index') }}"
       class="admin-btn admin-btn--ghost admin-btn--sm cp-client-directory-picker__manage"
       title="Manage clients"
       aria-label="Manage clients">
      <i class="fa fa-users" aria-hidden="true"></i>
    </a>
  </div>
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-client-combobox.js') }}?v={{ @filemtime(public_path('js/admin-client-combobox.js')) ?: time() }}"></script>
  @endpush
@endonce
