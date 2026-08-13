{{--
  Shared client-from-DB picker for the "Connect to a project → New project" form.

  Parameters (all required):
    $clientsSearchUrl
    $pickerId
    $nameInputId, $emailInputId, $phoneInputId, $addressInputId

  Optional:
    $hideDivider
--}}

<div class="fp-cp-wrap{{ ! empty($hideDivider) ? ' fp-cp-wrap--inline' : '' }}">
  @include('admin.partials.search_select', [
    'id' => $pickerId,
    'name' => 'fp_client_id',
    'label' => 'Autofill from client directory',
    'placeholder' => 'Search name, email, or phone',
    'ariaLabel' => 'Search clients from directory',
    'searchUrl' => $clientsSearchUrl,
    'metaKeys' => ['email', 'phone_display'],
    'emptyMessage' => 'No matching clients',
    'emptyQueryMessage' => 'No clients in the directory yet',
    'fill' => [
      'name' => $nameInputId,
      'email' => $emailInputId,
      'phone_display' => $phoneInputId,
      'address' => $addressInputId,
    ],
  ])
</div>
@if (empty($hideDivider))
<div class="fp-cp-divider">or fill manually</div>
@endif
