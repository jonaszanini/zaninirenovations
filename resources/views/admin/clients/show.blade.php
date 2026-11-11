@extends('admin.layouts.app')

@section('heading', $client->name)

@section('content')
  @php
    $formattedPhone = $client->phone ? \App\Models\ClientProject::formatPhone($client->phone) : '';
    $telHref = $formattedPhone !== '' ? \App\Models\ClientProject::telHref($client->phone) : null;
    $ownAddress = trim((string) $client->address);
    $mapsUrl = $ownAddress !== '' ? $client->googleMapsSearchUrl() : null;
  @endphp

  <div class="cl-show-toolbar">
    <a href="{{ route('admin.clients.edit', $client) }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-pencil"></i> Edit
    </a>
    <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost">
      Clients
    </a>
  </div>

  <div class="admin-card cl-show-card">
    <dl class="cl-show-fields">
      <div class="cl-show-field">
        <dt>Name</dt>
        <dd>{{ $client->name }}</dd>
      </div>
      <div class="cl-show-field">
        <dt>Email</dt>
        <dd>
          @if ($client->email)
            @include('admin.partials.copy_value', [
              'value' => $client->email,
              'href' => 'mailto:'.$client->email,
              'copyLabel' => 'Copy email',
            ])
          @endif
        </dd>
      </div>
      <div class="cl-show-field">
        <dt>Secondary email</dt>
        <dd>
          @if ($client->email_secondary)
            @include('admin.partials.copy_value', [
              'value' => $client->email_secondary,
              'href' => 'mailto:'.$client->email_secondary,
              'copyLabel' => 'Copy secondary email',
            ])
          @endif
        </dd>
      </div>
      <div class="cl-show-field">
        <dt>Phone</dt>
        <dd>
          @if ($formattedPhone !== '')
            @include('admin.partials.copy_value', [
              'value' => $formattedPhone,
              'href' => $telHref,
              'copyLabel' => 'Copy phone',
            ])
          @endif
        </dd>
      </div>
      <div class="cl-show-field cl-show-field--full">
        <dt>Address</dt>
        <dd>
          @if ($ownAddress !== '' && $mapsUrl)
            @include('admin.partials.copy_value', [
              'value' => $ownAddress,
              'href' => $mapsUrl,
              'copyLabel' => 'Copy address',
              'external' => true,
              'class' => 'cl-copy-field--address',
            ])
          @elseif ($ownAddress !== '')
            @include('admin.partials.copy_value', [
              'value' => $ownAddress,
              'copyLabel' => 'Copy address',
              'class' => 'cl-copy-field--address',
            ])
          @endif
        </dd>
      </div>
      <div class="cl-show-field cl-show-field--full">
        <dt>Notes</dt>
        <dd>
          @if (filled($client->notes))
            <div class="cl-show-notes">{{ $client->notes }}</div>
          @endif
        </dd>
      </div>
      <div class="cl-show-field cl-show-field--full">
        <dt>Tags</dt>
        <dd>
          @if ($client->tags->isNotEmpty())
            <div class="cl-tag-picker">
              @foreach ($client->tags as $tag)
                <span class="admin-badge" style="background:{{ $tag->color }}1a;color:{{ $tag->color }};border:1px solid {{ $tag->color }}55;">{{ $tag->name }}</span>
              @endforeach
            </div>
          @endif
        </dd>
      </div>
    </dl>
  </div>

  <div class="admin-card cl-show-card">
    <h2 class="cl-show-section-title">Projects</h2>
    @if ($client->projects->isEmpty())
      <p class="admin-form-help">No projects linked to this client.</p>
    @else
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--stack cl-show-projects">
          <thead>
            <tr>
              <th>Project</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($client->projects as $project)
              <tr>
                <td data-label="Project">
                  <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-client-link">
                    {{ $project->name }}
                  </a>
                  <span class="admin-code">{{ $project->referenceCode() }}</span>
                </td>
                <td data-label="Status">
                  <span class="admin-badge" style="background:{{ $project->statusColor() }}20;color:{{ $project->statusColor() }};">
                    {{ $project->statusLabel() }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection
