<?php

namespace App\Http\Controllers;

use App\Support\PublicServices;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServicePageController extends Controller
{
    public function index(): View
    {
        $hub = PublicServices::hub();
        $pages = PublicServices::publishedPages();
        $cta = PublicServices::cta();

        return view('site.services.index', compact('hub', 'pages', 'cta'));
    }

    public function show(string $slug): View|Response
    {
        $page = PublicServices::publishedPage($slug);
        if ($page === null) {
            abort(404);
        }

        $projects = PublicServices::projectsForPage($page);
        $hero = PublicServices::heroImage($page, $projects);
        $cta = PublicServices::cta();
        $cta['heading'] = (string) ($page['cta_heading'] ?? $cta['heading'] ?? 'Get a free estimate');
        $cta['text'] = (string) ($page['cta_text'] ?? $cta['text'] ?? '');

        return view('site.services.show', compact('page', 'projects', 'hero', 'cta'));
    }
}
