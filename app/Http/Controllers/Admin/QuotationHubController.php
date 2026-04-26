<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Hub landing page for standalone quotations: Fence (structural calculator)
 * and Standard (materials + labour grid), with room for more card types later.
 */
class QuotationHubController extends Controller
{
    public function index()
    {
        return view('admin.quotations.index');
    }
}
