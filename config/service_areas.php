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
            'Zanini Renovations is a fully insured carpentry and renovation business with an office in Carleton Place. Owner Jonas Zanini has run the company since 2020. We take on residential carpentry, kitchen remodels, and commercial work, and we coordinate with qualified trades when a job needs electrical or plumbing alongside the carpentry.',
            'This page lists every community we name as a service area, grouped the way people actually search: Lanark County towns, Ottawa communities, and Gatineau. Three of those places have their own pages because we have enough completed local projects to write about them in detail. Everywhere else stays on this hub until that bar is met. We do not invent jobs or reviews to fill a town page.',
            'Call (647) 834-7570 for a free estimate. Work is backed by a one-year workmanship warranty. Building permits belong to the municipality that covers the address—the Town of Carleton Place, Beckwith Township, the City of Ottawa, or the town office named on each listing—not to a generic provincial form we could copy here.',
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
                'Zanini Renovations keeps its office in Carleton Place, and that is our home base. Owner Jonas Zanini has run the business since 2020. We are fully insured, we back carpentry with a one-year workmanship warranty, and we take kitchen remodels and commercial work when the scope is carpentry we can stand behind. Call (647) 834-7570 if you want a free written estimate for a house in town, in Beckwith Township, or in Franktown.',
                'Carleton Place sits on the Mississippi River at the Lanark County edge of the Ottawa market. From the office we cover in-town streets, the lots that back onto the river and the railway, and the rural addresses that use a Carleton Place mailing address but sit in Beckwith. Franktown is part of that same township pattern: it is not a separate company territory, and we treat it as local work rather than a special trip category.',
            ],
            'services_heading' => 'Services we do in Carleton Place, Beckwith and Franktown',
            'services_intro' => 'The mix below matches the Carleton Place projects already on this website. We do not pad this list with work done in Kanata, Barrhaven, or Toronto.',
            'services' => [
                [
                    'title' => 'Fences and gates',
                    'text' => 'A large share of our published Carleton Place work is fencing. We have built privacy fences on shared lot lines, a side-yard fence with working gates, and a job where three neighbouring yards needed fencing in one stretch. Gates get braced so they hang square. We locate utilities before digging post holes and we set posts for frost, because a fence that heaves in March is not finished work.',
                ],
                [
                    'title' => 'Exterior stairs, landings and decks',
                    'text' => 'We replace worn exterior stairs and build landings that connect a door to the yard. Deck stairs and landings in Carleton Place are built as carpentry, not as a catalogue kit dropped on grade. If the house already has a deck, we can add stairs that match the existing structure instead of forcing a full rebuild.',
                ],
                [
                    'title' => 'Basements, trim and fireplace surrounds',
                    'text' => 'Inside town we have rebuilt a basement after flooding—framing, drywall, flooring, and trim—while qualified trades handled electrical and plumbing. We also build fireplace surrounds and niches when a living room needs a painted, site-built finish rather than a store-bought mantel. Finish carpentry in older Carleton Place houses often means scribing trim to floors and plaster that are no longer level.',
                ],
            ],
            'projects_intro' => 'These are the Carleton Place projects currently on the site. Each link goes to the real portfolio page with photographs. Beckwith and Franktown are served from this same page; they do not have separate project lists yet.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Which office issues a building permit depends on the property line, not on the mailing address on your bills. Work inside the Town of Carleton Place goes through the Town building department. Work in Beckwith Township, including Franktown, goes through Beckwith Township. We link both offices below. We do not quote height limits, setbacks, or “what needs a permit” numbers here, because those rules belong to the municipality and they change.',
                'Older in-town houses often hide out-of-square walls, patched plaster, and fences that have to follow a slope between two close neighbours. Lots farther out in Beckwith more often need privacy screening, a gate for dogs, or stairs that drop from a deck into a larger yard. That split comes from the jobs we have already photographed in Carleton Place, not from a guessed profile of the town.',
                'When a renovation needs electrical or plumbing, we coordinate with qualified trades rather than stretching carpentry into those trades. Kitchen remodels and small commercial carpentry follow the same rule: we own the woodwork, and we keep the estimate honest about what sits outside it.',
            ],
            'map_heading' => 'Where we work from Carleton Place',
            'map_paragraphs' => [
                'The map is our Google Business listing. We do not publish a street address. Nearby published pages cover Kanata and Stittsville, and Barrhaven. Almonte, Perth, Smiths Falls, and the rest of the Lanark list stay on the service-areas hub until we have enough local projects to give them their own copy.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you charge a travel fee in Carleton Place or Beckwith?',
                    'answer' => 'Carleton Place is our home base, and Beckwith and Franktown are treated as local work from that office. The estimate states the carpentry scope in Canadian dollars. If a site visit is needed to measure, we say so up front instead of burying a surprise fee in the fine print.',
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
                'Kanata and Stittsville are west Ottawa communities we serve from our Carleton Place office. Zanini Renovations has been in business since 2020. Owner Jonas Zanini runs the company. We are fully insured, we offer a one-year workmanship warranty, and we take kitchen remodels and commercial carpentry when that is the job in front of us. Call (647) 834-7570 for a free estimate on a Kanata or Stittsville house.',
                'Stittsville does not have its own page yet. The fence and gate we built there is listed here with the Kanata work, because the two communities sit next to each other and the same City of Ottawa permit office covers both. We are not going to spin a second town page from a single Stittsville job.',
            ],
            'services_heading' => 'Services we do in Kanata and Stittsville',
            'services_intro' => 'The headings below follow the Kanata and Stittsville projects already published. Interior finish work and outdoor carpentry both show up in this west Ottawa set.',
            'services' => [
                [
                    'title' => 'Decks and patio pads',
                    'text' => 'In Kanata we have built a floating deck with gravel around the posts and a backyard patio pad. Ground-level decks on newer west Ottawa lots often sit close to the fence line and need clean steps rather than a tall structure. We build those as carpentry: framing, decking, and stairs that match how people actually walk out of the house.',
                ],
                [
                    'title' => 'Fireplace, tile and built-ins',
                    'text' => 'The same Kanata house that received a fireplace renovation also received a hexagon tile backsplash. A separate Kanata job is a built-in shelving wall. That is finish carpentry and millwork done on site, not flat-pack boxes. We can paint-ready the woodwork or leave it for your painter; the estimate says which.',
                ],
                [
                    'title' => 'Fences in Stittsville',
                    'text' => 'The Stittsville project on this page is a backyard fence and gate. Posts, rails, and a gate that does not sag are the whole point of that job. Utility locates happen before we dig. Fence height and pool-enclosure rules are City of Ottawa by-laws, so we point you at Building Code Services instead of guessing a number in this article.',
                ],
            ],
            'projects_intro' => 'Kanata projects are listed first. The Stittsville fence is included because this page covers both communities. We do not mix in Carleton Place or Barrhaven jobs here.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Kanata and Stittsville addresses are inside the City of Ottawa. Building permits, when they are required, go through Ottawa Building Code Services. We link that office below. Whether a deck, basement, or interior renovation needs a permit depends on the work and on the city’s current rules. We will not invent a threshold on this page.',
                'West Ottawa houses in this set tend to be newer than the brick streetscapes in downtown Carleton Place. That shows up in the work: floating decks, patio pads, built-in storage walls, and fireplace updates in rooms that were finished by a builder and then restyled by the homeowner. We still scribe trim and flash ledgers the same way we do anywhere else. Fully insured carpentry does not change because the subdivision is newer.',
                'Qualified trades handle electrical and plumbing on Kanata and Stittsville renovations when those trades are part of the scope. We keep our estimate on the carpentry—framing, decks, fences, trim, built-ins, tile backsplashes we actually installed—and we say so in writing.',
            ],
            'map_heading' => 'Kanata, Stittsville and the rest of the area',
            'map_paragraphs' => [
                'The map is the Zanini Renovations Google listing, based in Carleton Place. From here, published town pages also cover Barrhaven and Carleton Place with Beckwith. Other Ottawa communities stay on the service-areas hub until they meet the same project-and-copy bar this page met.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you serve all of Kanata, and Stittsville as well?',
                    'answer' => 'Yes. This page is for Kanata and Stittsville together. We quote west Ottawa work from our Carleton Place office. If your address is in another Ottawa community, check the service-areas hub; some of those places are listed there even when they do not have a page of their own.',
                ],
                [
                    'question' => 'Who issues building permits for a Kanata or Stittsville address?',
                    'answer' => 'The City of Ottawa Building Code Services office. Send drawings or photos with your estimate request if you already know a permit is in play. We do not file permits in your name unless that is agreed in the written estimate.',
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
                'The Barrhaven jobs on this site are interior-heavy compared with the fence-heavy Carleton Place list. That is not a marketing choice. It is what we actually built: a finished basement, a window seat with storage, a custom bathroom vanity, and a backyard privacy fence with a gate.',
            ],
            'services_heading' => 'Services we do in Barrhaven',
            'services_intro' => 'Each block below ties to a published Barrhaven project. We leave Kanata decks and Carleton Place flood work on their own pages.',
            'services' => [
                [
                    'title' => 'Basement finishing',
                    'text' => 'Our Barrhaven basement finishing project covers framing through trim, including built-in storage on the main floor that belonged to the same house. Basement carpentry is a sequence: layout, framing, insulation and vapour details as specified, drywall, flooring, doors, and the millwork that makes the room usable. Qualified trades take electrical and plumbing. We do not hide that split in the quote.',
                ],
                [
                    'title' => 'Built-ins and bathroom millwork',
                    'text' => 'The built-in window storage in Barrhaven is a window seat with drawers, made to the opening rather than cut down from a catalogue bench. The custom bathroom vanity is site-built millwork for that room. Both jobs are the same kind of work we do in kitchen remodels: measure, build, install, and leave the piece sitting true on floors that are rarely perfectly flat.',
                ],
                [
                    'title' => 'Backyard fence and gate',
                    'text' => 'We also built a backyard privacy fence with a gate in Barrhaven. The photos on that project page are the Barrhaven fence, not a Carleton Place stand-in. Posts are set for frost. The gate is braced. Before we dig, we arrange utility locates. City of Ottawa fence rules sit with the city, not in a paragraph we would copy onto every town page.',
                ],
            ],
            'projects_intro' => 'Four Barrhaven projects are listed here. If a slug is not on the site yet, it will not appear as a card. We do not substitute a similar job from another town.',
            'permits_heading' => 'Permits and local notes',
            'permits_paragraphs' => [
                'Barrhaven is inside the City of Ottawa, so building permits go through Ottawa Building Code Services. Finishing a basement often needs a permit; a vanity or a window seat might not. We will not pretend those lines are the same for every house. Ask the city, or include the question when you request an estimate and we will tell you what we know for that scope.',
                'Barrhaven houses in this portfolio are suburban lots with finished or half-finished lower levels and backyards that need privacy from the next pad. That is a different problem than a 19th-century street in Carleton Place or a floating deck on a Kanata lot. The carpentry still has to be square, painted or paint-ready, and fully insured. The one-year workmanship warranty is the same.',
                'Commercial carpentry is available in this area when the scope is woodwork we perform. Most Barrhaven calls we have photographed are homes. Send photos either way. We answer in the same voice: what we will build, what qualified trades should own, and what the municipality should see before anyone opens a wall.',
            ],
            'map_heading' => 'Barrhaven and nearby service areas',
            'map_paragraphs' => [
                'The map embed is our Google Business listing in Carleton Place, with no street address. Published pages also cover Kanata and Stittsville, and Carleton Place with Beckwith. Nepean, Riverside South, and other Ottawa names without enough local projects stay on the hub as plain text.',
            ],
            'faqs' => [
                [
                    'question' => 'Do you take basement finishing and built-in work in Barrhaven?',
                    'answer' => 'Yes. The Barrhaven portfolio on this page is mostly interior: a finished basement, a window seat with storage, and a custom bathroom vanity, plus a backyard privacy fence. Send photos of the room or yard and we will tell you whether it is a carpentry job we take.',
                ],
                [
                    'question' => 'Who issues building permits in Barrhaven?',
                    'answer' => 'The City of Ottawa Building Code Services. We link their building-and-renovating page on this site. Permit need depends on the work. We do not quote code numbers or invented square-footage cutoffs here.',
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
            'intro' => 'Our office is in Carleton Place. Lanark County towns without a published page are listed here with any real project we can point to.',
            'towns' => [
                [
                    'name' => 'Carleton Place',
                    'page' => 'carleton-place',
                    'note' => 'Home base. Also covers Beckwith and Franktown.',
                ],
                [
                    'name' => 'Beckwith',
                    'note' => 'Covered on the Carleton Place page.',
                ],
                [
                    'name' => 'Franktown',
                    'note' => 'Covered on the Carleton Place page.',
                ],
                [
                    'name' => 'Almonte',
                    'project_slugs' => ['gabled-porch-roof'],
                ],
                [
                    'name' => 'Mississippi Mills',
                    'note' => 'Includes Almonte and Pakenham. Hub only until a second local project is published.',
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
                    'note' => 'The wider county, as listed on Google Business.',
                ],
                [
                    'name' => 'Arnprior',
                ],
            ],
        ],
        [
            'heading' => 'Ottawa communities',
            'intro' => 'West and south Ottawa communities we serve. Published pages are linked. Other names stay as text, with portfolio links where a project exists.',
            'towns' => [
                [
                    'name' => 'Ottawa',
                    'note' => 'The wider city, as listed on Google Business.',
                ],
                [
                    'name' => 'Kanata',
                    'page' => 'kanata',
                    'note' => 'Published as Kanata & Stittsville.',
                ],
                [
                    'name' => 'Stittsville',
                    'note' => 'Covered on the Kanata & Stittsville page.',
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
                    'note' => 'Inside Ottawa; not a separate Google Business area.',
                    'project_slugs' => ['side-walkway-backyard-patio-riverside-south'],
                ],
                [
                    'name' => 'South Keys',
                    'note' => 'Listed under Ottawa.',
                    'project_slugs' => ['catio', '2nd-floor-and-stairs-renovation'],
                ],
                [
                    'name' => 'Alta Vista',
                    'note' => 'Listed under Ottawa.',
                    'project_slugs' => ['lattice-fence'],
                ],
            ],
        ],
        [
            'heading' => 'Gatineau',
            'intro' => 'Confirmed work in Gatineau, Quebec. It is not on the Google Business service-area list, which is already at its cap.',
            'towns' => [
                [
                    'name' => 'Gatineau, QC',
                    'project_slugs' => ['basement-closet-doors-gatineau'],
                ],
            ],
        ],
    ],

];
