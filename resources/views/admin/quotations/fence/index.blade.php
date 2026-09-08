@extends('admin.layouts.app')

@section('heading', 'Fence Quotations')

@section('content')
  @php $qpid = request('project'); @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.index', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All quotation types
      </a>
      <a href="{{ route('admin.quotations.fence.config') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Configuration
      </a>
    </div>
    <a href="{{ route('admin.quotations.fence.create', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New fence quotation
    </a>
  </div>

  @include('admin.quotations._index_project_context')

  @if ($quotations->isEmpty())
    <div class="admin-card" style="text-align:center;padding:36px 24px;">
      <p class="admin-form-help cp-form-help-flush">
        @if ($filterUnlinkedOnly ?? false)
          No unlinked fence quotations available. Click <strong>New fence quotation</strong> to open the calculator for this project.
        @else
          No fence quotations yet. Click <strong>New fence quotation</strong> to open the calculator.
        @endif
      </p>
    </div>
  @else
    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table fq-list-table">
          <thead>
            <tr>
              <th>Number</th>
              <th>Client</th>
              <th>Height</th>
              <th>Linear ft</th>
              <th>Gates</th>
              <th>Total</th>
              <th>Linked project</th>
              <th>Updated</th>
              <th style="width: 260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($quotations as $q)
              @php
                $totalFt = collect($q->sides ?? [])->sum(fn ($s) => (float) ($s['length_ft'] ?? 0));
              @endphp
              <tr>
                <td><strong>{{ $q->quote_number }}</strong></td>
                <td>@include('admin.partials.client_directory_link', ['project' => $q->project, 'label' => $q->client_name])</td>
                <td>{{ rtrim(rtrim(number_format((float) $q->fence_height_ft, 2), '0'), '.') }}'</td>
                <td>{{ rtrim(rtrim(number_format($totalFt, 2), '0'), '.') }}</td>
                <td>{{ $q->gate_count }}</td>
                <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($q->grand_total) }}</td>
                <td>
                  @if ($q->project)
                    <a href="{{ route('admin.client-projects.show', $q->project) }}#quotation">
                      {{ $q->project->name }}
                    </a>
                  @else
                    <span class="admin-form-help">—</span>
                  @endif
                </td>
                <td>
                  <span class="admin-form-help">{{ $q->updated_at ? \App\Support\EasternStandardTimeDisplay::formatInstantCalendarDate($q->updated_at) : '' }}</span>
                </td>
                <td>
                  <div class="fq-list-actions">
                    <a href="{{ route('admin.quotations.fence.edit', $q) }}"
                       class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <form method="post"
                          action="{{ route('admin.quotations.fence.destroy', $q) }}"
                          class="cp-inline-form"
                          onsubmit="return confirm('Delete fence quotation {{ $q->quote_number }}? This cannot be undone.');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                        <i class="fa fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:16px;">
      {{ $quotations->links() }}
    </div>
  @endif
@endsection
