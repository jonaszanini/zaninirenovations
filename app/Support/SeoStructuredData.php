<?php

namespace App\Support;

class SeoStructuredData
{
    /**
     * JSON-LD output safe on PHP 8.0+ (JSON_INVALID_UTF8_IGNORE exists from PHP 8.3).
     */
    public static function toJsonLd(array $data): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        if (defined('JSON_INVALID_UTF8_IGNORE')) {
            $flags |= (int) constant('JSON_INVALID_UTF8_IGNORE');
        }

        return json_encode($data, $flags) ?: '{}';
    }

    public static function homeGraph(): array
    {
        $url = rtrim(config('app.url'), '/');
        $knowsAbout = self::knowsAboutTopics();
        $areas = config('seo.area_served', []);
        $fb = (string) config('social.facebook_url', '');
        $ig = (string) config('social.instagram_url', '');
        $googleBiz = (string) config('seo.google_business_url', '');
        $sameAs = array_values(array_filter([$fb, $ig, $googleBiz], static fn ($u) => is_string($u) && str_starts_with($u, 'http')));
        $homeSummary = trim((string) config('seo.home_summary', ''));
        if ($homeSummary === '') {
            $homeSummary = 'One-person, fully insured carpentry and renovation business in Carleton Place since 2020.';
        }
        $ogRel = ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
        $ogAbs = $url.'/'.$ogRel;

        $personJonas = [
            '@type' => 'Person',
            '@id' => $url.'/#person-jonas',
            'name' => 'Jonas Zanini',
            'jobTitle' => 'Owner & Contractor',
            'description' => 'Contractor and owner of Zanini Renovations in Carleton Place, Ontario.',
            'worksFor' => ['@id' => $url.'/#business'],
        ];
        $personMayara = [
            '@type' => 'Person',
            '@id' => $url.'/#person-mayara',
            'name' => 'Mayara Zanini',
            'jobTitle' => 'Office & Marketing',
            'worksFor' => ['@id' => $url.'/#business'],
        ];
        $primaryImage = [
            '@type' => 'ImageObject',
            '@id' => $url.'/#primary-image',
            'url' => $ogAbs,
            'contentUrl' => $ogAbs,
            'caption' => 'Zanini Renovations carpentry and renovation work in the Ottawa area.',
        ];

        $business = [
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => $url.'/#business',
            'name' => 'Zanini Renovations',
            'url' => $url.'/',
            'logo' => $url.'/images/logo.png',
            'image' => [$url.'/'.ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/')],
            'description' => $homeSummary,
            'slogan' => 'Quality craftsmanship for Ottawa-area homes',
            'knowsAbout' => $knowsAbout,
            'telephone' => '+1-647-834-7570',
            'email' => (string) config('mail.from.address', ''),
            'priceRange' => '$$',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Carleton Place',
                'addressRegion' => 'ON',
                'addressCountry' => 'CA',
            ],
            'areaServed' => $areas,
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'opens' => '09:00',
                    'closes' => '18:00',
                ],
            ],
            'founder' => ['@id' => $url.'/#person-jonas'],
            'employee' => [
                ['@id' => $url.'/#person-mayara'],
            ],
        ];

        if ($sameAs !== []) {
            $business['sameAs'] = $sameAs;
        }

        $services = [
            'Residential Fencing Services',
            'Professional Deck Builder Ottawa',
            'Basement Framing Contractors',
            'Interior Trim Installation Ottawa',
            'Custom Built-in Shelving Ottawa',
            'Hardwood Flooring Installation Ottawa',
            'Drywall Installation and Taping',
            'Accent wall carpentry',
            'Wood Staircase Refinishing',
        ];

        $itemList = [
            '@type' => 'ItemList',
            '@id' => $url.'/#services-list',
            'name' => 'Carpentry & renovation services',
            'itemListElement' => [],
        ];
        $n = 1;
        foreach ($services as $name) {
            $itemList['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $n,
                'item' => [
                    '@type' => 'Service',
                    'name' => $name,
                    'provider' => ['@id' => $url.'/#business'],
                    'areaServed' => ['Ottawa', 'Carleton Place'],
                ],
            ];
            $n++;
        }

        $graph = [
            [
                '@type' => 'WebSite',
                '@id' => $url.'/#website',
                'url' => $url.'/',
                'name' => 'Zanini Renovations',
                'description' => $homeSummary,
                'publisher' => ['@id' => $url.'/#business'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $url.'/#webpage',
                'url' => $url.'/',
                'name' => (string) config('seo.home_title', 'Zanini Renovations'),
                'description' => $homeSummary,
                'isPartOf' => ['@id' => $url.'/#website'],
                'about' => ['@id' => $url.'/#business'],
                'primaryImageOfPage' => ['@id' => $url.'/#primary-image'],
            ],
            $personJonas,
            $personMayara,
            $primaryImage,
            $business,
            $itemList,
        ];

        $faq = self::homeFaqPage($url);
        if ($faq !== null) {
            $graph[] = $faq;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function homeFaqPage(string $baseUrl): ?array
    {
        $faqItems = config('seo.home_faq', []);
        if (! is_array($faqItems) || $faqItems === []) {
            return null;
        }

        $entities = [];
        foreach ($faqItems as $row) {
            if (! is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['question'] ?? ''));
            $a = trim((string) ($row['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => rtrim($baseUrl, '/').'/#faq',
            'mainEntity' => $entities,
            'isPartOf' => ['@id' => rtrim($baseUrl, '/').'/#webpage'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function knowsAboutTopics(): array
    {
        $topics = config('seo.knows_about', []);
        if (! is_array($topics)) {
            return [];
        }

        $clean = [];
        foreach ($topics as $topic) {
            if (! is_string($topic)) {
                continue;
            }
            $label = trim($topic);
            if ($label !== '') {
                $clean[] = $label;
            }
        }

        return $clean;
    }

    /**
     * JSON-LD graph for a public blog article.
     *
     * @return array<string, mixed>
     */
    public static function blogPosting(\App\Models\BlogPost $post): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $post->publicUrl();
        $description = trim((string) ($post->meta_description ?: $post->displayExcerpt(300)));
        $published = $post->published_at ?? $post->created_at;
        $modified = $post->updated_at ?? $published;
        $authorName = trim((string) ($post->author_name ?: 'Jonas Zanini'));
        $author = [
            '@type' => 'Person',
            'name' => $authorName,
        ];
        if (strcasecmp($authorName, 'Jonas Zanini') === 0) {
            $author['@id'] = $base.'/#person-jonas';
            $author['jobTitle'] = 'Owner & Contractor';
        }

        $image = $post->featuredImageUrl();
        if (! is_string($image) || $image === '') {
            $image = $base.'/'.ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
        }

        $article = [
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => (string) $post->title,
            'description' => $description,
            'url' => $url,
            'mainEntityOfPage' => $url,
            'inLanguage' => 'en-CA',
            'image' => $image,
            'author' => $author,
            'publisher' => [
                '@type' => 'HomeAndConstructionBusiness',
                '@id' => $base.'/#business',
                'name' => 'Zanini Renovations',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $base.'/images/logo.png',
                ],
            ],
            'about' => ['@id' => $base.'/#business'],
            'wordCount' => str_word_count(strip_tags((string) $post->html_content)),
        ];

        if ($published) {
            $article['datePublished'] = $published->toAtomString();
        }
        if ($modified) {
            $article['dateModified'] = $modified->toAtomString();
        }

        $keywords = [];
        if ($post->relationLoaded('tags')) {
            foreach ($post->tags as $tag) {
                $name = trim((string) ($tag->name ?? ''));
                if ($name !== '') {
                    $keywords[] = $name;
                }
            }
        }
        if ($keywords !== []) {
            $article['keywords'] = implode(', ', $keywords);
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Blog', 'url' => $base.'/blog'],
                    ['name' => (string) $post->title, 'url' => $url],
                ]),
                $article,
            ],
        ];
    }

    /**
     * JSON-LD for the public blog index.
     *
     * @return array<string, mixed>
     */
    public static function blogIndex(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Blog', 'url' => $base.'/blog'],
                ]),
                [
                    '@type' => 'Blog',
                    '@id' => $base.'/blog#blog',
                    'name' => 'Zanini Renovations Blog',
                    'url' => $base.'/blog',
                    'description' => 'Renovation tips, project insights, and carpentry news from Zanini Renovations in Carleton Place and the Ottawa region.',
                    'inLanguage' => 'en-CA',
                    'publisher' => ['@id' => $base.'/#business'],
                ],
            ],
        ];
    }

    /**
     * JSON-LD graph for the public portfolio index.
     *
     * @return array<string, mixed>
     */
    public static function portfolioIndex(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Projects', 'url' => $base.'/portfolio'],
                ]),
                [
                    '@type' => 'CollectionPage',
                    '@id' => $base.'/portfolio#page',
                    'name' => 'Carpentry and renovation projects',
                    'url' => $base.'/portfolio',
                    'description' => 'Photos and details of decks, fences, basements, flooring, and other completed projects by Zanini Renovations in Carleton Place and the Ottawa region.',
                    'inLanguage' => 'en-CA',
                    'about' => ['@id' => $base.'/#business'],
                ],
            ],
        ];
    }

    /**
     * JSON-LD graph for one public portfolio project.
     *
     * @return array<string, mixed>
     */
    public static function portfolioProject(\App\Models\Project $project): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $project->publicUrl();
        $description = $project->seoDescription();
        $images = [];

        if ($project->relationLoaded('photos')) {
            foreach ($project->photos as $photo) {
                $photoUrl = self::absoluteUrl($photo->url());
                $images[] = [
                    '@type' => 'ImageObject',
                    'contentUrl' => $photoUrl,
                    'url' => $photoUrl,
                    'caption' => (string) $project->title,
                ];
            }
        }

        $gallery = [
            '@type' => 'ImageGallery',
            '@id' => $url.'#gallery',
            'name' => (string) $project->title,
            'description' => $description,
            'url' => $url,
            'inLanguage' => 'en-CA',
            'mainEntityOfPage' => $url,
            'creator' => [
                '@type' => 'HomeAndConstructionBusiness',
                '@id' => $base.'/#business',
                'name' => 'Zanini Renovations',
            ],
        ];

        if ($project->completed_on) {
            $gallery['datePublished'] = $project->completed_on->toDateString();
        }
        if ($project->updated_at) {
            $gallery['dateModified'] = $project->updated_at->toAtomString();
        }
        if ($images !== []) {
            $gallery['image'] = $images;
        }

        $keywords = [];
        if ($project->relationLoaded('categories')) {
            foreach ($project->categories as $category) {
                $name = trim((string) $category->name);
                if ($name !== '') {
                    $keywords[] = $name;
                }
            }
        }
        if ($keywords !== []) {
            $gallery['keywords'] = implode(', ', $keywords);
        }

        $page = [
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $project->seoTitle(),
            'description' => $description,
            'inLanguage' => 'en-CA',
            'about' => ['@id' => $base.'/#business'],
            'mainEntity' => ['@id' => $url.'#gallery'],
        ];
        if ($images !== []) {
            $page['primaryImageOfPage'] = $images[0];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Projects', 'url' => $base.'/portfolio'],
                    ['name' => (string) $project->title, 'url' => $url],
                ]),
                $page,
                $gallery,
            ],
        ];
    }

    public static function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }

    public static function breadcrumb(array $items): array
    {
        $elements = [];
        $pos = 1;
        foreach ($items as $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $pos,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
            $pos++;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
