<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EtransferNotice;
use App\Services\Etransfer\EtransferNoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtransferNoticeController extends Controller
{
    public function store(Request $request, EtransferNoticeService $notices): JsonResponse
    {
        $data = $request->validate([
            'raw_text' => ['nullable', 'string', 'max:20000'],
            'sender_name' => ['nullable', 'string', 'max:180'],
            'sender_email' => ['nullable', 'email', 'max:180'],
            'amount' => ['nullable'],
            'currency' => ['nullable', 'string', 'max:3'],
            'reference' => ['nullable', 'string', 'max:80'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:500'],
            'received_at' => ['nullable', 'string', 'max:80'],
        ]);

        $hasRaw = filled(trim((string) ($data['raw_text'] ?? '')));
        $hasStructured = filled($data['amount'] ?? null) && filled($data['sender_name'] ?? null);
        if (! $hasRaw && ! $hasStructured) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Provide raw_text or both sender_name and amount.',
            ], 422);
        }

        $result = $notices->ingest($data, EtransferNotice::SOURCE_API);
        $http = $result['status'] === 'created' ? 201 : 200;

        return response()->json([
            'status' => $result['status'],
            'notice' => $notices->serializeNotice($result['notice']),
            'candidates' => $result['candidates'],
        ], $http);
    }
}
