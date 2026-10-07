<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Optional verification & analytics (non-intrusive when empty)
    |--------------------------------------------------------------------------
    */
    'google_site_verification' => env('SEO_GOOGLE_SITE_VERIFICATION', ''),
    'ga4_measurement_id' => env('SEO_GA4_MEASUREMENT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Open Graph / Twitter image (absolute path from /public)
    |--------------------------------------------------------------------------
    */
    'og_image' => env('SEO_OG_IMAGE', 'images/home_1_slider_1.webp'),

    /*
    |--------------------------------------------------------------------------
    | Structured data — geo
    |--------------------------------------------------------------------------
    | Exact pin coordinates removed per service-area business guidelines.
    | Do not publish a street address, postal code, or precise geo pin.
    |
    | Visible service-area sentence — same wording as the home location blurb.
    | Do not invent shorter pairings (e.g. "Ottawa or Gatineau") in new UI.
    */
    'service_area_text' => 'Based in Carleton Place, we proudly serve Perth, Smiths Falls, Lanark County, the Ottawa region, and Gatineau.',

    /*
    |--------------------------------------------------------------------------
    | areaServed (verbatim list for JSON-LD)
    |--------------------------------------------------------------------------
    | Names only — must match places named in service_area_text. Do not add
    | neighbourhoods or towns that are not in that sentence.
    */
    'area_served' => [
        ['@type' => 'City', 'name' => 'Carleton Place'],
        ['@type' => 'City', 'name' => 'Perth'],
        ['@type' => 'City', 'name' => 'Smiths Falls'],
        ['@type' => 'AdministrativeArea', 'name' => 'Lanark County'],
        ['@type' => 'Place', 'name' => 'Ottawa region'],
        ['@type' => 'City', 'name' => 'Gatineau'],
    ],

    /*
    | Filtered /blog?tag= and /blog?category= stay noindex until this many
    | posts are published. /blog?q= is always noindex.
    */
    'blog_filter_index_min_posts' => (int) env('SEO_BLOG_FILTER_INDEX_MIN_POSTS', 8),

    /*
    |--------------------------------------------------------------------------
    | Topics the business actually covers (JSON-LD knowsAbout — short labels)
    |--------------------------------------------------------------------------
    */
    'knows_about' => [
        'Finish carpentry',
        'Rough carpentry',
        'Custom millwork',
        'Residential fencing',
        'Deck construction',
        'Basement framing and finishing',
        'Interior trim and moulding',
        'Hardwood flooring installation',
        'Drywall',
        'Wood staircase refinishing',
        'Ontario Building Code O.Reg. 332/12',
        'Part 9 housing and small buildings',
        'WSIB clearance certificate',
    ],

    'liability_insurance' => '$3 million',

    /*
    |--------------------------------------------------------------------------
    | Short, quotable homepage summary (WebPage + visible intro for AI/SEO)
    |--------------------------------------------------------------------------
    */
    'home_summary' => 'Zanini Renovations is a one-person, fully insured carpentry and renovation business based in Carleton Place, Ontario, since 2020. Decks, fences and gates, basement finishing, finish carpentry and trim, custom built-ins and closets, staircases, drywall and flooring—with $3 million general liability insurance, WSIB clearance, and O.Reg. 332/12 compliant practices.',

    'home_title' => 'Carpenter & Renovation Contractor in Carleton Place | Zanini',

    'google_business_url' => env('SEO_GOOGLE_BUSINESS_URL') ?: 'https://maps.google.com/?cid=7676669856408637146',

    'google_review_url' => 'https://search.google.com/local/writereview?placeid=ChIJwbfj-ifxCicR2o4vyY8CiWo',

    /*
    |--------------------------------------------------------------------------
    | Homepage FAQ (visible Q&A + FAQPage JSON-LD for AI answer engines)
    |--------------------------------------------------------------------------
    */
    'home_faq' => [
        [
            'question' => 'What renovation and carpentry services does Zanini Renovations offer?',
            'answer' => 'We offer residential carpentry and renovation services including decks and outdoor living, fencing, basement framing and finishing, interior trim and moulding, custom built-ins and shelving, hardwood flooring, drywall, accent walls and wainscoting, and wood staircase refinishing. Projects are completed by a fully insured contractor.',
        ],
        [
            'question' => 'What areas does Zanini Renovations serve?',
            'answer_from' => 'service_area_text',
        ],
        [
            'question' => 'Is Zanini Renovations fully insured for residential work?',
            'answer' => 'Yes. Zanini Renovations carries $3 million in general liability insurance, ensuring your home and project are fully protected. All of our work strictly adheres to the Ontario Building Code (O.Reg. 332/12) requirements for Part 9 housing and small buildings, delivering structural integrity and professional craftsmanship you can trust.',
        ],
        [
            'question' => 'How do I get a quote for a renovation or carpentry project?',
            'answer' => 'Contact us by phone at (647) 834-7570, WhatsApp, or the contact form on zaninirenovations.ca. We discuss your project scope, visit the site when needed, and provide a written estimate in Canadian dollars.',
        ],
        [
            'question' => 'Does Zanini Renovations handle both labour and materials?',
            'answer' => 'We can quote labour-only carpentry and renovation work or include materials in the estimate depending on your project. Scope, timeline, and pricing are confirmed before work begins.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI crawler user-agents explicitly allowed on public pages (GEO)
    |--------------------------------------------------------------------------
    */
    'ai_crawler_user_agents' => [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'Google-Extended',
        'Google-CloudVertexBot',
        'ClaudeBot',
        'Claude-SearchBot',
        'Claude-User',
        'PerplexityBot',
        'Perplexity-User',
        'Meta-ExternalAgent',
        'Meta-ExternalFetcher',
        'Amazonbot',
        'Applebot-Extended',
        'AzureAI-SearchBot',
        'DeepSeekBot',
        'MistralAI-User',
        'cohere-ai',
        'Bytespider',
        'YouBot',
        'PhindBot',
        'iAskBot',
        'CCBot',
        'omgili',
        'omgilibot',
        'Diffbot',
        'FirecrawlAgent',
        'Crawl4AI',
        'TavilyBot',
    ],

];
