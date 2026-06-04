<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $clients = Client::query()
            ->withCount('projects')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('address', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $totalClients = Client::query()->count();

        return view('admin.clients.index', compact('clients', 'q', 'totalClients'));
    }

    public function create(): View
    {
        return view('admin.clients.create');
    }

    public function store(Request $request)
    {
        $this->normalisePhone($request);
        $this->normaliseAddressGeo($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'email_secondary' => ['nullable', 'email', 'max:255', 'different:email'],
            'phone' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_place_id' => ['nullable', 'string', 'max:255'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'email_secondary.different' => 'The secondary email must differ from the primary email.',
        ]);

        $client = Client::create($data);

        return redirect()
            ->route('admin.clients.edit', $client)
            ->with('success', 'Client created.');
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $this->normalisePhone($request);
        $this->normaliseAddressGeo($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'email_secondary' => ['nullable', 'email', 'max:255', 'different:email'],
            'phone' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_place_id' => ['nullable', 'string', 'max:255'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'email_secondary.different' => 'The secondary email must differ from the primary email.',
        ]);

        $client->update($data);

        return redirect()
            ->route('admin.clients.edit', $client)
            ->with('success', 'Client saved.');
    }

    public function destroy(Client $client)
    {
        if ($client->projects()->exists()) {
            return back()->with('error', 'This client is linked to one or more projects. Remove or reassign the link on each project before deleting.');
        }

        $client->delete();

        return redirect()
            ->route('admin.clients.index')
            ->with('success', 'Client deleted.');
    }

    /**
     * JSON search for Tom Select on the project create/edit forms.
     */
    public function search(Request $request): JsonResponse
    {
        $id = $request->integer('id');
        if ($id > 0) {
            $client = Client::query()->find($id);

            return response()->json($client ? [$this->pickerRow($client)] : []);
        }

        $q = trim((string) $request->get('q', ''));

        $clients = Client::query()
            ->when(strlen($q) >= 1, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('address', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($clients->map(fn (Client $c) => $this->pickerRow($c)));
    }

    /** @return array<string, mixed> */
    private function pickerRow(Client $c): array
    {
        $row = $c->toPickerArray();
        $row['display'] = $c->searchLabel();
        $row['phone_display'] = $c->phone ? \App\Models\ClientProject::formatPhone($c->phone) : '';

        return $row;
    }

    private function normalisePhone(Request $request): void
    {
        $raw = $request->input('phone');
        if ($raw === null || $raw === '') {
            return;
        }
        $digits = preg_replace('/\D/', '', (string) $raw);
        $request->merge(['phone' => strlen($digits) === 10 ? $digits : null]);
    }

    private function normaliseAddressGeo(Request $request): void
    {
        foreach (['address_place_id', 'address_latitude', 'address_longitude'] as $key) {
            if (($request->input($key) ?? '') === '') {
                $request->merge([$key => null]);
            }
        }

        if (! filled(trim((string) $request->input('address', '')))) {
            $request->merge([
                'address' => null,
                'address_place_id' => null,
                'address_latitude' => null,
                'address_longitude' => null,
            ]);
        }
    }
}
