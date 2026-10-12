@extends('admin.layouts.app')

@section('heading', 'Custom millwork quotations')

@section('content')
  @php $qpid = request('project'); @endphp
  <nav class="qh-crumb" aria-label="Breadcrumb">
    <ol class="qh-crumb__list">
      <li class="qh-crumb__item">
        <a class="qh-crumb__link qh-crumb__quotations" href="{{ route('admin.quotations.index', $qpid ? ['project' => $qpid] : []) }}">Quotations</a>
      </li>
      <li class="qh-crumb__item" aria-hidden="true"><span class="qh-crumb__sep">›</span></li>
      <li class="qh-crumb__item">
        <span class="qh-crumb__current" aria-current="page">Millwork</span>
      </li>
    </ol>
  </nav>
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.index', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All quotation types
      </a>
      <a href="{{ route('admin.quotations.millwork.price-configuration', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Price configuration
      </a>
    </div>
    <a href="{{ route('admin.quotations.millwork.create', $qpid ? ['project' => $qpid] : []) }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New millwork quotation
    </a>
  </div>

  @include('admin.quotations._index_project_context')

  @if ($quotations->isEmpty())
    <div class="admin-card" style="text-align:center;padding:36px 24px;">
      <p class="admin-form-help cp-form-help-flush">
        @if ($filterUnlinkedOnly ?? false)
          No unlinked millwork quotations available. Click <strong>New millwork quotation</strong> to build one for this project.
        @else
          No custom millwork quotations yet.
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
              <th>Sheets</th>
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
                <td>{{ $q->sheetCount() > 0 ? $q->sheetCount() : '—' }}</td>
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
                    <a href="{{ route('admin.quotations.millwork.edit', $q) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <form method="post" action="{{ route('admin.quotations.millwork.destroy', $q) }}" class="cp-inline-form"
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
