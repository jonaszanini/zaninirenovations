@php
  $clientScope = $quotation->clientScopeForPdf();
@endphp
@if ($clientScope)
  <div class="quote-client-scope">
    <p class="quote-client-scope-kicker">Value</p>
    <table class="quote-client-scope-table" role="presentation">
      <tr>
        <td>
          @include('admin.client_projects._quotation_client_scope_block', ['title' => 'Included', 'body' => $clientScope['included'] ?? ''])
        </td>
        <td>
          @include('admin.client_projects._quotation_client_scope_block', ['title' => 'Not included', 'body' => $clientScope['not_included'] ?? ''])
        </td>
      </tr>
    </table>
    <p class="quote-client-scope-kicker">Special terms</p>
    <table class="quote-client-scope-table" role="presentation">
      <tr>
        <td>
          @include('admin.client_projects._quotation_client_scope_block', ['title' => 'Assumptions', 'body' => $clientScope['assumptions'] ?? ''])
        </td>
        <td>
          @include('admin.client_projects._quotation_client_scope_block', ['title' => 'Allowances', 'body' => $clientScope['allowances'] ?? ''])
        </td>
      </tr>
    </table>
  </div>
@endif
