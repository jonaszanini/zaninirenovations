<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuoteAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationAcceptController extends Controller
{
    public function store(Request $request, QuoteAcceptanceService $acceptance): JsonResponse
    {
        $data = $request->validate([
            'project_number' => ['required', 'string', 'max:40'],
            'quote_number' => ['required', 'string', 'max:60'],
            'source_note' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $acceptance->acceptByNumbers(
            (string) $data['project_number'],
            (string) $data['quote_number'],
            \App\Models\ClientProjectQuotation::ACCEPTED_VIA_API,
            $request->ip(),
            isset($data['source_note']) ? (string) $data['source_note'] : null,
        );

        $status = $result['status'];
        $http = match ($status) {
            QuoteAcceptanceService::STATUS_ACCEPTED,
            QuoteAcceptanceService::STATUS_ALREADY_ACCEPTED => 200,
            QuoteAcceptanceService::STATUS_NOT_FOUND => 404,
            default => 409,
        };

        return response()->json(['status' => $status], $http);
    }
}
