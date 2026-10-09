<?php

namespace App\Http\Controllers\Concerns;

use App\Support\AdminOnceSubmitToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RejectsReplaySubmit
{
    protected function rejectReplaySubmit(Request $request): RedirectResponse|JsonResponse|null
    {
        if (AdminOnceSubmitToken::claim($request)) {
            return null;
        }

        return AdminOnceSubmitToken::replayResponse($request);
    }
}
