@php
  $initialClientForPicker = $initialClientForPicker ?? null;
  $initialClientId = (int) old('client_id', ($initialClientForPicker['id'] ?? 0));
  $manageClientsBtn = '<a href="'.e(route('admin.clients.index')).'" class="admin-btn admin-btn--ghost admin-btn--sm cp-client-directory-picker__manage" title="Manage clients" aria-label="Manage clients"><i class="fa fa-users" aria-hidden="true"></i></a>';
@endphp

@include('admin.partials.search_select', [
  'id' => 'cp-client-combobox',
  'name' => 'client_id',
  'value' => $initialClientId > 0 ? $initialClientId : '',
  'label' => 'Client from directory',
  'labelHint' => [
    'tipText' => 'Type a name, email, or phone, then tap a match. That fills the fields below. If you skip this, a new directory entry is created from the name you enter.',
    'tipLabel' => 'Client from directory',
  ],
  'placeholder' => 'Search name, email, or phone',
  'ariaLabel' => 'Search clients in the directory',
  'searchUrl' => $clientsSearchUrl,
  'selectedOption' => $initialClientId > 0 ? $initialClientForPicker : null,
  'metaKeys' => ['email', 'phone_display'],
  'emptyMessage' => 'No matching clients',
  'emptyQueryMessage' => 'No clients in the directory yet',
  'fill' => [
    'name' => 'cp-client-name',
    'email' => 'cp-client-email',
    'email_secondary' => 'cp-client-email-secondary',
    'phone_display' => 'cp-client-phone',
    'address' => 'cp-client-address',
    'address_place_id' => 'cp-client-address-place-id',
    'address_latitude' => 'cp-client-address-lat',
    'address_longitude' => 'cp-client-address-lng',
  ],
  'fillSkipIf' => '#cp-client-name',
  'addressInputId' => 'cp-client-address',
  'addressHintId' => 'cp-client-address-directory-hint',
  'wrapperClass' => 'cp-client-directory-picker',
  'trailing' => $manageClientsBtn,
])
