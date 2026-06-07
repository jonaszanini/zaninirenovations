@extends('admin.layouts.app')

@section('heading', 'AI instructions')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">System prompts sent to Gemini for each AI feature. Edit the instruction text to change how the model drafts contracts and other content.</p>
  </div>

  @if ($instructions->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No AI instructions configured yet.</p>
      </div>
    </div>
  @else
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Key</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($instructions as $row)
            <tr>
              <td><strong>{{ $row->name }}</strong></td>
              <td><code class="admin-code">{{ $row->key }}</code></td>
              <td>{{ $row->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.ai-instructions.edit', $row) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
