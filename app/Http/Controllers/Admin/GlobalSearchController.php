<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One search box (admin topbar) across the three things staff look up
 * most: the client directory, projects, and quotations/estimates —
 * instead of navigating to each section and using its own local filter.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT = 15;
    private const API_LIMIT = 5;

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $clients = collect();
        $projects = collect();
        $quotations = collect();

        if (mb_strlen($q) >= 2) {
            $clients = Client::query()
                ->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('address', 'like', '%'.$q.'%');
                })
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get();

            $projects = ClientProject::query()
                ->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhere('client_name', 'like', '%'.$q.'%')
                        ->orWhere('client_email', 'like', '%'.$q.'%')
                        ->orWhere('client_phone', 'like', '%'.$q.'%')
                        ->orWhere('client_address', 'like', '%'.$q.'%');
                })
                ->orderByDesc('updated_at')
                ->limit(self::LIMIT)
                ->get();

            $quotations = ClientProjectQuotation::query()
                ->with('project')
                ->where(function ($sub) use ($q) {
                    $sub->where('quotation_number', 'like', '%'.$q.'%')
                        ->orWhere('title', 'like', '%'.$q.'%');
                })
                ->orWhereHas('project', function ($sub) use ($q) {
                    $sub->where('client_name', 'like', '%'.$q.'%');
                })
                ->orderByDesc('updated_at')
                ->limit(self::LIMIT)
                ->get();
        }

        $resultCount = $clients->count() + $projects->count() + $quotations->count();

        return view('admin.search.index', compact('q', 'clients', 'projects', 'quotations', 'resultCount'));
    }

    /**
     * JSON API endpoint for the Ctrl+K search palette.
     */
    public function api(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        $projects = $this->searchProjects($q);
        if (count($projects) > 0) {
            $results[] = [
                'type' => 'projects',
                'label' => 'Projects',
                'items' => $projects,
            ];
        }

        $invoices = $this->searchInvoices($q);
        if (count($invoices) > 0) {
            $results[] = [
                'type' => 'invoices',
                'label' => 'Invoices',
                'items' => $invoices,
            ];
        }

        $quotations = $this->searchQuotations($q);
        if (count($quotations) > 0) {
            $results[] = [
                'type' => 'quotations',
                'label' => 'Quotations',
                'items' => $quotations,
            ];
        }

        $clients = $this->searchClients($q);
        if (count($clients) > 0) {
            $results[] = [
                'type' => 'clients',
                'label' => 'Clients',
                'items' => $clients,
            ];
        }

        return response()->json(['results' => $results]);
    }

    private function searchProjects(string $q): array
    {
        return ClientProject::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('client_name', 'like', '%' . $q . '%')
                    ->orWhere('client_email', 'like', '%' . $q . '%')
                    ->orWhere('client_phone', 'like', '%' . $q . '%')
                    ->orWhere('client_address', 'like', '%' . $q . '%');
            })
            ->orderByDesc('updated_at')
            ->limit(self::API_LIMIT)
            ->get()
            ->map(fn (ClientProject $p) => [
                'id' => $p->id,
                'title' => $p->name,
                'subtitle' => $p->client_name . ($p->client_address ? ' — ' . $p->client_address : ''),
                'status' => $p->statusLabel(),
                'status_color' => $p->statusColor(),
                'url' => route('admin.client-projects.show', $p),
                'icon' => 'folder',
            ])
            ->values()
            ->all();
    }

    private function searchQuotations(string $q): array
    {
        return ClientProjectQuotation::query()
            ->with('project')
            ->where(function ($sub) use ($q) {
                $sub->where('quotation_number', 'like', '%' . $q . '%')
                    ->orWhere('title', 'like', '%' . $q . '%');
            })
            ->orWhereHas('project', function ($sub) use ($q) {
                $sub->where('client_name', 'like', '%' . $q . '%');
            })
            ->orderByDesc('updated_at')
            ->limit(self::API_LIMIT)
            ->get()
            ->map(fn (ClientProjectQuotation $qt) => [
                'id' => $qt->id,
                'title' => $qt->quotation_number ?: $qt->resolvedTitle(),
                'subtitle' => $qt->project?->name . ' — ' . ($qt->project?->client_name ?? ''),
                'status' => $qt->statusLabel(),
                'url' => $qt->project
                    ? route('admin.client-projects.show', $qt->project) . '#quotation'
                    : '#',
                'icon' => 'file-text-o',
            ])
            ->values()
            ->all();
    }

    private function searchInvoices(string $q): array
    {
        return ClientProjectInvoice::query()
            ->with('project')
            ->where(function ($sub) use ($q) {
                $sub->where('invoice_number', 'like', '%' . $q . '%')
                    ->orWhere('installment_description', 'like', '%' . $q . '%');
            })
            ->orWhereHas('project', function ($sub) use ($q) {
                $sub->where('client_name', 'like', '%' . $q . '%')
                    ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->orderByDesc('updated_at')
            ->limit(self::API_LIMIT)
            ->get()
            ->map(fn (ClientProjectInvoice $inv) => [
                'id' => $inv->id,
                'title' => $inv->invoice_number ?: 'Invoice #' . $inv->id,
                'subtitle' => $inv->project?->name . ' — ' . ($inv->project?->client_name ?? ''),
                'status' => ClientProjectInvoice::statusLabel($inv->status),
                'status_color' => ClientProjectInvoice::statusColor($inv->status),
                'url' => $inv->project
                    ? route('admin.client-projects.show', $inv->project) . '#invoice'
                    : '#',
                'icon' => 'file-text-o',
            ])
            ->values()
            ->all();
    }

    private function searchClients(string $q): array
    {
        return Client::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%');
            })
            ->orderBy('name')
            ->limit(self::API_LIMIT)
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'title' => $c->name ?: 'Unknown',
                'subtitle' => trim($c->email . ' ' . ClientProject::formatPhone($c->phone)),
                'url' => route('admin.client-directory.show', $c),
                'icon' => 'user',
            ])
            ->values()
            ->all();
    }
}
