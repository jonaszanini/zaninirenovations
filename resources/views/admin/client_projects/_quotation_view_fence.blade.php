{{-- Fence quotation — read-only detail partial for the View drawer.
     Expects: $sourceModel (FenceQuotation) --}}
@php
  $sides   = is_array($sourceModel->sides) ? $sourceModel->sides : [];
  $totalFt = collect($sides)->sum(fn ($s) => (float) ($s['length_ft'] ?? 0));
  $height  = (int) round((float) $sourceModel->fence_height_ft);
  $style   = $sourceModel->board_style ?? 'standard';
  $styleLbl = $style === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING ? 'Overlapping' : 'Standard';
  $overlap = (float) $sourceModel->overlap_in;
  $gates   = (int) $sourceModel->gate_count;
  $labour6 = (float) $sourceModel->labour_rate_per_ft;
  $labour8 = (float) $sourceModel->labour_rate_8ft_per_ft;
  $calc    = is_array($sourceModel->calculation) ? $sourceModel->calculation : [];
@endphp

<div class="cp-vd-section">
  <div class="cp-vd-section__title">Project inputs</div>

  @if ($sourceModel->client_name)
    <div class="cp-vd-grid" style="margin-bottom:10px;">
      <div class="cp-vd-field cp-vd-field--full">
        <span class="cp-vd-field__label">Client name</span>
        <span class="cp-vd-field__value">@include('admin.partials.client_directory_link', ['project' => $project ?? null, 'label' => $sourceModel->client_name])</span>
      </div>
    </div>
  @endif

  {{-- Fence sides --}}
  <div style="margin-bottom:12px;">
    <span class="cp-vd-field__label" style="display:block;margin-bottom:6px;">Fence sides ({{ count($sides) }}) — {{ rtrim(rtrim(number_format($totalFt, 2),'0'),'.') }} linear ft total</span>
    <ul class="cp-vd-sides-list">
      @forelse ($sides as $i => $side)
        <li class="cp-vd-side-chip">
          Side {{ $i + 1 }}: {{ rtrim(rtrim(number_format((float)($side['length_ft']??0),2),'0'),'.') }} ft
        </li>
      @empty
        <li class="cp-vd-side-chip">No sides recorded</li>
      @endforelse
    </ul>
  </div>

  <div class="cp-vd-grid">
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Fence height</span>
      <span class="cp-vd-field__value">{{ $height }}'</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Board style</span>
      <span class="cp-vd-field__value">{{ $styleLbl }}</span>
    </div>
    @if ($style === \App\Models\FenceQuotation::BOARD_STYLE_OVERLAPPING && $overlap > 0)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Overlap</span>
        <span class="cp-vd-field__value">{{ rtrim(rtrim(number_format($overlap,3),'0'),'.') }} in</span>
      </div>
    @endif
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Gate count</span>
      <span class="cp-vd-field__value">{{ $gates }}</span>
    </div>
    <div class="cp-vd-field">
      <span class="cp-vd-field__label">Labour rate (6 ft)</span>
      <span class="cp-vd-field__value">${{ number_format($labour6, 2) }}/ft</span>
    </div>
    @if ($height >= 8)
      <div class="cp-vd-field">
        <span class="cp-vd-field__label">Labour rate (8 ft)</span>
        <span class="cp-vd-field__value">${{ number_format($labour8, 2) }}/ft</span>
      </div>
    @endif
  </div>
</div>

{{-- Calculated totals --}}
@if (!empty($calc))
  <div class="cp-vd-section">
    <div class="cp-vd-section__title">Calculator summary</div>
    <div class="cp-vd-grid">
      @if (isset($sourceModel->materials_subtotal))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Materials (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->materials_subtotal) }}</span>
        </div>
      @endif
      @if (isset($sourceModel->labour_total))
        <div class="cp-vd-field">
          <span class="cp-vd-field__label">Labour (pre-tax)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->labour_total) }}</span>
        </div>
      @endif
      @if (isset($sourceModel->grand_total))
        <div class="cp-vd-field cp-vd-field--highlight">
          <span class="cp-vd-field__label">Grand total (incl. HST)</span>
          <span class="cp-vd-field__value">{{ \App\Models\ClientProject::formatMoney((float)$sourceModel->grand_total) }}</span>
        </div>
      @endif
    </div>
  </div>
@endif
