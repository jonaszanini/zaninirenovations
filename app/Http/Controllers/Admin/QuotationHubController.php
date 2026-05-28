<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use Illuminate\Http\Request;

/**
 * Hub landing page for standalone quotations: Fence (structural calculator)
 * and Standard (materials + labour grid), with room for more card types later.
 */
class QuotationHubController extends Controller
{
    public function index(Request $request)
    {
        $hubProject = null;
        $hubProjectError = null;

        if ($request->filled('project')) {
            $id = (int) $request->query('project');
            $project = ClientProject::query()->find($id);
            if (! $project) {
                $hubProjectError = 'That project was not found.';
            } elseif (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
                $hubProjectError = 'This project no longer accepts new quotations.';
            } else {
                $hubProject = $project;
            }
        }

        return view('admin.quotations.index', [
            'hubProject' => $hubProject,
            'hubProjectError' => $hubProjectError,
        ]);
    }
}
