@extends('admin.layouts.app')

@section('heading', 'Clients')

@section('content')
  @php $isFiltered = $q !== '' || $tagId > 0; @endphp
  <div class="admin-toolbar cp-clients-toolbar">
    <form method="get" action="{{ route('admin.clients.index') }}" class="cp-clients-toolbar-search" role="search">
      <div class="cp-clients-toolbar-search__row">
        <input type="search"
               name="q"
               value="{{ $q }}"
               placeholder="Search clients"
               title="Search name, email, phone, address"
               class="admin-input cp-clients-toolbar-search__input"
               aria-label="Search name, email, phone, address">
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-search"></i> Search
        </button>
      </div>
      @if ($tags->isNotEmpty())
        <select name="tag" class="admin-input" onchange="this.form.submit()" aria-label="Filter by tag">
          <option value="0">All tags</option>
          @foreach ($tags as $tag)
            <option value="{{ $tag->id }}" @selected($tagId === $tag->id)>{{ $tag->name }}</option>
          @endforeach
        </select>
      @endif
    </form>
    <div class="cp-clients-toolbar-actions">
      <a href="{{ route('admin.client-tags.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" title="Manage client tags">
        <i class="fa fa-tags"></i> Tags
      </a>
      <a href="{{ route('admin.clients.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> New client
      </a>
    </div>
    <p class="admin-form-help cp-clients-toolbar-total">
      @if ($isFiltered)
        {{ number_format($clients->total()) }} of {{ number_format($totalClients) }} {{ $totalClients === 1 ? 'client' : 'clients' }}
        <a href="{{ route('admin.clients.index') }}" class="cp-clients-toolbar-clear" title="Clear search" aria-label="Clear search">
          <i class="fa fa-times" aria-hidden="true"></i>
        </a>
      @else
        {{ number_format($totalClients) }} {{ $totalClients === 1 ? 'client' : 'clients' }} in database
      @endif
    </p>
  </div>

  <div class="admin-card cp-clients-table-card">
    <div class="admin-table-scroll">
      <table class="admin-table admin-table--stack cp-clients-table">
        <thead>
          <tr>
            <th class="cp-clients-col-name">Name</th>
            <th class="cp-clients-col-email">Email</th>
            <th class="cp-clients-col-phone">Phone</th>
            <th class="cp-clients-col-address">Address</th>
            <th class="cp-clients-col-projects">Projects</th>
            <th class="cp-clients-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($clients as $client)
            @php
              $listAddress = $client->listAddress();
              $mapsUrl = $listAddress ? $client->googleMapsSearchUrl() : null;
              $formattedPhone = $client->phone ? \App\Models\ClientProject::formatPhone($client->phone) : '';
              $telHref = $formattedPhone !== '' ? \App\Models\ClientProject::telHref($client->phone) : null;
            @endphp
            <tr>
              <td class="cp-clients-col-name" data-label="Name">
                <strong>@include('admin.partials.client_directory_link', [
                  'client' => $client,
                  'url' => route('admin.clients.show', $client),
                  'title' => 'View client',
                ])</strong>
                @if ($client->tags->isNotEmpty())
                  <div class="cl-tag-picker" style="margin-top:4px;">
                    @foreach ($client->tags as $tag)
                      <span class="admin-badge" style="background:{{ $tag->color }}1a;color:{{ $tag->color }};border:1px solid {{ $tag->color }}55;">{{ $tag->name }}</span>
                    @endforeach
                  </div>
                @endif
              </td>
              <td class="cp-clients-col-email" data-label="Email">
                <span class="cl-copy-fields">
                  @if ($client->email)
                    @include('admin.partials.copy_value', [
                      'value' => $client->email,
                      'href' => 'mailto:'.$client->email,
                      'copyLabel' => 'Copy email',
                    ])
                  @endif
                  @if ($client->email_secondary)
                    @include('admin.partials.copy_value', [
                      'value' => $client->email_secondary,
                      'href' => 'mailto:'.$client->email_secondary,
                      'copyLabel' => 'Copy secondary email',
                    ])
                  @endif
                  @if (! $client->email && ! $client->email_secondary)
                    —
                  @endif
                </span>
              </td>
              <td class="cp-clients-col-phone" data-label="Phone">
                @if ($formattedPhone !== '')
                  @include('admin.partials.copy_value', [
                    'value' => $formattedPhone,
                    'href' => $telHref,
                    'copyLabel' => 'Copy phone',
                  ])
                @else
                  —
                @endif
              </td>
              <td class="cp-clients-col-address" data-label="Address">
                @if ($mapsUrl)
                  @include('admin.partials.copy_value', [
                    'value' => $listAddress,
                    'href' => $mapsUrl,
                    'copyLabel' => 'Copy address',
                    'external' => true,
                    'class' => 'cl-copy-field--address',
                  ])
                @endif
              </td>
              <td class="cp-clients-col-projects" data-label="Projects">{{ $client->projects_count ?: '0' }}</td>
              <td class="admin-table__actions cp-clients-col-actions" data-label="">
                <a href="{{ route('admin.clients.edit', $client) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="admin-form-help">
                @if ($isFiltered)
                  No matching clients.
                @else
                  No clients yet. Add one or create a project — existing project contacts can be imported via migration.
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($clients->hasPages())
      <div style="margin-top:16px;">{{ $clients->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection
