<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'open');
        $filter = in_array($filter, ['open', 'completed', 'all'], true) ? $filter : 'open';

        $tasks = Task::query()
            ->with('project')
            ->when($filter === 'open', fn ($q) => $q->open())
            ->when($filter === 'completed', fn ($q) => $q->completed())
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();

        $openCount = Task::query()->open()->count();
        $overdueCount = Task::query()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count();

        $projects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderBy('client_name')
            ->limit(200)
            ->get(['id', 'name', 'client_name']);

        return view('admin.tasks.index', compact('tasks', 'filter', 'openCount', 'overdueCount', 'projects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'client_project_id' => ['nullable', 'exists:client_projects,id'],
        ]);

        $data['created_by'] = auth()->id();

        Task::create($data);

        return back()->with('success', 'Task added.');
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
            'client_project_id' => ['nullable', 'exists:client_projects,id'],
        ]);

        $task->update($data);

        return back()->with('success', 'Task updated.');
    }

    /** Toggle done/open — the everyday action on the inbox list. */
    public function toggle(Task $task)
    {
        $task->update([
            'completed_at' => $task->isDone() ? null : now(),
        ]);

        return back()->with('success', $task->isDone() ? 'Task marked done.' : 'Task reopened.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
