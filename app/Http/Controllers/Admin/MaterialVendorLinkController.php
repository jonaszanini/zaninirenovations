<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaterialScraping\AmazonCaUrls;
use Illuminate\Http\RedirectResponse;

/**
 * Same-origin vendor product links for admin UI (avoids ad-blocker hiding of amazon.ca URLs).
 */
class MaterialVendorLinkController extends Controller
{
    public function amazon(string $asin): RedirectResponse
    {
        $normalized = AmazonCaUrls::normalizeAsin($asin);
        abort_if($normalized === null, 404);

        return redirect()->away(AmazonCaUrls::productUrl($normalized));
    }
}
