@extends('admin.layouts.app')

@section('heading', 'Flooring quotations')

@section('content')
  @php $qpid = request('project'); @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.index', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All quotation types
      </a>
      <a href="{{ route('admin.quotations.flooring.price-configuration', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
    </div>
    <a href="{{ route('admin.quotations.flooring.create', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New flooring quotation
    </a>
  </div>

  @include('admin.quotations._index_project_context')

  @if ($quotations->isEmpty())
    <div class="admin-card" style="text-align:center;padding:36px 24px;">
      <p class="admin-form-help cp-form-help-flush">
        @if ($filterUnlinkedOnly ?? false)
          No unlinked flooring quotations available. Click <strong>New flooring quotation</strong> to build one for this project.
        @else
          No flooring quotations yet. Open the calculator to build an estimate from your flooring price database.
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
              <th>Area</th>
              <th>Draft</th>
              <th>Final quote</th>
              <th>Linked project</th>
              <th>Updated</th>
              <th style="width: 260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($quotations as $q)
              <tr>
                <td><strong>{{ $q->quote_number }}</strong></td>
                <td>@include('admin.partials.client_directory_link', ['project' => $q->project, 'label' => $q->client_name])</td>
                <td>{{ $q->squareFootage() > 0 ? number_format($q->squareFootage(), 0).' sq ft' : '—' }}</td>
                <td>{{ $q->is_draft ? 'Yes' : '—' }}</td>
                <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($q->final_quote_total) }}</td>
                <td>
                  @if ($q->project)
                    <a href="{{ route('admin.client-projects.show', $q->project) }}#quotation">{{ $q->project->name }}</a>
                  @else
                    <span class="admin-form-help">—</span>
                  @endif
                </td>
                <td><span class="admin-form-help">{{ $q->updated_at ? \App\Support\EasternStandardTimeDisplay::formatInstantCalendarDate($q->updated_at) : '' }}</span></td>
                <td>
                  <div class="fq-list-actions">
                    <a href="{{ route('admin.quotations.flooring.edit', $q) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <form method="post" action="{{ route('admin.quotations.flooring.destroy', $q) }}" class="cp-inline-form"
                          onsubmit="return confirm('Delete {{ $q->quote_number }}?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"><i class="fa fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      {{ $quotations->links() }}
    </div>
  @endif
@endsection
