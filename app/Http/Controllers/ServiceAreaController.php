<?php

namespace App\Http\Controllers;

use App\Support\ServiceAreas;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServiceAreaController extends Controller
{
    public function index(): View
    {
        $hub = ServiceAreas::hub();
        $groups = ServiceAreas::hubGroups();
        $published = ServiceAreas::publishedPages();
        $cta = ServiceAreas::cta();
        $mapEmbedSrc = ServiceAreas::mapEmbedSrc();

        return view('site.areas.index', compact(
            'hub',
            'groups',
            'published',
            'cta',
            'mapEmbedSrc',
        ));
    }

    public function show(string $slug): View|Response
    {
        $page = ServiceAreas::publishedPage($slug);
        if ($page === null) {
            abort(404);
        }

        $projects = ServiceAreas::projectsForPage($page);
        $nearby = ServiceAreas::nearbyPublished($page);
        $cta = ServiceAreas::cta();
        $mapEmbedSrc = ServiceAreas::mapEmbedSrc();

        return view('site.areas.show', compact(
            'page',
            'projects',
            'nearby',
            'cta',
            'mapEmbedSrc',
        ));
    }
}
