<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Illuminate\Http\Request;

/**
 * One search box (admin topbar) across the three things staff look up
 * most: the client directory, projects, and quotations/estimates —
 * instead of navigating to each section and using its own local filter.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT = 15;

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
}
