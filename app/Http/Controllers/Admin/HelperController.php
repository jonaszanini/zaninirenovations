<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use Illuminate\Http\Request;

class HelperController extends Controller
{
    public function index(Request $request)
    {
        $helpers = Helper::query()
            ->withCount(['timeEntries', 'projects'])
            ->orderBy('name')
            ->get();

        $returnProjectId = $request->integer('return_project') ?: null;

        return view('admin.helpers.index', compact('helpers', 'returnProjectId'));
    }

    public function create(Request $request)
    {
        $returnProjectId = $request->integer('return_project') ?: null;

        return view('admin.helpers.create', compact('returnProjectId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateHelper($request);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        Helper::create($data);
        $this->rememberSubmitSucceeded($request);

        return $this->redirectAfterSave($request, 'Helper added.');
    }

    public function edit(Request $request, Helper $helper)
    {
        $returnProjectId = $request->integer('return_project') ?: null;

        return view('admin.helpers.edit', compact('helper', 'returnProjectId'));
    }

    public function update(Request $request, Helper $helper)
    {
        $data = $this->validateHelper($request);

        $helper->update($data);

        return $this->redirectAfterSave($request, 'Helper updated.');
    }

    public function destroy(Request $request, Helper $helper)
    {
        if ($helper->timeEntries()->exists()) {
            return back()->with(
                'error',
                'This helper has time entries on record. Deactivate them instead of deleting.'
            );
        }

        $helper->projects()->detach();
        $helper->delete();

        return $this->redirectAfterSave($request, 'Helper removed.');
    }

    /** @return array<string, mixed> */
    private function validateHelper(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function redirectAfterSave(Request $request, string $message)
    {
        $returnProjectId = $request->integer('return_project') ?: null;
        if ($returnProjectId > 0) {
            return redirect()
                ->route('admin.client-projects.show', $returnProjectId)
                ->withFragment('time')
                ->with('success', $message);
        }

        return redirect()
            ->route('admin.helpers.index')
            ->with('success', $message);
    }
}
