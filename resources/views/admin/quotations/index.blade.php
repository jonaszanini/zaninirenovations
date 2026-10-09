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
      Creating quotations for project <strong>{{ $hubProject->name }}</strong> (@include('admin.partials.client_directory_link', ['project' => $hubProject])).
      The first saved quotation will attach to this project automatically.
    </div>
  @else
    <div class="admin-card fq-hub-intro">
      <h3 style="margin:0 0 8px;">
        Standalone quotations
        <x-admin.info-tip>Calculators produce saved quotes that you can tie to a project when you're ready — either from here or via the project's Quotation tab.</x-admin.info-tip>
      </h3>
    </div>
  @endif

  <div class="fq-hub-grid">
    <a href="{{ route('admin.quotations.standard.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'standard'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Standard</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.fence.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'fence'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Fence</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.paving.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'paving'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Paving stones</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.flooring.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'flooring'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Flooring</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.tile.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'tile'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Tile</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.painting.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'painting'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Painting</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.stairs.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'stairs'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Stairs</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.low-deck.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'low-deck'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Low Deck</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.basement.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'basement'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Basement Renovation</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.trimming.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'trimming'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Trimming</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.shed.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'shed'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Shed</h4>
      </div>
    </a>

    <a href="{{ route('admin.quotations.millwork.index', $pq) }}" class="fq-hub-card">
      <div class="fq-hub-card-image-wrap" aria-hidden="true">
        @include('admin.quotations._hub_icon', ['name' => 'millwork'])
      </div>
      <div class="fq-hub-card-body">
        <h4>Custom Millwork</h4>
      </div>
    </a>
  </div>
@endsection
