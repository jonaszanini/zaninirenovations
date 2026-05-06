@extends('admin.layouts.app')

@section('heading', 'Quotations')

@section('content')
  @php
    $pq = ($hubProject ?? null) ? ['project' => $hubProject->id] : [];
  @endphp

  @if (!empty($hubProjectError))
    <div class="admin-flash admin-flash--danger" style="margin-bottom:16px;">{{ $hubProjectError }}</div>
  @endif

  @if ($hubProject ?? null)
    <div class="admin-flash admin-flash--info" style="margin-bottom:16px;">
      Creating quotations for project <strong>{{ $hubProject->name }}</strong> ({{ $hubProject->client_name }}).
      The first saved quotation will attach to this project automatically.
    </div>
  @else
    <div class="admin-card fq-hub-intro">
      <h3 style="margin:0 0 8px;">Standalone quotations</h3>
      <p class="admin-form-help" style="margin:0;">
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
    <a href="#" class="fq-hub-card fq-hub-card--placeholder" onclick="event.preventDefault();" aria-label="Tile quotation (coming soon)">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/tile.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Tile</h4>
      </div>
    </a>

    <a href="#" class="fq-hub-card fq-hub-card--placeholder" onclick="event.preventDefault();" aria-label="Painting quotation (coming soon)">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/painting.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Painting</h4>
      </div>
    </a>

    <a href="#" class="fq-hub-card fq-hub-card--placeholder" onclick="event.preventDefault();" aria-label="Stairs quotation (coming soon)">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        <img src="{{ asset('images/quotations/stairs.webp') }}" alt="" class="fq-hub-card-image">
      </div>
      <div class="fq-hub-card-body">
        <h4>Stairs</h4>
      </div>
    </a>
  </div>
@endsection
