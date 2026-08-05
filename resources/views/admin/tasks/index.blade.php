@extends('admin.layouts.app')

@section('heading', 'Tasks & reminders')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      {{ $openCount }} open{{ $overdueCount > 0 ? ', ' . $overdueCount . ' overdue' : '' }}.
      Your own follow-up list — not visible to clients.
    </p>
    <div class="admin-toolbar-actions" role="tablist" aria-label="Filter tasks">
      <a href="{{ route('admin.tasks.index', ['filter' => 'open']) }}" class="admin-btn admin-btn--sm {{ $filter === 'open' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">Open</a>
      <a href="{{ route('admin.tasks.index', ['filter' => 'completed']) }}" class="admin-btn admin-btn--sm {{ $filter === 'completed' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">Completed</a>
      <a href="{{ route('admin.tasks.index', ['filter' => 'all']) }}" class="admin-btn admin-btn--sm {{ $filter === 'all' ? 'admin-btn--primary' : 'admin-btn--ghost' }}">All</a>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:16px;">
    <form action="{{ route('admin.tasks.store') }}" method="post" class="tk-quick-add">
      @csrf
      <div class="admin-form-group tk-quick-add__title">
        <label for="tk-title" class="sr-only">Task</label>
        <input class="admin-input" type="text" id="tk-title" name="title" placeholder="New task or reminder…" required maxlength="255" value="{{ old('title') }}">
      </div>
      <div class="admin-form-group tk-quick-add__due">
        <label for="tk-due" class="sr-only">Due</label>
        <input class="admin-input" type="date" id="tk-due" name="due_at" value="{{ old('due_at') }}">
      </div>
      <div class="admin-form-group tk-quick-add__project">
        <label for="tk-project" class="sr-only">Related project</label>
        <select class="admin-input" id="tk-project" name="client_project_id">
          <option value="">No project</option>
          @foreach ($projects as $project)
            <option value="{{ $project->id }}" @selected(old('client_project_id') == $project->id)>{{ $project->client_name ?: $project->name }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-plus"></i> Add
      </button>
    </form>
    @error('title') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>

  @if ($tasks->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>{{ $filter === 'completed' ? 'No completed tasks yet.' : 'Nothing here — add a task above.' }}</p>
      </div>
    </div>
  @else
    <div class="tk-list">
      @foreach ($tasks as $task)
        <div class="admin-card tk-item {{ $task->isDone() ? 'tk-item--done' : '' }} {{ $task->isOverdue() ? 'tk-item--overdue' : '' }}">
          <form action="{{ route('admin.tasks.toggle', $task) }}" method="post" class="tk-item__toggle-form">
            @csrf
            @method('PATCH')
            <button type="submit" class="tk-item__toggle" title="{{ $task->isDone() ? 'Mark as open' : 'Mark as done' }}" aria-label="{{ $task->isDone() ? 'Mark as open' : 'Mark as done' }}">
              <i class="fa {{ $task->isDone() ? 'fa-check-square-o' : 'fa-square-o' }}"></i>
            </button>
          </form>

          <div class="tk-item__body">
            <p class="tk-item__title">{{ $task->title }}</p>
            <div class="tk-item__meta">
              @if ($task->due_at)
                <span class="tk-item__due"><i class="fa fa-calendar"></i> {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' (overdue)' : '' }}</span>
              @endif
              @if ($task->project)
                <a href="{{ route('admin.client-projects.show', $task->project) }}" class="tk-item__project"><i class="fa fa-briefcase"></i> {{ $task->project->client_name ?: $task->project->name }}</a>
              @endif
            </div>
            @if ($task->notes)
              <p class="tk-item__notes">{{ $task->notes }}</p>
            @endif
          </div>

          <div class="tk-item__actions">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-tk-edit-toggle>Edit</button>
            <form action="{{ route('admin.tasks.destroy', $task) }}" method="post" onsubmit="return confirm('Delete this task?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
            </form>
          </div>

          <form action="{{ route('admin.tasks.update', $task) }}" method="post" class="tk-item__edit-form" hidden>
            @csrf
            @method('PUT')
            <div class="admin-form-group">
              <label>Title</label>
              <input class="admin-input" type="text" name="title" value="{{ $task->title }}" required maxlength="255">
            </div>
            <div class="admin-form-grid-2">
              <div class="admin-form-group">
                <label>Due date</label>
                <input class="admin-input" type="date" name="due_at" value="{{ optional($task->due_at)->format('Y-m-d') }}">
              </div>
              <div class="admin-form-group">
                <label>Project</label>
                <select class="admin-input" name="client_project_id">
                  <option value="">No project</option>
                  @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected($task->client_project_id === $project->id)>{{ $project->client_name ?: $project->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="admin-form-group">
              <label>Notes</label>
              <textarea class="admin-input" name="notes" maxlength="2000" rows="2">{{ $task->notes }}</textarea>
            </div>
            <div class="admin-form-actions">
              <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save</button>
            </div>
          </form>
        </div>
      @endforeach
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-tk-edit-toggle]');
      if (!btn) return;
      var item = btn.closest('.tk-item');
      var form = item && item.querySelector('.tk-item__edit-form');
      if (!form) return;
      form.hidden = !form.hidden;
    });
  </script>
@endpush
