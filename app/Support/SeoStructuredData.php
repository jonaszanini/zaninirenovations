<?php

namespace App\Support;

class SeoStructuredData
{
    /**
     * JSON-LD output safe on PHP 8.0+ (JSON_INVALID_UTF8_IGNORE exists from PHP 8.3).
     */
    public static function toJsonLd(array $data): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_IGNORE')) {
            $flags |= (int) constant('JSON_INVALID_UTF8_IGNORE');
        }

        return json_encode($data, $flags) ?: '{}';
    }

    public static function homeGraph(): array
    {
        $url = rtrim(config('app.url'), '/');
        $lsi = (string) config('seo.lsi_verbatim', '');
        $areas = config('seo.area_served', []);
        $fb = (string) config('social.facebook_url', '');
        $ig = (string) config('social.instagram_url', '');
        $sameAs = array_values(array_filter([$fb, $ig], static fn ($u) => is_string($u) && str_starts_with($u, 'http')));
        $ogRel = ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/');
        $ogAbs = $url.'/'.$ogRel;

        $personJonas = [
            '@type' => 'Person',
            '@id' => $url.'/#person-jonas',
            'name' => 'Jonas Zanini',
            'jobTitle' => 'Owner & Contractor',
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
            'caption' => 'Licensed Carpenter Ottawa, Finish Carpentry Services Ontario, Custom Millwork Ottawa — O.Reg. 332/12 compliant, WSIB Clearance Certificate, Carleton Place.',
        ];

        $business = [
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => $url.'/#business',
            'name' => 'Zanini Renovations',
            'url' => $url.'/',
            'logo' => $url.'/images/logo.png',
            'image' => [$url.'/'.ltrim((string) config('seo.og_image', 'images/home_1_slider_1.webp'), '/')],
            'description' => $lsi,
            'keywords' => $lsi,
            'knowsAbout' => $lsi,
            'telephone' => '+1-647-834-7570',
            'email' => (string) config('mail.from.address', ''),
            'priceRange' => '$$',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Carleton Place',
                'addressRegion' => 'ON',
                'addressCountry' => 'CA',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) config('seo.latitude', 45.1387107),
                'longitude' => (float) config('seo.longitude', -76.1525359),
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
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => '5',
                'bestRating' => '5',
                'worstRating' => '1',
                'ratingCount' => '3',
                'reviewCount' => '3',
            ],
            'founder' => ['@id' => $url.'/#person-jonas'],
            'employee' => [
                ['@id' => $url.'/#person-mayara'],
            ],
            'review' => [
                [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => 'Alana Hamilton'],
                    'reviewBody' => 'Professional, friendly drywall, flooring and trim in our basement; great communication and fair pricing.',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => '5', 'bestRating' => '5'],
                ],
                [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => 'Kawana Bazzo'],
                    'reviewBody' => 'Attentive, organized, and clean throughout the project; happy with results and would work together again.',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => '5', 'bestRating' => '5'],
                ],
                [
                    '@type' => 'Review',
                    'author' => ['@type' => 'Person', 'name' => 'Mike Dobson'],
                    'reviewBody' => 'Professional fence and interior work; prompt, thorough, and completed on a quick timeframe.',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => '5', 'bestRating' => '5'],
                ],
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

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $url.'/#website',
                    'url' => $url.'/',
                    'name' => 'Zanini Renovations',
                    'publisher' => ['@id' => $url.'/#business'],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'/#webpage',
                    'url' => $url.'/',
                    'name' => 'Licensed Carpenter Ottawa | O.Reg. 332/12 | Zanini Renovations',
                    'isPartOf' => ['@id' => $url.'/#website'],
                    'about' => ['@id' => $url.'/#business'],
                    'primaryImageOfPage' => ['@id' => $url.'/#primary-image'],
                ],
                $personJonas,
                $personMayara,
                $primaryImage,
                $business,
                $itemList,
            ],
        ];
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

    /** Service node for embedding in @graph (no @context). */
    public static function serviceKitchenNode(): array
    {
        $url = rtrim(config('app.url'), '/');

        return [
            '@type' => 'Service',
            '@id' => $url.'/services/custom-kitchen-remodel#service',
            'name' => 'Custom Kitchen Remodel',
            'serviceType' => 'Kitchen Cabinet Refacing Ottawa',
            'provider' => ['@id' => $url.'/#business'],
            'areaServed' => ['Ottawa', 'Kanata', 'Carleton Place'],
            'description' => (string) config('seo.lsi_verbatim', ''),
        ];
    }

    public static function kitchenGraph(): array
    {
        $url = rtrim(config('app.url'), '/');
        $pageUrl = $url.'/services/custom-kitchen-remodel';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $pageUrl.'#webpage',
                    'url' => $pageUrl,
                    'name' => 'Kitchen Refacing Kanata, Ottawa | O.Reg. 332/12 | Zanini Renovations',
                    'isPartOf' => ['@id' => $url.'/#website'],
                ],
                self::breadcrumb([
                    ['name' => 'Home', 'url' => $url.'/'],
                    ['name' => 'Custom Kitchen Remodel', 'url' => $pageUrl],
                ]),
                self::serviceKitchenNode(),
            ],
        ];
    }
}
