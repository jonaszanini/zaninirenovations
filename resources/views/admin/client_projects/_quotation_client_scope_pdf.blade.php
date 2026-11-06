@php
  $clientScope = $quotation->clientScopeForPdf();
  $packages = $quotation->valuePackagesForPdf();
  $selectedTier = $quotation->acceptedValueTier() ?: $quotation->selectedValueTier();
  $fmt = $fmt ?? fn ($n) => 'CAD$'.number_format((float) $n, 2, '.', ',');
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
@if ($packages !== [])
  <div class="quote-invest">
    <p class="quote-client-scope-kicker">Invest</p>
    <table class="quote-invest-table">
      <thead>
        <tr>
          @foreach (['base' => 'Base', 'standard' => 'Standard', 'upgrade' => 'Upgrade'] as $tier => $label)
            <th class="{{ $selectedTier === $tier ? 'is-selected' : '' }}">{{ $label }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        <tr>
          @foreach (['base', 'standard', 'upgrade'] as $tier)
            @php $pkg = $packages[$tier] ?? null; @endphp
            <td>
              <p class="quote-invest-amount">{{ $pkg ? $fmt($pkg['grand_total'] ?? 0) : '—' }}</p>
              <p class="quote-invest-note">{{ $pkg['note'] ?? '' }}</p>
              @if ($selectedTier === $tier)
                <p class="quote-invest-pick">Selected at acceptance</p>
              @endif
            </td>
          @endforeach
        </tr>
      </tbody>
    </table>
  </div>
@endif
