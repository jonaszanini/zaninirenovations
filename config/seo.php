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

];
