<?php

namespace App\Http\Controllers;

use App\Services\ContactFormMailer;
use Illuminate\Http\Request;

class ContactFormController extends Controller
{
    public function submit(Request $request)
    {
        $result = ContactFormMailer::handle($request->all());

        return response()->json($result, $result['status'] === 1 ? 200 : 422);
    }
}
