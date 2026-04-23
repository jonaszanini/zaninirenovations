<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Hub landing page for standalone quotations. Today it only surfaces
 * the Fence calculator, but the page is designed as a card grid so
 * future quotation types (deck, pergola, etc.) slot in without layout
 * churn.
 */
class QuotationHubController extends Controller
{
    public function index()
    {
        return view('admin.quotations.index');
    }
}
