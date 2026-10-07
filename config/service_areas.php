<?php

/**
 * Public /service-areas hub and published town pages (SEO batch 5).
 *
 * Gate: published pages need ≥2 local projects and ≥400 words of unique copy.
 * Unpublished towns stay on the hub as plain text (with project links when the
 * slug exists in the portfolio). Orléans is intentionally omitted.
 */

return [

    'map_embed_src' => 'https://maps.google.com/maps?cid=7676669856408637146&output=embed',

    'cta' => [
        'heading' => 'Request a free estimate',
        'text' => 'Call (647) 834-7570 or send a message. Fully insured · in business since 2020 · one-year workmanship warranty.',
        'button_label' => 'Get a free estimate',
        'button_url' => '/#contact',
        'phone_label' => 'Call (647) 834-7570',
        'phone_href' => 'tel:+16478347570',
    ],

    'hub' => [
        'title' => 'Service Areas: Carleton Place, Lanark & Ottawa | Zanini',
        'meta_description' => 'Where Zanini Renovations works: Carleton Place, Beckwith, Almonte, Perth, Smiths Falls, Stittsville, Kanata and the wider Ottawa and Lanark County area.',
        'h1' => 'Areas We Serve in Lanark County and West Ottawa',
        'intro_paragraphs' => [
            'Zanini Renovations is a fully insured carpentry and renovation business with an office in Carleton Place. Owner Jonas Zanini has run the company since 2020. We take on residential carpentry, kitchen remodels, and commercial work, and we coordinate with qualified trades when a job needs electrical or plumbing. Call (647) 834-7570 for a free estimate; work is backed by a one-year workmanship warranty.',
            'We work across Lanark County, west Ottawa, and Gatineau. Open a town page for Carleton Place, Kanata and Stittsville, or Barrhaven, or browse the full list of communities below.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Published town pages
    |--------------------------------------------------------------------------
    */
    'pages' => [

        'carleton-place' => [
            'published' => true,
            'slug' => 'carleton-place',
            'name' => 'Carleton Place',
            'hub_label' => 'Carleton Place',
            'featured_note' => 'Office in town. Also Beckwith and Franktown.',
            'breadcrumb_name' => 'Carleton Place & Beckwith',
            'title' => 'Carpenter in Carleton Place & Beckwith | Zanini Renovations',
            'meta_description' => 'Carleton Place carpenter for decks, fences, basements, trim and built-ins in town, Beckwith and Franktown. Fully insured, since 2020. Free estimate.',
            'h1' => 'Carpenter & Renovation Contractor in Carleton Place and Beckwith',
            'covers' => ['Beckwith', 'Franktown'],
            'area_served' => [
                ['@type' => 'City', 'name' => 'Carleton Place'],
                ['@type' => 'AdministrativeArea', 'name' => 'Beckwith'],
                ['@type' => 'Place', 'name' => 'Franktown'],
            ],
            'project_slugs' => [
                'three-fences-at-once',
                'basement-flood-restoration',
                'outside-stairs',
                'fence',
                'fence-4',
                'backyard-privacy-fence-carleton-place',
                'deck-stairs-landing-carleton-place',
                'side-yard-fence-gates-carleton-place',
                'fireplace-surround-niche-carleton-place',
            ],
            'permit_links' => [
                [
                    'label' => 'Town of Carleton Place Building Department',
                    'url' => 'https://carletonplace.ca/town/municipal-services-info/building-department',
                ],
                [
                    'label' => 'Beckwith Township Building / By-Law',
                    'url' => 'https://beckwith.ca/services/building-by-law/',
                ],
            ],
            'nearby' => ['kanata', 'barrhaven'],
            'intro_paragraphs' => [
                'Zanini Renovations keeps its office in Carleton Place. Owner Jonas Zanini has run the business since 2020. We are fully insured, we back our carpentry with a one-year workmanship warranty, and we take kitchen remodels and commercial work as well as fences, decks, basements, and trim. Call (647) 834-7570 for a free written estimate on a home in town, in Beckwith Township, or in Franktown.',
                'Carleton Place sits on the Mississippi River at the Lanark County edge of Ottawa. From the office we work on in-town streets, river lots, and rural Beckwith addresses that use a Carleton Place mailing address. Franktown is part of that same local area: we quote and build there the same way we do in town.',
            ],
            'services_heading' => 'Services we do in Carleton Place, Beckwith and Franktown',
            'services_intro' => 'Fences, exterior stairs, basement repairs, and finish carpentry are the work we do most often around Carleton Place, Beckwith, and Franktown.',
            'services' => [
                [
                    'title' => 'Fences and gates',
                    'text' => 'We have built privacy fences on shared lot lines, a side-yard fence with working gates, and a stretch where three neighbouring yards needed fencing together. Gates get braced so they hang square. We locate utilities before digging post holes and set posts for frost so the fence stays true through winter.',
                ],
                [
                    'title' => 'Exterior stairs, landings and decks',
                    'text' => 'We replace worn exterior stairs and build landings that connect a door to the yard. Deck stairs and landings are framed as carpentry, fitted to the house and the grade. If the house already has a deck, we can add stairs that match the existing structure.',
                ],
                [
                    'title' => 'Basements, trim and fireplace surrounds',
                    'text' => 'Inside town we have rebuilt a basement after flooding—framing, drywall, flooring, and trim—while qualified trades handled electrical and plumbing. We also build fireplace surrounds and niches when a living room needs a painted, site-built finish. Finish carpentry in older Carleton Place houses often means scribing trim to floors and plaster that are no longer level.',
                ],
            ],
            'projects_intro' => 'Recent carpentry in Carleton Place is linked below, with photographs on each project page. We do the same work in Beckwith and Franktown.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Which office issues a building permit depends on the property line, not on the mailing address on your bills. Work inside the Town of Carleton Place goes through the Town building department. Work in Beckwith Township, including Franktown, goes through Beckwith Township. Both offices are linked below. Height limits and setbacks belong to those municipalities, so check with them—or send us the address and we will help you identify the right office.',
                'Older in-town houses often have out-of-square walls, patched plaster, and fences that follow a slope between close neighbours. Lots farther out in Beckwith more often need privacy screening, a gate for dogs, or stairs from a deck into a larger yard. We plan the carpentry around the house in front of us.',
                'When a renovation needs electrical or plumbing, we coordinate with qualified trades. Kitchen remodels and small commercial carpentry follow the same split: we own the woodwork, and the written estimate names anything that sits with those trades.',
            ],
            'map_heading' => 'Where we work from Carleton Place',
            'map_paragraphs' => [
                'We work from Carleton Place across Beckwith, Franktown, and west Ottawa. Nearby pages cover Kanata and Stittsville, and Barrhaven. Browse every community we serve, or open our location on Google Maps.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you charge a travel fee in Carleton Place or Beckwith?',
                    'answer' => 'Carleton Place is our home base, and Beckwith and Franktown are treated as local work from that office. The estimate states the carpentry scope in Canadian dollars. If a site visit is needed to measure, we say so up front.',
                ],
                [
                    'question' => 'Which office handles building permits for my address, the Town or Beckwith Township?',
                    'answer' => 'Use the Town of Carleton Place building department for addresses inside the town boundary. Use Beckwith Township for Beckwith and Franktown addresses. If you are unsure which side of the line you sit on, check with those offices or send us the address and we will help you identify the right one before you apply.',
                ],
                [
                    'question' => 'Do you work in Franktown as well as in town?',
                    'answer' => 'Yes. Franktown is part of the Carleton Place and Beckwith service area on this page. We quote and build there the same way we do in town: fully insured carpentry, a one-year workmanship warranty, and qualified trades for electrical or plumbing when the job needs them.',
                ],
            ],
        ],

        'kanata' => [
            'published' => true,
            'slug' => 'kanata',
            'name' => 'Kanata',
            'hub_label' => 'Kanata & Stittsville',
            'featured_note' => 'Decks, built-ins, finish carpentry, and fences.',
            'breadcrumb_name' => 'Kanata & Stittsville',
            'title' => 'Carpenter in Kanata & Stittsville | Zanini Renovations',
            'meta_description' => 'Decks, fences, built-ins and finish carpentry for Kanata and Stittsville homes. Fully insured, based in Carleton Place since 2020. Free estimate.',
            'h1' => 'Carpentry, Basements & Decks in Kanata & Stittsville',
            'covers' => ['Stittsville'],
            'area_served' => [
                ['@type' => 'Place', 'name' => 'Kanata, Ottawa'],
                ['@type' => 'Place', 'name' => 'Stittsville, Ottawa'],
            ],
            'project_slugs' => [
                'floating-deck-with-gravel-around',
                'fireplace-renovation',
                'backsplash-installation',
                'backyard-patio-pad-kanata',
                'built-in-shelving-wall-kanata',
                'fence-3',
            ],
            'permit_links' => [
                [
                    'label' => 'City of Ottawa Building Code Services',
                    'url' => 'https://ottawa.ca/en/planning-development-and-construction/building-and-renovating',
                ],
            ],
            'nearby' => ['barrhaven', 'carleton-place'],
            'intro_paragraphs' => [
                'Kanata and Stittsville are west Ottawa communities we serve from our Carleton Place office. Zanini Renovations has been in business since 2020. Owner Jonas Zanini runs the company. We are fully insured, we offer a one-year workmanship warranty, and we take kitchen remodels and commercial carpentry as well as decks, fences, built-ins, and trim. Call (647) 834-7570 for a free estimate on a Kanata or Stittsville house.',
                'The two communities sit next to each other, and the City of Ottawa handles building permits for both. On this page you will find Kanata decks, patio pads, fireplace and tile work, and built-in shelving, along with a backyard fence and gate we built in Stittsville.',
            ],
            'services_heading' => 'Services we do in Kanata and Stittsville',
            'services_intro' => 'Interior finish work and outdoor carpentry both show up in our Kanata and Stittsville jobs: decks and patio pads, fireplace and tile updates, built-in storage, and fencing.',
            'services' => [
                [
                    'title' => 'Decks and patio pads',
                    'text' => 'In Kanata we have built a floating deck with gravel around the posts and a backyard patio pad. Ground-level decks on newer west Ottawa lots often sit close to the fence line and need clean steps rather than a tall structure. We build those as carpentry: framing, decking, and stairs that match how people walk out of the house.',
                ],
                [
                    'title' => 'Fireplace, tile and built-ins',
                    'text' => 'One Kanata house received a fireplace renovation and a hexagon tile backsplash. A separate Kanata job is a built-in shelving wall. That is finish carpentry and millwork done on site. We can leave the woodwork paint-ready or finish it ourselves; the estimate says which.',
                ],
                [
                    'title' => 'Fences in Stittsville',
                    'text' => 'In Stittsville we built a backyard fence and gate. Posts, rails, and a gate that hangs square are the point of that job. We arrange utility locates before we dig. Fence height and pool-enclosure rules are City of Ottawa by-laws; the Building Code Services page linked below is the place to confirm them for your lot.',
                ],
            ],
            'projects_intro' => 'Kanata decks, patio pads, fireplace and tile work, and built-ins are linked below, along with the Stittsville fence we built for a west Ottawa backyard.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Kanata and Stittsville addresses are inside the City of Ottawa. Building permits, when they are required, go through Ottawa Building Code Services. We link that office below. Whether a deck, basement, or interior renovation needs a permit depends on the work and on the city’s current rules. Ask the city, or include the question when you request an estimate.',
                'West Ottawa houses in this set tend to be newer than the brick streetscapes in downtown Carleton Place. That shows up in the work: floating decks, patio pads, built-in storage walls, and fireplace updates in rooms that were finished by a builder and then restyled by the homeowner. We still scribe trim and flash ledgers with the same care. Fully insured carpentry does not change because the subdivision is newer.',
                'Qualified trades handle electrical and plumbing on Kanata and Stittsville renovations when those trades are part of the scope. The written estimate covers the carpentry—framing, decks, fences, trim, built-ins, and the tile backsplashes we install—and names anything that sits with those trades.',
            ],
            'map_heading' => 'Kanata, Stittsville and nearby towns',
            'map_paragraphs' => [
                'We quote west Ottawa work from our Carleton Place office. Nearby pages cover Barrhaven and Carleton Place with Beckwith. See the full list of communities, or open our location on Google Maps.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you serve all of Kanata, and Stittsville as well?',
                    'answer' => 'Yes. This page is for Kanata and Stittsville together. We quote west Ottawa work from our Carleton Place office. If your address is in another Ottawa community, the service-areas list will show whether we work there.',
                ],
                [
                    'question' => 'Who issues building permits for a Kanata or Stittsville address?',
                    'answer' => 'The City of Ottawa Building Code Services office. Send drawings or photos with your estimate request if you already know a permit is in play. We file permits only when that is agreed in the written estimate.',
                ],
                [
                    'question' => 'Can you quote a deck, fence, or built-in from photos before visiting?',
                    'answer' => 'You can send photos and a short description to get a sense of scope. Many Kanata and Stittsville jobs still need a site visit to measure, check grade, or look at an existing fireplace or fence line. We tell you which of those applies before you book anything.',
                ],
            ],
        ],

        'barrhaven' => [
            'published' => true,
            'slug' => 'barrhaven',
            'name' => 'Barrhaven',
            'hub_label' => 'Barrhaven',
            'featured_note' => 'Basements, built-ins, and fences.',
            'breadcrumb_name' => 'Barrhaven',
            'title' => 'Carpenter in Barrhaven, Ottawa | Zanini Renovations',
            'meta_description' => 'Basement finishing, built-ins, fences and bathroom millwork in Barrhaven. Fully insured carpenter based in Carleton Place since 2020. Free estimate.',
            'h1' => 'Carpentry, Basements & Built-ins in Barrhaven',
            'covers' => [],
            'area_served' => [
                ['@type' => 'Place', 'name' => 'Barrhaven, Ottawa'],
            ],
            'project_slugs' => [
                'basement-finishing',
                'built-in-window-storage',
                'fence-2',
                'custom-bathroom-vanity-barrhaven',
            ],
            'permit_links' => [
                [
                    'label' => 'City of Ottawa Building Code Services',
                    'url' => 'https://ottawa.ca/en/planning-development-and-construction/building-and-renovating',
                ],
            ],
            'nearby' => ['kanata', 'carleton-place'],
            'intro_paragraphs' => [
                'Barrhaven is a south-west Ottawa community we serve from Carleton Place. Zanini Renovations has been in business since 2020, with owner Jonas Zanini. We are fully insured and we include a one-year workmanship warranty on our carpentry. Kitchen remodels and commercial work are in the mix when the job is a fit. Call (647) 834-7570 for a free estimate on a Barrhaven basement, built-in, fence, or bathroom millwork project.',
                'The Barrhaven work on this site is a finished basement, a window seat with storage, a custom bathroom vanity, and a backyard privacy fence with a gate. That mix is typical of the calls we take in this part of Ottawa: lower-level rooms that need to become living space, millwork that has to fit an existing opening, and yards that need privacy from the next lot.',
            ],
            'services_heading' => 'Services we do in Barrhaven',
            'services_intro' => 'Basement finishing, built-in storage, bathroom millwork, and backyard fencing are the Barrhaven jobs linked on this page.',
            'services' => [
                [
                    'title' => 'Basement finishing',
                    'text' => 'Our Barrhaven basement finishing project covers framing through trim, including built-in storage on the main floor of the same house. Basement carpentry is a sequence: layout, framing, insulation and vapour details as specified, drywall, flooring, doors, and the millwork that makes the room usable. Qualified trades take electrical and plumbing, and the written estimate names that work separately.',
                ],
                [
                    'title' => 'Built-ins and bathroom millwork',
                    'text' => 'The built-in window storage in Barrhaven is a window seat with drawers, made to the opening rather than cut down from a catalogue bench. The custom bathroom vanity is site-built millwork for that room. Both jobs are the same kind of work we do in kitchen remodels: measure, build, install, and leave the piece sitting true on floors that are rarely perfectly flat.',
                ],
                [
                    'title' => 'Backyard fence and gate',
                    'text' => 'We also built a backyard privacy fence with a gate in Barrhaven. Posts are set for frost. The gate is braced. Before we dig, we arrange utility locates. City of Ottawa fence rules sit with the city; the Building Code Services page linked below is the place to confirm height and enclosure requirements for your yard.',
                ],
            ],
            'projects_intro' => 'Barrhaven projects are linked below, with photographs on each project page.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Barrhaven is inside the City of Ottawa, so building permits go through Ottawa Building Code Services. Finishing a basement often needs a permit; a vanity or a window seat might not. Ask the city, or include the question when you request an estimate and we will tell you what we know for that scope.',
                'Barrhaven houses in this portfolio are suburban lots with finished or half-finished lower levels and backyards that need privacy from the next pad. The carpentry still has to be square, painted or paint-ready, and fully insured. The one-year workmanship warranty is the same as on our other work.',
                'Commercial carpentry is available in this area when the scope is woodwork we perform. Send photos of the basement, the window, the bathroom, or the fence line. We answer with what we will build, what qualified trades should own, and what the municipality should see before anyone opens a wall.',
            ],
            'map_heading' => 'Barrhaven and nearby service areas',
            'map_paragraphs' => [
                'Nearby pages cover Kanata and Stittsville, and Carleton Place with Beckwith. See the full list of communities we serve, or open our location on Google Maps.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you take basement finishing and built-in work in Barrhaven?',
                    'answer' => 'Yes. The Barrhaven work on this page is mostly interior: a finished basement, a window seat with storage, and a custom bathroom vanity, plus a backyard privacy fence. Send photos of the room or yard and we will tell you whether it is a carpentry job we take.',
                ],
                [
                    'question' => 'Who issues building permits in Barrhaven?',
                    'answer' => 'The City of Ottawa Building Code Services. We link their building-and-renovating page on this site. Whether a permit is needed depends on the work. Confirm with the city for your address, or ask us when you request an estimate.',
                ],
                [
                    'question' => 'Can we send photos and a short description for an estimate?',
                    'answer' => 'Yes. Call (647) 834-7570 or use the contact form. Photos of the basement, the window, the bathroom, or the fence line help. We may still need to visit the Barrhaven house to measure before the estimate is final. Hours are Monday to Friday, 9 AM to 6 PM.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hub groups — GBP areas + Gatineau + Riverside South. No Orléans.
    |--------------------------------------------------------------------------
    */
    'hub_groups' => [
        [
            'heading' => 'Lanark County',
            'intro' => '',
            'towns' => [
                [
                    'name' => 'Carleton Place',
                    'page' => 'carleton-place',
                    'note' => 'Office in town. Also Beckwith and Franktown.',
                ],
                [
                    'name' => 'Beckwith',
                    'note' => 'Township lots and rural addresses.',
                ],
                [
                    'name' => 'Franktown',
                    'note' => 'Decks, fences, and carpentry.',
                ],
                [
                    'name' => 'Almonte',
                    'project_slugs' => ['gabled-porch-roof'],
                ],
                [
                    'name' => 'Mississippi Mills',
                    'note' => 'Almonte and Pakenham.',
                    'project_slugs' => ['gabled-porch-roof'],
                ],
                [
                    'name' => 'Pakenham',
                ],
                [
                    'name' => 'Perth',
                    'project_slugs' => ['custom-classic-fireplace-mantel'],
                ],
                [
                    'name' => 'Smiths Falls',
                ],
                [
                    'name' => 'Lanark',
                    'note' => 'Lanark Highlands.',
                ],
                [
                    'name' => 'Lanark County',
                    'note' => 'County-wide carpentry.',
                ],
                [
                    'name' => 'Arnprior',
                ],
            ],
        ],
        [
            'heading' => 'Ottawa communities',
            'intro' => '',
            'towns' => [
                [
                    'name' => 'Ottawa',
                    'note' => 'City-wide carpentry.',
                ],
                [
                    'name' => 'Kanata',
                    'page' => 'kanata',
                    'note' => 'Kanata and Stittsville.',
                ],
                [
                    'name' => 'Stittsville',
                    'note' => 'West Ottawa. Fence and gate work.',
                    'project_slugs' => ['fence-3'],
                ],
                [
                    'name' => 'Barrhaven',
                    'page' => 'barrhaven',
                ],
                [
                    'name' => 'Nepean',
                    'project_slugs' => ['kitchen-renovations'],
                ],
                [
                    'name' => 'Manotick',
                ],
                [
                    'name' => 'Richmond',
                ],
                [
                    'name' => 'Carp',
                ],
                [
                    'name' => 'Ashton',
                    'project_slugs' => ['engineering-hard-wood-flooring-installation'],
                ],
                [
                    'name' => 'West Carleton',
                    'project_slugs' => ['custom-shed-doors'],
                ],
                [
                    'name' => 'Riverside South',
                    'project_slugs' => ['side-walkway-backyard-patio-riverside-south'],
                ],
                [
                    'name' => 'South Keys',
                    'project_slugs' => ['catio', '2nd-floor-and-stairs-renovation'],
                ],
                [
                    'name' => 'Alta Vista',
                    'project_slugs' => ['lattice-fence'],
                ],
            ],
        ],
        [
            'heading' => 'Gatineau',
            'intro' => '',
            'towns' => [
                [
                    'name' => 'Gatineau, QC',
                    'note' => 'Carpentry on the Quebec side.',
                    'project_slugs' => ['basement-closet-doors-gatineau'],
                ],
            ],
        ],
    ],

];
