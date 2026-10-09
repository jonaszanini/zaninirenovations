@extends('admin.layouts.app')

@php
  $layoutId = $layoutId ?? '';
  $option = $option ?? [];
  $assemblies = $assemblies ?? [];
  $existingQuotes = $existingQuotes ?? collect();
  $clientProject = $clientProject ?? null;
  $defaultClientName = $defaultClientName ?? '';
  $defaultTitle = $defaultTitle ?? '';
  $fmt = static function (?float $n): string {
      if ($n === null) {
          return '—';
      }
      $rounded = abs($n - round($n)) < 0.001
          ? (string) (int) round($n)
          : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

      return $rounded !== '' ? $rounded : '0';
  };
@endphp

@section('heading', 'Add kits to quote')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar util-rq-toolbar">
    <p class="admin-toolbar-lede">
      {{ $option['name'] ?? 'Room Area' }}
      @if (!empty($option['meta']))
        <span class="util-rq-toolbar-meta">{{ $option['meta'] }}</span>
      @endif
    </p>
    <a href="{{ $backUrl ?? route('admin.utilities.room-area') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> Room Area
    </a>
  </div>

  <form class="util-rq util-ra-kit-quote" method="post" action="{{ route('admin.utilities.room-area.assemblies.quote.store') }}">
    @csrf
    <input type="hidden" name="layout" value="{{ $layoutId }}">

    <section class="admin-card util-rq-card">
      <div class="util-rq-card-head">
        <h3 class="util-rq-card-title">Kit quantities</h3>
        @include('admin.utilities._room_area_help_pop', [
          'tipId' => 'kit-quote',
          'label' => 'About adding kits',
          'text' => 'Quantities come from this layout and each kit’s formula. Whole units already round up. This only adds materials when you click Add to quote.',
        ])
      </div>
      <p class="util-rq-hint">Pick the kits to add. Prices come from the catalogue when a line is linked.</p>

      @error('assembly_ids')
        <p class="admin-form-error">{{ $message }}</p>
      @enderror

      @forelse ($assemblies as $kit)
        <article class="util-ra-kit-quote__kit">
          <label class="util-ra-kit-quote__pick">
            <input type="checkbox" name="assembly_ids[]" value="{{ $kit['id'] }}" checked>
            <strong>{{ $kit['name'] }}</strong>
          </label>
          <ul class="util-ra-kit-quote__lines">
            @foreach ($kit['lines'] as $line)
              <li>
                <span>{{ $line['name'] }}</span>
                @if (!empty($line['error']))
                  <em class="util-ra-kit-quote__err">{{ $line['error'] }}</em>
                @else
                  <strong>{{ $fmt($line['qty']) }} {{ $line['unit'] }}</strong>
                @endif
                @if (!empty($line['material_name']))
                  <span class="util-ra-kit-quote__mat">{{ $line['material_name'] }}</span>
                @endif
                @php $lineKey = (string) ($line['id'] ?? $line['name']); @endphp
                <input type="hidden" name="unit_price[{{ $lineKey }}]" value="{{ $line['unit_price'] ?? '' }}">
                @if (!empty($line['material_id']))
                  <input type="hidden" name="catalog_material_id[{{ $lineKey }}]" value="{{ $line['material_id'] }}">
                @endif
              </li>
            @endforeach
          </ul>
        </article>
      @empty
        <p class="util-rq-hint">No kits yet. Open Room Area → Kits to add Drywall wall, LVP floor, or Baseboard.</p>
      @endempty
    </section>

    <section class="admin-card util-rq-card">
      <h3 class="util-rq-card-title">Quotation</h3>
      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="ra-kit-quote-existing">Add to existing</label>
          <select class="admin-input" id="ra-kit-quote-existing" name="quotation_id">
            <option value="">New quotation</option>
            @foreach ($existingQuotes as $quote)
              <option value="{{ $quote->id }}" @selected((string) old('quotation_id') === (string) $quote->id)>
                {{ $quote->quote_number }} — {{ $quote->display_title ?: $quote->client_name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="admin-form-group">
          <label for="ra-kit-quote-client">Client name</label>
          <input class="admin-input" type="text" id="ra-kit-quote-client" name="client_name" value="{{ old('client_name', $defaultClientName) }}" maxlength="160">
          @error('client_name')
            <p class="admin-form-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="admin-form-group">
          <label for="ra-kit-quote-title">Title</label>
          <input class="admin-input" type="text" id="ra-kit-quote-title" name="display_title" value="{{ old('display_title', $defaultTitle) }}" maxlength="255">
        </div>
      </div>
      <div class="util-rq-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Add to quote</button>
        <a class="admin-btn admin-btn--ghost" href="{{ $backUrl ?? route('admin.utilities.room-area') }}">Cancel</a>
      </div>
    </section>
  </form>
@endsection
