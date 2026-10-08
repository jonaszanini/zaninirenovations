<?php

namespace App\Http\Controllers;

use App\Support\PublicServices;
use App\Support\ServiceAreas;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function about(): View
    {
        $page = PublicServices::about();
        $services = PublicServices::publishedPages();
        $cta = PublicServices::cta();

        return view('site.about', compact('page', 'services', 'cta'));
    }

    public function reviews(): View
    {
        $page = PublicServices::reviews();
        $cta = PublicServices::cta();

        return view('site.reviews', compact('page', 'cta'));
    }

    public function contact(): View
    {
        $page = PublicServices::contact();
        $cta = PublicServices::cta();
        $mapEmbedSrc = ServiceAreas::mapEmbedSrc();
        $services = PublicServices::publishedPages();

        return view('site.contact', compact('page', 'cta', 'mapEmbedSrc', 'services'));
    }
}
