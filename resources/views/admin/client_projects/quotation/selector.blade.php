@extends('admin.layouts.app')

@section('heading', 'Edit quotation — '.$project->name)

@section('content')
  <div class="admin-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.client-projects.show', $project) }}#quotation"
         class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to project
      </a>
    </div>
  </div>

  <div class="admin-card">
    <h3 style="margin:0 0 6px;">Multiple specialised quotations are linked</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">
      This project has more than one specialised quotation linked to it.
      Pick the one you'd like to edit. Saving any of them will refresh
      <strong>only that quotation's</strong> line items on the project's
      Quotation tab — the other linked quotations stay untouched.
    </p>

    <table class="admin-table">
      <thead>
        <tr>
          <th>Quote #</th>
          <th>Type</th>
          <th>Client name</th>
          <th>Grand total</th>
          <th>Last updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($standardQuotations as $sq)
          <tr>
            <td><strong>{{ $sq->quote_number }}</strong></td>
            <td>Standard</td>
            <td>{{ $sq->client_name }}</td>
            <td>{{ \App\Models\ClientProject::formatMoney((float) $sq->grand_total) }}</td>
            <td>{{ optional($sq->updated_at)->format('M j, Y g:ia') ?: '—' }}</td>
            <td style="text-align:right;">
              <a href="{{ route('admin.quotations.standard.edit', [$sq, 'return' => 'project']) }}"
                 class="admin-btn admin-btn--primary admin-btn--sm">
                <i class="fa fa-pencil"></i> Edit
              </a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
