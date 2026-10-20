<?php

namespace App\Support;

class SeoStructuredData
{
    /**
     * Canonical Google Business Profile CID URL.
     * Always use this instead of long /maps/place/ URLs that expose coordinates.
     */
    public const GOOGLE_BUSINESS_CID = 'https://maps.google.com/?cid=7676669856408637146';

    /**
     * Return the canonical GBP URL, ignoring any /maps/place/ URL from env.
     * This prevents accidental publication of exact coordinates.
     */
    public static function googleBusinessUrl(): string
    {
        $configured = trim((string) config('seo.google_business_url', ''));

        if ($configured === '' || str_contains($configured, '/maps/place/') || str_contains($configured, '/@')) {
            return self::GOOGLE_BUSINESS_CID;
        }

        return $configured;
    }

    /**
     * Public profile URLs already present in the repo (Facebook, Instagram, GBP).
     * Messaging links such as WhatsApp are excluded from sameAs.
     *
     * @return list<string>
     */
    public static function profileSameAsUrls(): array
    {
        $candidates = [
            (string) config('social.facebook_url', ''),
            (string) config('social.instagram_url', ''),
            self::googleBusinessUrl(),
        ];
        $out = [];
        foreach ($candidates as $url) {
            $url = trim($url);
            if ($url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }
            if (str_contains($url, 'wa.me') || str_contains(strtolower($url), 'whatsapp')) {
                continue;
            }
            $out[] = $url;
        }

        return array_values(array_unique($out));
    }

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

    /**
     * Canonical HomeAndConstructionBusiness structured data for the site.
     *
     * @return array<string, mixed>
     */
    public static function canonicalBusiness(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => 'https://zaninirenovations.ca/#business',
            'name' => 'Zanini Renovations',
            'url' => 'https://zaninirenovations.ca/',
            'logo' => 'https://zaninirenovations.ca/images/logo.png',
            'telephone' => '+1-647-834-7570',
            'foundingDate' => '2020',
            'founder' => [
                '@type' => 'Person',
                'name' => 'Jonas Zanini',
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Carleton Place',
                'addressRegion' => 'ON',
                'addressCountry' => 'CA',
            ],
            'hasMap' => self::GOOGLE_BUSINESS_CID,
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'opens' => '09:00',
                    'closes' => '18:00',
                ],
            ],
            'sameAs' => array_values(array_filter([
                (string) config('social.facebook_url', 'https://www.facebook.com/zaninirenovations'),
                (string) config('social.instagram_url', 'https://www.instagram.com/zaninirenovations/'),
            ])),
            'areaServed' => [
                'Carleton Place',
                'Beckwith',
                'Almonte',
                'Mississippi Mills',
                'Perth',
                'Smiths Falls',
                'Ottawa',
                'Kanata',
                'Stittsville',
                'Barrhaven',
                'Nepean',
                'Gatineau',
            ],
        ];
    }

    public static function homeGraph(): array
    {
        $url = rtrim(config('app.url'), '/');
        $knowsAbout = self::knowsAboutTopics();
        $areas = config('seo.area_served', []);
        $sameAs = self::profileSameAsUrls();
        $homeSummary = trim((string) config('seo.home_summary', ''));
        if ($homeSummary === '') {
            $homeSummary = 'Fully insured carpentry and renovation business in Carleton Place since 2020.';
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
            'email' => (string) (config('mail.from.address') ?: BusinessContact::DEFAULT_EMAIL),
            'foundingDate' => '2020',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Carleton Place',
                'addressRegion' => 'ON',
                'addressCountry' => 'CA',
            ],
            'areaServed' => $areas,
            'openingHours' => 'Mo-Fr 09:00-18:00',
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'opens' => '09:00',
                    'closes' => '18:00',
                ],
            ],
            'founder' => [
                '@type' => 'Person',
                '@id' => $url.'/#person-jonas',
                'name' => 'Jonas Zanini',
            ],
            'employee' => [
                ['@id' => $url.'/#person-mayara'],
            ],
        ];

        if ($sameAs !== []) {
            $business['sameAs'] = $sameAs;
        }

        $offers = [];
        foreach (PublicServices::offerCatalogItems() as $item) {
            $offers[] = [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'Service',
                    'name' => $item['name'],
                    'serviceType' => $item['service_type'],
                    'url' => $item['url'],
                ],
            ];
        }
        if ($offers !== []) {
            $business['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Carpentry and renovation services',
                'itemListElement' => $offers,
            ];
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

    /**
     * Homepage FAQ rows with service-area answers resolved from config.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function homeFaqItems(): array
    {
        $faqItems = config('seo.home_faq', []);
        if (! is_array($faqItems)) {
            return [];
        }

        $out = [];
        foreach ($faqItems as $row) {
            if (! is_array($row)) {
                continue;
            }
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if (($row['answer_from'] ?? '') === 'service_area_text') {
                $answer = trim((string) config('seo.service_area_text', ''));
            }
            if ($question === '' || $answer === '') {
                continue;
            }
            $out[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function homeFaqPage(string $baseUrl): ?array
    {
        $faqItems = self::homeFaqItems();
        if ($faqItems === []) {
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
        $description = $post->seoDescription();
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

        $imageUrl = $post->featuredImageUrl();
        if (! is_string($imageUrl) || $imageUrl === '') {
            $imageUrl = $base.'/'.ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
        }
        $image = [
            '@type' => 'ImageObject',
            'url' => $imageUrl,
        ];
        $alt = method_exists($post, 'featuredImageAlt') ? $post->featuredImageAlt() : '';
        if ($alt !== '') {
            $image['caption'] = $alt;
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

        $location = null;
        $town = $project->townLabel();
        if ($town !== '' && $project->isInServiceArea()) {
            $location = [
                '@type' => 'Place',
                'name' => $town,
            ];
            $gallery['contentLocation'] = $location;
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
        if ($location !== null) {
            $page['contentLocation'] = $location;
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

    /**
     * JSON-LD for /service-areas hub.
     *
     * @param  array<string, mixed>  $hub
     * @return array<string, mixed>
     */
    public static function serviceAreaHub(array $hub): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/service-areas';
        $name = (string) ($hub['title'] ?? 'Service areas');
        $description = (string) ($hub['meta_description'] ?? '');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Service areas', 'url' => $url],
                ]),
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => $name,
                    'description' => $description,
                    'inLanguage' => 'en-CA',
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#business'],
                ],
            ],
        ];
    }

    /**
     * JSON-LD for a published town page: LocalBusiness + Service + WebPage + FAQ.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function serviceAreaTown(array $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $slug = (string) ($page['slug'] ?? '');
        $url = $base.'/service-areas/'.$slug;
        $title = (string) ($page['title'] ?? '');
        $description = (string) ($page['meta_description'] ?? '');
        $h1 = (string) ($page['h1'] ?? $title);
        $crumbName = (string) ($page['breadcrumb_name'] ?? $page['name'] ?? $h1);
        $areas = $page['area_served'] ?? [];
        if (! is_array($areas) || $areas === []) {
            $areas = [['@type' => 'Place', 'name' => (string) ($page['name'] ?? 'Ottawa')]];
        }

        $service = [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $h1,
            'serviceType' => 'Carpentry and renovation',
            'description' => $description,
            'url' => $url,
            'provider' => [
                '@type' => 'LocalBusiness',
                '@id' => 'https://zaninirenovations.ca/#business',
                'name' => 'Zanini Renovations',
            ],
            'areaServed' => $areas,
        ];

        $graph = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => $base.'/'],
                ['name' => 'Service areas', 'url' => $base.'/service-areas'],
                ['name' => $crumbName, 'url' => $url],
            ]),
            [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $title,
                'description' => $description,
                'inLanguage' => 'en-CA',
                'isPartOf' => ['@id' => $base.'/#website'],
                'about' => ['@id' => $url.'#service'],
            ],
            $service,
        ];

        $faqItems = [];
        foreach ((array) ($page['faqs'] ?? []) as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }
        if ($faqItems !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => $faqItems,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * JSON-LD for the /services hub.
     *
     * @param  array<string, mixed>  $hub
     * @return array<string, mixed>
     */
    public static function servicesHub(array $hub): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/services';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Services', 'url' => $url],
                ]),
                [
                    '@type' => 'CollectionPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => (string) ($hub['title'] ?? 'Services'),
                    'description' => (string) ($hub['meta_description'] ?? ''),
                    'inLanguage' => 'en-CA',
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#business'],
                ],
            ],
        ];
    }

    /**
     * JSON-LD for a public service landing page.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function servicePage(array $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $slug = (string) ($page['slug'] ?? '');
        $url = $base.'/services/'.$slug;
        $title = (string) ($page['title'] ?? '');
        $description = (string) ($page['meta_description'] ?? '');
        $h1 = (string) ($page['h1'] ?? $title);
        $crumbName = (string) ($page['breadcrumb_name'] ?? $h1);
        $serviceType = (string) ($page['service_type'] ?? $crumbName);

        $service = [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $h1,
            'serviceType' => $serviceType,
            'description' => $description,
            'url' => $url,
            'provider' => ['@id' => $base.'/#business'],
            'areaServed' => config('seo.area_served', []),
        ];

        $graph = [
            self::breadcrumb([
                ['name' => 'Home', 'url' => $base.'/'],
                ['name' => 'Services', 'url' => $base.'/services'],
                ['name' => $crumbName, 'url' => $url],
            ]),
            [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $title,
                'description' => $description,
                'inLanguage' => 'en-CA',
                'isPartOf' => ['@id' => $base.'/#website'],
                'about' => ['@id' => $url.'#service'],
            ],
            $service,
        ];

        $faqItems = [];
        foreach ((array) ($page['faqs'] ?? []) as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }
        if ($faqItems !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => $faqItems,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function aboutPage(array $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/about';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'About', 'url' => $url],
                ]),
                [
                    '@type' => 'AboutPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => (string) ($page['title'] ?? 'About'),
                    'description' => (string) ($page['meta_description'] ?? ''),
                    'inLanguage' => 'en-CA',
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'mainEntity' => ['@id' => $base.'/#person-jonas'],
                    'about' => ['@id' => $base.'/#business'],
                ],
                [
                    '@type' => 'Person',
                    '@id' => $base.'/#person-jonas',
                    'name' => 'Jonas Zanini',
                    'jobTitle' => 'Owner & Contractor',
                    'description' => 'Contractor and owner of Zanini Renovations in Carleton Place, Ontario.',
                    'worksFor' => ['@id' => $base.'/#business'],
                ],
            ],
        ];
    }

    /**
     * Reviews page: visible quotes stay plain HTML; schema uses real Google reviews.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function reviewsPage(array $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/reviews';
        $reviews = [];
        foreach (GoogleReviews::textualReviews() as $row) {
            $reviews[] = [
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    'name' => (string) $row['author'],
                ],
                'reviewBody' => (string) $row['text'],
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => '5',
                    'bestRating' => '5',
                ],
                'itemReviewed' => ['@id' => $base.'/#business'],
            ];
        }

        $pageNode = [
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => (string) ($page['title'] ?? 'Reviews'),
            'description' => (string) ($page['meta_description'] ?? ''),
            'inLanguage' => 'en-CA',
            'isPartOf' => ['@id' => $base.'/#website'],
            'about' => ['@id' => $base.'/#business'],
        ];

        if ($reviews !== []) {
            $pageNode['mainEntity'] = [
                '@type' => 'LocalBusiness',
                '@id' => $base.'/#business',
                'name' => 'Zanini Renovations',
                'aggregateRating' => [
                    '@type' => 'AggregateRating',
                    'ratingValue' => GoogleReviews::ratingValue(),
                    'reviewCount' => (string) GoogleReviews::reviewCount(),
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'review' => $reviews,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Reviews', 'url' => $url],
                ]),
                $pageNode,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function contactPage(array $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = $base.'/contact';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $base.'/'],
                    ['name' => 'Contact', 'url' => $url],
                ]),
                [
                    '@type' => 'ContactPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => (string) ($page['title'] ?? 'Contact'),
                    'description' => (string) ($page['meta_description'] ?? ''),
                    'inLanguage' => 'en-CA',
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#business'],
                ],
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
