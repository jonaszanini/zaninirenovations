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
    | Structured data — geo (Carleton Place)
    |--------------------------------------------------------------------------
    */
    'latitude' => 45.1387107,
    'longitude' => -76.1525359,

    /*
    |--------------------------------------------------------------------------
    | areaServed (verbatim list for JSON-LD)
    |--------------------------------------------------------------------------
    */
    'area_served' => [
        ['@type' => 'City', 'name' => 'Ottawa'],
        ['@type' => 'City', 'name' => 'Carleton Place'],
        ['@type' => 'AdministrativeArea', 'name' => 'Centretown'],
        ['@type' => 'AdministrativeArea', 'name' => 'The Glebe'],
        ['@type' => 'AdministrativeArea', 'name' => 'Old Ottawa South'],
        ['@type' => 'AdministrativeArea', 'name' => 'Old Ottawa East'],
        ['@type' => 'AdministrativeArea', 'name' => 'Sandy Hill'],
        ['@type' => 'AdministrativeArea', 'name' => 'ByWard Market'],
        ['@type' => 'AdministrativeArea', 'name' => 'Lowertown'],
        ['@type' => 'AdministrativeArea', 'name' => 'New Edinburgh'],
        ['@type' => 'AdministrativeArea', 'name' => 'Westboro'],
        ['@type' => 'AdministrativeArea', 'name' => 'Wellington Village'],
        ['@type' => 'AdministrativeArea', 'name' => 'Hintonburg'],
        ['@type' => 'City', 'name' => 'Kanata'],
        ['@type' => 'City', 'name' => 'Stittsville'],
        ['@type' => 'City', 'name' => 'Barrhaven'],
        ['@type' => 'AdministrativeArea', 'name' => 'Bells Corners'],
        ['@type' => 'AdministrativeArea', 'name' => 'Centrepointe'],
        ['@type' => 'City', 'name' => 'Orléans'],
        ['@type' => 'AdministrativeArea', 'name' => 'Beacon Hill'],
        ['@type' => 'AdministrativeArea', 'name' => 'Blackburn Hamlet'],
        ['@type' => 'AdministrativeArea', 'name' => 'Vanier'],
        ['@type' => 'AdministrativeArea', 'name' => 'Rockcliffe Park'],
        ['@type' => 'AdministrativeArea', 'name' => 'Riverside South'],
        ['@type' => 'AdministrativeArea', 'name' => 'Hunt Club'],
        ['@type' => 'City', 'name' => 'Manotick'],
        ['@type' => 'City', 'name' => 'Carp'],
        ['@type' => 'City', 'name' => 'Dunrobin'],
        ['@type' => 'City', 'name' => 'Greely'],
        ['@type' => 'City', 'name' => 'Metcalfe'],
        ['@type' => 'City', 'name' => 'Osgoode'],
        ['@type' => 'City', 'name' => 'Richmond'],
        ['@type' => 'City', 'name' => 'Almonte'],
        ['@type' => 'City', 'name' => 'Ashton'],
        ['@type' => 'City', 'name' => 'Beckwith'],
        ['@type' => 'City', 'name' => 'Perth'],
        ['@type' => 'City', 'name' => 'Smiths Falls'],
        ['@type' => 'City', 'name' => 'Arnprior'],
        ['@type' => 'City', 'name' => 'Innisville'],
        ['@type' => 'City', 'name' => 'Lanark'],
        ['@type' => 'City', 'name' => 'Pakenham'],
        ['@type' => 'City', 'name' => 'Franktown'],
        ['@type' => 'City', 'name' => 'Balderson'],
        ['@type' => 'City', 'name' => 'Middleville'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verbatim LSI / semantic entity string (schema knowsAbout / keywords)
    |--------------------------------------------------------------------------
    */
    'lsi_verbatim' => 'O.Reg. 332/12 compliant (The specific regulation number for the Ontario Building Code). WSIB Clearance Certificate (Better than just "WSIB insured"). Form 1000 Compliance (Construction safety specific). Part 9 Housing & Small Buildings (The OBC section for residential work). Sub-floor depressurization rough-in (A 2026 OBC requirement for radon). Fire-resistance ratings (FRR). Structural load-bearing verification. Egress window compliance. General Liability Insurance $2M. Red Seal Certified Carpenter. Architectural Woodworking. MDF & Solid Wood Profiling. Coped Joints and Miters. Zero-clearance Built-ins. Scribe-to-wall fitting. Concealed hinge installation. Flush-mount cabinetry. Furniture-grade finishing. Engineered Wood Products (EWP). LVL Beam Installation (Laminated Veneer Lumber). Truss System Assembly. Advanced Framing (OVE). Sheathing and House Wrap. Header and Sill Plate sizing. Balloon vs. Platform Framing. Joist bridging and blocking. Space-saving condo storage. Ergonomic home office millwork. Legal basement apartment conversion. Multi-generational living renovations. Heritage home restoration. Bespoke closet solutions. Hidden door / Murphy door systems.',

    /*
    |--------------------------------------------------------------------------
    | Short, quotable homepage summary (WebPage + visible intro for AI/SEO)
    |--------------------------------------------------------------------------
    */
    'home_summary' => 'Zanini Renovations is a licensed carpenter and home renovation contractor based in Carleton Place, serving Ottawa and surrounding Ontario communities. We provide finish carpentry, rough carpentry, custom millwork, decks, basement framing, trim, fencing, flooring, drywall, and staircase work—with WSIB clearance, liability insurance, and O.Reg. 332/12 compliant practices.',

    'google_business_url' => env('SEO_GOOGLE_BUSINESS_URL', 'https://www.google.com/maps/place/Zanini+Renovations/@45.120446,-76.0081985,17z'),

    /*
    |--------------------------------------------------------------------------
    | Homepage FAQ (visible Q&A + FAQPage JSON-LD for AI answer engines)
    |--------------------------------------------------------------------------
    */
    'home_faq' => [
        [
            'question' => 'What renovation and carpentry services does Zanini Renovations offer?',
            'answer' => 'We offer residential carpentry and renovation services including decks and outdoor living, fencing, basement framing and finishing, interior trim and moulding, custom built-ins and shelving, hardwood flooring, drywall, accent walls and wainscoting, and wood staircase refinishing. Projects are completed by a licensed, Red Seal certified carpenter.',
        ],
        [
            'question' => 'What areas does Zanini Renovations serve?',
            'answer' => 'We are based in Carleton Place, Ontario and serve Ottawa, Kanata, Barrhaven, Orléans, Stittsville, Perth, Smiths Falls, and communities across the National Capital Region and Lanark County.',
        ],
        [
            'question' => 'Is Zanini Renovations licensed and insured for residential work?',
            'answer' => 'Yes. Jonas Zanini is a licensed carpenter (Red Seal certified) with WSIB clearance, Form 1000 construction safety compliance, and $3M general liability insurance. We follow Ontario Building Code requirements for Part 9 housing and small buildings, including O.Reg. 332/12 compliance.',
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
