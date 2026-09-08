@extends('admin.layouts.app')

@section('heading', 'Search')

@section('content')
  @if (mb_strlen($q) < 2)
    <div class="admin-card">
      <div class="admin-empty">
        <p>Type at least 2 characters to search clients, projects, and quotations.</p>
      </div>
    </div>
  @elseif ($resultCount === 0)
    <div class="admin-card">
      <div class="admin-empty">
        <p>No matches for "<strong>{{ $q }}</strong>".</p>
      </div>
    </div>
  @else
    <p class="admin-toolbar-lede" style="margin-bottom:16px;">Results for "<strong>{{ $q }}</strong>"</p>

    @if ($clients->isNotEmpty())
      <section class="admin-card" style="margin-bottom:16px;">
        <h2 class="gs-section-title"><i class="fa fa-address-book"></i> Clients ({{ $clients->count() }})</h2>
        <div class="admin-table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($clients as $client)
                <tr>
                  <td><strong>{{ $client->name }}</strong></td>
                  <td>{{ $client->email ?: '—' }}</td>
                  <td>{{ $client->phone ? \App\Models\ClientProject::formatPhone($client->phone) : '—' }}</td>
                  <td style="white-space:nowrap;">
                    <a href="{{ route('admin.clients.edit', $client) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Open</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    @if ($projects->isNotEmpty())
      <section class="admin-card" style="margin-bottom:16px;">
        <h2 class="gs-section-title"><i class="fa fa-briefcase"></i> Projects ({{ $projects->count() }})</h2>
        <div class="admin-table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Project</th>
                <th>Client</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($projects as $project)
                <tr>
                  <td><strong>{{ $project->name }}</strong></td>
                  <td>@include('admin.partials.client_directory_link', ['project' => $project])</td>
                  <td>
                    <span class="admin-badge" style="background:{{ $project->statusColor() }}20;color:{{ $project->statusColor() }};">
                      {{ $project->statusLabel() }}
                    </span>
                  </td>
                  <td style="white-space:nowrap;">
                    <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Open</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    @if ($quotations->isNotEmpty())
      <section class="admin-card">
        <h2 class="gs-section-title"><i class="fa fa-file-text-o"></i> Quotations &amp; estimates ({{ $quotations->count() }})</h2>
        <div class="admin-table-scroll">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Title</th>
                <th>Client</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($quotations as $quotation)
                <tr>
                  <td><code class="admin-code">{{ $quotation->quotation_number ?: '—' }}</code></td>
                  <td>{{ $quotation->resolvedTitle() }}</td>
                  <td>@include('admin.partials.client_directory_link', [
                    'project' => $quotation->project,
                    'label' => $quotation->project?->client_name,
                  ])</td>
                  <td>{{ $quotation->statusLabel() }}</td>
                  <td style="white-space:nowrap;">
                    @if ($quotation->project)
                      <a href="{{ route('admin.client-projects.show', $quotation->project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Open project</a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif
  @endif
@endsection
