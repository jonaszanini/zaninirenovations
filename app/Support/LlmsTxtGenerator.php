<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * /llms.txt — Markdown index of public pages for language-model crawlers.
 *
 * @see https://llmstxt.org/
 */
class LlmsTxtGenerator
{
    public static function generate(?Collection $posts = null, ?Collection $projects = null): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $summary = trim((string) config('seo.home_summary', ''));
        if ($summary === '') {
            $summary = 'One-person, fully insured carpentry and renovation business in Carleton Place since 2020.';
        }

        $insurance = trim((string) config('seo.liability_insurance', '$3 million'));
        $phone = '(647) 834-7570';

        $lines = [
            '# Zanini Renovations',
            '',
            '> '.$summary,
            '',
            'Zanini Renovations is owned by Jonas Zanini.',
            'Office and marketing: Mayara Zanini. Based in Carleton Place, Ontario.',
            'Phone: '.$phone.'. Quotes are in Canadian dollars.',
            'Insurance: '.$insurance.' general liability; WSIB clearance; Ontario Building Code O.Reg. 332/12 (Part 9 housing).',
            '',
            '## Pages',
            '',
            '- [Home]('.$base.'/): Services, service area, team, reviews, and FAQ.',
            '- [Warranty]('.$base.'/warranty): Workmanship warranty and insurance details.',
            '- [Blog]('.$base.'/blog): Renovation and carpentry articles for Ottawa-area homeowners.',
            '- [Projects]('.$base.'/portfolio): Photos and details of completed carpentry and renovation work.',
            '- [Privacy policy]('.$base.'/privacy-policy)',
            '- [Terms and conditions]('.$base.'/terms-and-conditions)',
            '',
            '## Services',
            '',
            '- Fencing, decks, basement framing and finishing, interior trim, custom built-ins,',
            '  flooring, drywall, accent walls, and wood staircases.',
            '',
            '## Service area',
            '',
            '- '.trim((string) config('seo.service_area_text', '')),
            '',
        ];

        $posts = $posts ?? BlogPost::query()
            ->indexable()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['title', 'slug', 'excerpt', 'html_content']);

        if ($posts->isNotEmpty()) {
            $lines[] = '## Blog';
            $lines[] = '';
            foreach ($posts as $post) {
                $title = trim((string) $post->title);
                if ($title === '') {
                    continue;
                }
                $excerpt = trim((string) $post->displayExcerpt(160));
                $link = '- ['.$title.']('.$base.'/blog/'.$post->slug.')';
                if ($excerpt !== '') {
                    $link .= ': '.$excerpt;
                }
                $lines[] = $link;
            }
            $lines[] = '';
        }

        $projects = $projects ?? Project::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('completed_on')
            ->orderByDesc('id')
            ->limit(25)
            ->get(['title', 'slug', 'description', 'meta_description']);

        if ($projects->isNotEmpty()) {
            $lines[] = '## Portfolio';
            $lines[] = '';
            foreach ($projects as $project) {
                $title = trim((string) $project->title);
                $slug = trim((string) $project->slug);
                if ($title === '' || $slug === '') {
                    continue;
                }
                $excerpt = trim((string) $project->plainDescription(160));
                $link = '- ['.$title.']('.$base.'/portfolio/'.$slug.')';
                if ($excerpt !== '') {
                    $link .= ': '.$excerpt;
                }
                $lines[] = $link;
            }
            $lines[] = '';
        }

        $lines[] = '## Contact';
        $lines[] = '';
        $lines[] = '- Phone: '.$phone;
        $lines[] = '- Website: '.$base.'/';
        $googleBiz = SeoStructuredData::googleBusinessUrl();
        $lines[] = '- Google Business: '.$googleBiz;
        $lines[] = '';

        return implode("\n", $lines);
    }
}
