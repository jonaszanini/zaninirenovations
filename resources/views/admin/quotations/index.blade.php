@extends('admin.layouts.app')

@section('heading', 'Quotations')

@section('content')
  @php
    $pq = ($hubProject ?? null) ? ['project' => $hubProject->id] : [];
  @endphp

  @if (!empty($hubProjectError))
    <div class="admin-flash admin-flash--danger">{{ $hubProjectError }}</div>
  @endif

  @if ($hubProject ?? null)
    <div class="admin-flash admin-flash--info">
      Creating quotations for project <strong>{{ $hubProject->name }}</strong> ({{ $hubProject->client_name }}).
      The first saved quotation will attach to this project automatically.
    </div>
  @else
    <div class="admin-card fq-hub-intro">
      <h3 style="margin:0 0 8px;">Standalone quotations</h3>
      <p class="admin-form-help cp-form-help-flush">
        Calculators produce saved quotes that you can tie to a project when you're ready —
        either from here or via the project's Quotation tab.
      </p>
    </div>
  @endif

  <div class="fq-hub-grid">
    <a href="{{ route('admin.quotations.standard.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/standard.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Standard</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.fence.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/fence.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Fence</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.paving.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/flooring.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Paving stones</h4>
      </div>
    </a>

    {{-- Placeholders: calculators not built yet; replace href when routes exist. --}}
    <a href="{{ route('admin.quotations.flooring.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap fq-hub-card-image-wrap--svg" aria-hidden="true">
        <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="">
          <rect x="4" y="36" width="56" height="8" rx="1" fill="#c4a574"/>
          <rect x="4" y="28" width="56" height="8" rx="1" fill="#d4b896"/>
          <rect x="4" y="20" width="56" height="8" rx="1" fill="#e8d4b8"/>
          <rect x="4" y="12" width="56" height="8" rx="1" fill="#f0e6d6"/>
          <line x1="4" y1="12" x2="60" y2="12" stroke="#024768" stroke-width="1.5"/>
          <line x1="20" y1="12" x2="20" y2="44" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 2"/>
          <line x1="44" y1="12" x2="44" y2="44" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 2"/>
        </svg>
      </div>
      <div class="fq-hub-card-body">
        <h4>Flooring</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.tile.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/tile.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Tile</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.painting.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/painting.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Painting</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.stairs.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/stairs.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Stairs</h4>
      </div>
    </a>
  </div>
@endsection
