<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientTagController extends Controller
{
    public function index()
    {
        $tags = ClientTag::query()
            ->withCount('clients')
            ->orderBy('name')
            ->get();

        return view('admin.client_tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.client_tags.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('client_tags', 'name')],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        ClientTag::create($data);

        return redirect()
            ->route('admin.client-tags.index')
            ->with('success', 'Tag created.');
    }

    public function edit(ClientTag $client_tag)
    {
        return view('admin.client_tags.edit', ['tag' => $client_tag]);
    }

    public function update(Request $request, ClientTag $client_tag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('client_tags', 'name')->ignore($client_tag->id)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $client_tag->update($data);

        return redirect()
            ->route('admin.client-tags.index')
            ->with('success', 'Tag updated.');
    }

    public function destroy(ClientTag $client_tag)
    {
        $client_tag->delete();

        return redirect()
            ->route('admin.client-tags.index')
            ->with('success', 'Tag deleted.');
    }
}
