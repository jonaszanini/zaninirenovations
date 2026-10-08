<?php

/**
 * Public /services hub and the nine service landing pages (SEO batch 3).
 */

return [

    'cta' => [
        'heading' => 'Get a free estimate',
        'text' => 'Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
        'button_label' => 'Get a free estimate',
        'button_url' => '/contact',
        'phone_label' => 'Call (647) 834-7570',
        'phone_href' => 'tel:+16478347570',
        'email_label' => 'zaninirenovations@gmail.com',
        'email_href' => 'mailto:zaninirenovations@gmail.com',
    ],

    'hub' => [
        'title' => 'Carpentry & Renovation Services | Zanini Renovations',
        'meta_description' => 'Decks, fences, basements, trim, built-ins, stairs, flooring, drywall and kitchen remodels in Carleton Place, Lanark County and Ottawa. Fully insured.',
        'h1' => 'Carpentry and Renovation Services',
        'breadcrumb_name' => 'Services',
        'intro_paragraphs' => [
            'Zanini Renovations is a fully insured carpentry and renovation business based in Carleton Place since 2020. Owner Jonas Zanini is your point of contact from the first call to the final walkthrough.',
            'We work in homes, condos and commercial spaces across Lanark County, Ottawa and Gatineau. Open a service page for what you need done, or request a free estimate.',
        ],
    ],

    'about' => [
        'title' => 'About Jonas Zanini, Carleton Place Carpenter | Zanini',
        'meta_description' => 'Meet Jonas Zanini, owner of Zanini Renovations, a fully insured carpentry and renovation business in Carleton Place since 2020. See what we build.',
        'h1' => 'About Jonas Zanini',
        'breadcrumb_name' => 'About',
        'photo' => 'images/about-us/jonas.webp',
        'photo_alt' => 'Jonas Zanini on a job site',
    ],

    'reviews' => [
        'title' => 'Reviews | Zanini Renovations, Carleton Place Carpenter',
        'meta_description' => 'Read what clients in Carleton Place, Lanark County and Ottawa say about Zanini Renovations: 5.0 on Google from 26 reviews.',
        'h1' => 'Client Reviews',
        'breadcrumb_name' => 'Reviews',
    ],

    'contact' => [
        'title' => 'Contact & Free Estimate | Zanini Renovations',
        'meta_description' => 'Call (647) 834-7570 or send a message for a free estimate. Mon–Fri 9 AM–6 PM. Serving Carleton Place, Lanark County and Ottawa.',
        'h1' => 'Get a Free Estimate',
        'breadcrumb_name' => 'Contact',
    ],

    'pages' => [

        'decks' => [
            'published' => true,
            'slug' => 'decks',
            'nav_label' => 'Decks',
            'hub_label' => 'Deck building',
            'title' => 'Deck Builder in Carleton Place & Ottawa | Zanini Renovations',
            'meta_description' => 'Ground-level and floating decks, deck stairs, landings and railings in Carleton Place, Almonte, Stittsville and Kanata. Fully insured, since 2020.',
            'h1' => 'Deck Builder in Carleton Place, Lanark County & West Ottawa',
            'breadcrumb_name' => 'Deck building',
            'service_type' => 'Deck building',
            'fallback_image' => 'images/services/deck.webp',
            'hero_alt' => 'Floating deck with gravel around, built in Kanata',
            'hero_project_slug' => 'floating-deck-with-gravel-around',
            'cta_heading' => 'Get a free deck estimate',
            'cta_text' => 'Send us a few photos of the spot and a rough size, and we\'ll set up a visit. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Deck building FAQ',
            'faqs' => [
                [
                    'question' => 'Do I need a permit to build a deck in Carleton Place, Beckwith or Ottawa?',
                    'answer' => 'It depends on the deck\'s height, size and whether it\'s attached to the house, and each municipality sets its own rules. Check with your town\'s building department, whether that\'s the City of Ottawa, the Town of Carleton Place, Beckwith, Mississippi Mills or Perth. If you\'d like help with the permit application, we offer it as an optional add-on, quoted separately.',
                ],
                [
                    'question' => 'Which decking material holds up best to Ottawa-area winters?',
                    'answer' => 'Every option can work if it\'s built properly. Pressure-treated lumber costs the least but needs regular staining. Cedar looks great but is soft. Composite and PVC cost more upfront and need far less upkeep. Our decking showdown blog post compares all four, and we\'re happy to go through them with you at the estimate.',
                ],
                [
                    'question' => 'Can you add stairs, railings or a landing to my existing deck?',
                    'answer' => 'Yes. We regularly build stairs, landings and railings, and we can add skirting to enclose the sides of a deck. We look at the existing frame first to see whether it can carry the new work.',
                ],
                [
                    'question' => 'Do you supply the materials, or can I hire you for labour only?',
                    'answer' => 'Either. We can quote labour-only or include materials in the estimate, depending on your project. The scope and pricing are confirmed in writing before work begins.',
                ],
                [
                    'question' => 'How long does a typical deck build take?',
                    'answer' => 'It depends on size, height, materials and weather. We\'ll give you a schedule with your written quote.',
                ],
            ],
            'projects' => [
                ['slug' => 'floating-deck-with-gravel-around', 'label' => 'Floating deck with gravel around, Kanata', 'text' => 'a 290 sq ft floating deck on a treated subframe over compacted clear stone, with the underside fully enclosed.'],
                ['slug' => 'deck-stairs-landing-carleton-place', 'label' => 'Deck stairs and landing, Carleton Place', 'text' => 'a landing on posts and concrete footings off a patio door, with stairs and railings on both sides.'],
                ['slug' => 'outside-stairs', 'label' => 'Outside stairs, Carleton Place', 'text' => 'custom pressure-treated stairs with handrails from the patio up to the house.'],
                ['slug' => 'catio', 'label' => 'Catio, South Keys (Ottawa)', 'text' => 'an outdoor cat enclosure connected to the house by a covered walkway, painted to match the home.'],
            ],
            'reviews' => [
                [
                    'quote' => 'We are extremely happy with the deck that Jonas built for us! From start to finish, he was incredibly detail-oriented, professional, and reliable. ... We also asked him to enclose all the sides of the deck, which was something we decided to add after the original contract had already been agreed upon. He was very accommodating and happy to take on the extra work...',
                    'author' => 'Beatriz C.',
                ],
                [
                    'quote' => 'My deck railing looks great! Thank you Zanini!',
                    'author' => 'Luccas C.',
                ],
            ],
        ],

        'fences-gates' => [
            'published' => true,
            'slug' => 'fences-gates',
            'nav_label' => 'Fences and gates',
            'hub_label' => 'Fences and gates',
            'title' => 'Fence & Gate Installation – Carleton Place & Ottawa | Zanini',
            'meta_description' => 'Pressure-treated privacy fences, lattice screens and custom gates built to last through Ottawa winters. Carleton Place, Beckwith, Almonte and Kanata.',
            'h1' => 'Fence & Gate Installation in Carleton Place and West Ottawa',
            'breadcrumb_name' => 'Fences and gates',
            'service_type' => 'Fence and gate installation',
            'fallback_image' => 'images/services/fence.webp',
            'hero_alt' => 'Custom wood fence and gates in Carleton Place',
            'hero_project_slug' => 'side-yard-fence-gates-carleton-place',
            'cta_heading' => 'Get a free fence estimate',
            'cta_text' => 'Send a photo of the yard and a rough line for the fence, and we\'ll come out to measure. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Fence and gate FAQ',
            'faqs' => [
                [
                    'question' => 'Who is responsible for knowing where my property line is?',
                    'answer' => 'It\'s the homeowner\'s responsibility. If there\'s any doubt about where the line is, get it confirmed with a survey and talk to your neighbour before the fence is laid out.',
                ],
                [
                    'question' => 'Do you arrange utility locates before digging post holes?',
                    'answer' => 'Yes, locates are required before any digging. Call before you dig: in Ontario, utility locates are requested through Ontario One Call, and no post hole goes in until the lines are marked.',
                ],
                [
                    'question' => 'How tall can a backyard fence be in my town?',
                    'answer' => 'That\'s set by each municipality\'s fence by-law, and corner lots and pool enclosures often have their own rules. Check with the City of Ottawa or your town before we finalize the design.',
                ],
                [
                    'question' => 'How long should I wait before staining a new pressure-treated fence?',
                    'answer' => 'The wood needs to dry out first so the stain can soak in. How long that takes depends on the lumber and the weather, so we\'ll tell you what to look for once your fence is finished.',
                ],
                [
                    'question' => 'Can you build a gate that won\'t sag over time?',
                    'answer' => 'Yes. We build the gate frame square and braced, and hang it on hardware sized for its weight and width, including wide double gates.',
                ],
            ],
            'projects' => [
                ['slug' => 'backyard-privacy-fence-carleton-place', 'label' => 'Backyard privacy fence, Carleton Place', 'text' => 'solid vertical wood boards on sturdy posts, closing in a backyard and gazebo that used to open onto a shared lawn.'],
                ['slug' => 'side-yard-fence-gates-carleton-place', 'label' => 'Side-yard fence and gates, Carleton Place', 'text' => 'new fence sections on both side yards, with a lattice-top single gate and a wide double gate with a header beam.'],
                ['slug' => 'three-fences-at-once', 'label' => 'Three fences at once, Carleton Place', 'text' => 'matching 6 ft pressure-treated privacy fences for three neighbouring homes.'],
                ['slug' => 'fence-4', 'label' => 'Fence, Carleton Place', 'text' => 'a 6 ft pressure-treated wood privacy fence.'],
            ],
            'more_projects' => [
                ['slug' => 'lattice-fence', 'label' => 'Lattice fence, Alta Vista (Ottawa)', 'text' => 'a pressure-treated lattice privacy fence on posts set to resist frost heave'],
                ['slug' => 'fence-3', 'label' => 'Fence, Stittsville', 'text' => 'a pressure-treated backyard fence and gate'],
            ],
            'reviews' => [
                [
                    'quote' => 'Huge thank you to Zanini Renovations for doing an amazing job on our new fence and our neighbours! They were fast, friendly, and left our yard spotless. The quality of the materials and workmanship is top-notch.',
                    'author' => 'Tina W.',
                ],
                [
                    'quote' => 'Jonas, it\'s amazing to work with, he did a beautiful fence in our backyard for our dogs, amongst other projects in the house. Highly recommend his work, professional, prompt, and gets the job complete on a quick timeframe no loose ends.',
                    'author' => 'Mike D.',
                ],
            ],
        ],

        'basement-finishing' => [
            'published' => true,
            'slug' => 'basement-finishing',
            'nav_label' => 'Basement finishing',
            'hub_label' => 'Basement finishing',
            'title' => 'Basement Finishing – Ottawa & Carleton Place | Zanini',
            'meta_description' => 'Basement framing, drywall, flooring, trim and built-ins, plus flood-damage rebuilds, in Ottawa, Carleton Place and Lanark County. Fully insured.',
            'h1' => 'Basement Finishing in Ottawa and Carleton Place',
            'breadcrumb_name' => 'Basement finishing',
            'service_type' => 'Basement finishing',
            'fallback_image' => 'images/services/basement-renovation.webp',
            'hero_alt' => 'Finished basement family room in Barrhaven',
            'hero_project_slug' => 'basement-finishing',
            'cta_heading' => 'Get a free basement estimate',
            'cta_text' => 'Tell us roughly how big the space is and how you\'d like to use it, and send a few photos if you can. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Basement finishing FAQ',
            'faqs' => [
                [
                    'question' => 'Do I need a building permit to finish my basement?',
                    'answer' => 'Often, yes, and the rules depend on your municipality and the work involved. Ottawa residents can check the City of Ottawa\'s basement-finishing guidance. In Carleton Place, Mississippi Mills, Beckwith or Perth, check with the local building department. If you\'d like help with the permit application, we offer it as an optional add-on, quoted separately.',
                ],
                [
                    'question' => 'How long does it take to finish a basement?',
                    'answer' => 'It depends on size, condition, and how many trades and inspections are involved. We\'ll give you a schedule with your written quote.',
                ],
                [
                    'question' => 'Can you rebuild a basement after a flood?',
                    'answer' => 'Yes, once the source of the water is fixed and the space is dry. We\'ve cut out and replaced flood-damaged drywall, then reinstalled trim and new flooring, as in our Carleton Place flood restoration.',
                ],
                [
                    'question' => 'Do you handle electrical and plumbing, or do I hire those separately?',
                    'answer' => 'You don\'t need to hire them separately. We arrange qualified, certified electricians and plumbers as part of the project and coordinate their work with ours. Your written estimate sets out who does what.',
                ],
                [
                    'question' => 'Can you add a closet, built-in shelving or a home office down there?',
                    'answer' => 'Yes. Closets, bifold or barn doors, built-in shelving and desk nooks are common parts of a basement job, and we can also do them as a smaller standalone project.',
                ],
            ],
            'projects' => [
                ['slug' => 'basement-finishing', 'label' => 'Basement finishing, Barrhaven (Ottawa)', 'text' => 'a bright family room with smooth drywall, a sliding barn door, a finished egress window, built-in storage and trim.'],
                ['slug' => 'basement-flood-restoration', 'label' => 'Basement flood restoration, Carleton Place', 'text' => 'a 2 ft drywall flood cut, new door trim, baseboards and shoe moulding, and new flooring through two rooms and a hallway.'],
                ['slug' => 'basement-closet-doors-gatineau', 'label' => 'Basement closet doors, Gatineau', 'text' => 'two pairs of white six-panel bifold doors closing off a wide storage wall.'],
            ],
            'reviews' => [
                [
                    'quote' => 'I highly recommend Jonas! He was very professional, friendly and did a great job dry walling and installing flooring and trim in our basement. Communication was great and price was fair as well!',
                    'author' => 'Alana H.',
                ],
                [
                    'quote' => 'We renovated our entire house with him — he built our basement from scratch, and the drywall finishing was fantastic, always thinking about how to make the best use of every space.',
                    'author' => 'Leo B.',
                ],
            ],
        ],

        'finish-carpentry-trim' => [
            'published' => true,
            'slug' => 'finish-carpentry-trim',
            'nav_label' => 'Finish carpentry and trim',
            'hub_label' => 'Finish carpentry and trim',
            'title' => 'Finish Carpentry & Trim – Carleton Place & Ottawa | Zanini',
            'meta_description' => 'Baseboards, casings, crown moulding, wainscoting, accent walls and fireplace mantels, installed with care in Carleton Place, Kanata and Ottawa.',
            'h1' => 'Finish Carpentry & Trim in Carleton Place and Ottawa',
            'breadcrumb_name' => 'Finish carpentry and trim',
            'service_type' => 'Finish carpentry',
            'fallback_image' => 'images/services/trim.webp',
            'hero_alt' => 'Custom classic fireplace mantel in Perth',
            'hero_project_slug' => 'custom-classic-fireplace-mantel',
            'cta_heading' => 'Get a free estimate for trim or a mantel',
            'cta_text' => 'Send photos of the room and any trim you\'d like to match. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Finish carpentry FAQ',
            'faqs' => [
                [
                    'question' => 'Can you match existing trim in an older home?',
                    'answer' => 'Often, yes. Many standard profiles are still available, and we\'ll look at the closest match for your home. Bring us a short offcut, or we\'ll take a photo and measurements at the visit.',
                ],
                [
                    'question' => 'Do you paint or finish the trim after installing it?',
                    'answer' => 'We install, fill and caulk so the trim is ready for paint. We\'ll agree on painting or staining as part of the quote.',
                ],
                [
                    'question' => 'What\'s the difference between wainscoting and an accent wall?',
                    'answer' => 'Wainscoting usually covers the lower part of a wall with panels or boards and a cap rail. An accent wall is a feature treatment, such as board and batten, picture-frame moulding or slats, that can run floor to ceiling on one wall.',
                ],
                [
                    'question' => 'Can you build a custom fireplace mantel or surround?',
                    'answer' => 'Yes. We\'ve built everything from a drywall surround with a recessed niche to a full traditional mantel with corbels and dentil crown, and we build to the required clearances from the firebox.',
                ],
                [
                    'question' => 'Do you take on small trim jobs?',
                    'answer' => 'Send us photos and a short description, whether it\'s one room of baseboard or a few doors, and we\'ll let you know how we can help.',
                ],
            ],
            'projects' => [
                ['slug' => 'custom-classic-fireplace-mantel', 'label' => 'Custom classic fireplace mantel, Perth', 'text' => 'a multi-layered mantel with corbels, an arched header and dentil crown, plus a large-format tile surround and raised hearth.'],
                ['slug' => 'fireplace-surround-niche-carleton-place', 'label' => 'Fireplace surround with niche, Carleton Place', 'text' => 'a drywall bump-out with a recessed niche, a wood sill and wrapped baseboard below a floating mantel shelf.'],
                ['slug' => 'fireplace-renovation', 'label' => 'Fireplace renovation, Kanata', 'text' => 'surface prep and priming of the wood and metal, new grout and refinished surfaces.'],
                ['slug' => 'basement-flood-restoration', 'label' => 'Basement flood restoration, Carleton Place', 'text' => 'custom-fit door trim, new baseboards and shoe moulding after a flood.'],
            ],
            'more_projects' => [
                ['slug' => 'custom-shed-doors', 'label' => 'Custom shed doors, West Carleton', 'text' => 'a new 8 ft opening with a header and reinforced framing'],
            ],
            'reviews' => [
                [
                    'quote' => 'On the upper floor, Jonas covered our fireplace with beautiful finishes and installed stunning handmade shelves with excellent craftsmanship. In the upstairs hallway, he added frames and lighting combined with dark paint, giving the house a very elegant look.',
                    'author' => 'Leo B.',
                ],
                [
                    'quote' => 'Jonas completed some custom projects for me and they turned out beautifully. Excellent craftsmanship, attention to detail and turnaround times. I highly recommend him!',
                    'author' => 'PJ',
                ],
            ],
        ],

        'custom-built-ins-closets' => [
            'published' => true,
            'slug' => 'custom-built-ins-closets',
            'nav_label' => 'Custom built-ins and closets',
            'hub_label' => 'Custom built-ins and closets',
            'title' => 'Custom Built-ins & Closets – Carleton Place | Zanini',
            'meta_description' => 'Built-in shelving, window seats, closets and storage walls designed and built to fit your space. Carleton Place, Beckwith, Kanata and Ottawa.',
            'h1' => 'Custom Built-ins, Shelving & Closets',
            'breadcrumb_name' => 'Custom built-ins and closets',
            'service_type' => 'Custom built-ins and closets',
            'fallback_image' => 'images/services/shelves.webp',
            'hero_alt' => 'Built-in shelving wall in Kanata',
            'hero_project_slug' => 'built-in-shelving-wall-kanata',
            'cta_heading' => 'Get a free built-in estimate',
            'cta_text' => 'Send us a photo of the space, rough measurements and a picture of something you like. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Built-ins and closets FAQ',
            'faqs' => [
                [
                    'question' => 'Do you design the built-in, or do I need drawings?',
                    'answer' => 'You don\'t need drawings. We measure the space, talk through how you\'ll use it and work from photos of designs you like, then confirm the layout with you before building.',
                ],
                [
                    'question' => 'What materials do you use for shelving and cabinet boxes?',
                    'answer' => 'It depends on the piece, how it will be used and the finish you want. We\'ll walk you through the material options when we plan the design.',
                ],
                [
                    'question' => 'Can you replace cabinet doors without replacing the cabinets?',
                    'answer' => 'Yes, as long as the existing boxes are in good shape. In a Nepean kitchen we built 16 new flat-style doors for the existing cabinets, along with new drawers and soft-close hardware.',
                ],
                [
                    'question' => 'Can you build a closet in a basement or unfinished room?',
                    'answer' => 'Yes. We can frame and drywall a new closet, add shelving, and hang bifold, barn or double doors, either as part of a basement finish or on its own.',
                ],
                [
                    'question' => 'Can you build from a photo I found online?',
                    'answer' => 'Yes. An inspiration photo is a great starting point. We\'ll measure your space and adapt the design so it fits the room and the way you\'ll use it.',
                ],
            ],
            'projects' => [
                ['slug' => 'built-in-shelving-wall-kanata', 'label' => 'Built-in shelving wall, Kanata', 'text' => 'a full-wall white unit with open shelves, panelled lower cabinets and crown moulding.'],
                ['slug' => 'built-in-window-storage', 'label' => 'Built-in window storage, Barrhaven (Ottawa)', 'text' => 'a window bench with three drawers and recessed panels, fitted into an alcove.'],
                ['slug' => 'custom-bathroom-vanity-barrhaven', 'label' => 'Custom bathroom vanity, Barrhaven', 'text' => 'a stained wood vanity built in place of a pedestal sink.'],
                ['slug' => 'basement-finishing', 'label' => 'Basement finishing, Barrhaven', 'text' => 'built-in storage, including a corner desk with wood shelving, in a finished basement.'],
            ],
            'reviews' => [
                [
                    'quote' => 'Job well done, easy to work with and was able to do a custom design from an inspiration picture. Very reasonable and made sure I was happy with the result.',
                    'author' => 'Jennifer A.',
                ],
                [
                    'quote' => 'Highly recommend Zanini Renovations for custom carpentry and office furniture. Jonas Zanini built a custom office desk at my workplace, and the quality and attention to detail were excellent.',
                    'author' => 'Kelly C.F.',
                ],
            ],
        ],

        'staircases' => [
            'published' => true,
            'slug' => 'staircases',
            'nav_label' => 'Staircases',
            'hub_label' => 'Staircases',
            'title' => 'Stair Renovations & Custom Stairs – Carleton Place | Zanini',
            'meta_description' => 'Carpet-to-hardwood stair makeovers, new treads and nosing, and exterior stairs and landings. Carleton Place, Kanata, Stittsville and Ottawa.',
            'h1' => 'Staircase Renovations & Exterior Stairs',
            'breadcrumb_name' => 'Staircases',
            'service_type' => 'Staircase renovation',
            'fallback_image' => 'images/services/stairs.webp',
            'hero_alt' => 'Finished interior staircase after a carpet-to-wood makeover',
            'hero_project_slug' => '2nd-floor-and-stairs-renovation',
            'cta_heading' => 'Get a free stair estimate',
            'cta_text' => 'Send a photo of your staircase, or of the spot where you need outdoor stairs. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Staircase FAQ',
            'faqs' => [
                [
                    'question' => 'Can you replace carpeted stairs with hardwood treads?',
                    'answer' => 'Yes. We remove the carpet and finish the stairs with new treads, risers and nosing chosen to match your floors.',
                ],
                [
                    'question' => 'Do you refinish existing treads or replace them?',
                    'answer' => 'It depends on their condition. Sometimes the existing treads are worth keeping, and sometimes new treads are the better choice. We\'ll look at them together at the estimate.',
                ],
                [
                    'question' => 'Can I use the stairs during the work?',
                    'answer' => 'We\'ll plan access before the work starts, so you know how you\'ll get up and down. Your schedule comes with the quote.',
                ],
                [
                    'question' => 'Can you build new exterior stairs or a landing for my deck or porch?',
                    'answer' => 'Yes. We build pressure-treated exterior stairs and landings with handrails or railings, on footings or a level base, for decks, patios and back doors.',
                ],
                [
                    'question' => 'Do you install railings and handrails?',
                    'answer' => 'On exterior stairs, yes: handrails and railings with balusters are part of the build. For interior stairs, we\'ll go over railing options at the estimate.',
                ],
            ],
            'projects' => [
                ['slug' => '2nd-floor-and-stairs-renovation', 'label' => '2nd floor and stairs renovation, South Keys (Ottawa)', 'text' => 'carpet removed from the second floor and staircase, with new flooring installed throughout.'],
                ['slug' => 'outside-stairs', 'label' => 'Outside stairs, Carleton Place', 'text' => 'custom pressure-treated stairs with precision-cut stringers and secure handrails, from the patio to the house.'],
                ['slug' => 'deck-stairs-landing-carleton-place', 'label' => 'Deck stairs and landing, Carleton Place', 'text' => 'a landing on concrete footings with stairs and railings on both sides.'],
            ],
            'reviews' => [
                [
                    'quote' => 'The installation of the flooring on the second floor of my house was excellent, and the stairs were finished beautifully. The quality of the workmanship exceeded my expectations, and everything was completed with great care and precision.',
                    'author' => 'Antonio S.',
                ],
            ],
        ],

        'flooring-installation' => [
            'published' => true,
            'slug' => 'flooring-installation',
            'nav_label' => 'Flooring installation',
            'hub_label' => 'Flooring installation',
            'title' => 'Flooring Installation – Carleton Place & Ottawa | Zanini',
            'meta_description' => 'Hardwood, engineered wood, vinyl, laminate and tile floors installed with careful prep, transitions and trim. Carleton Place, Kanata and Ottawa.',
            'h1' => 'Flooring Installation in Carleton Place and Ottawa',
            'breadcrumb_name' => 'Flooring installation',
            'service_type' => 'Flooring installation',
            'fallback_image' => 'images/services/flooring.webp',
            'hero_alt' => 'Engineered hardwood flooring installation',
            'hero_project_slug' => 'engineering-hard-wood-flooring-installation',
            'cta_heading' => 'Get a free flooring estimate',
            'cta_text' => 'Tell us the rooms, the rough square footage and what\'s on the floor now. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Flooring FAQ',
            'faqs' => [
                [
                    'question' => 'Do you supply the flooring, or can I buy my own?',
                    'answer' => 'Either works. You can buy your own flooring and hire us to install it, or we can include materials in the estimate.',
                ],
                [
                    'question' => 'Can you install flooring in a basement?',
                    'answer' => 'Yes. We\'ve installed new flooring in finished basements and in basements rebuilt after a flood. The right product depends on the slab and the moisture conditions, so we\'ll look at those first and go over the options. Vinyl plank, engineered wood, laminate and tile are common basement choices because they handle moisture and temperature changes better than solid hardwood.',
                ],
                [
                    'question' => 'Do you remove and dispose of the old flooring?',
                    'answer' => 'Removing the old flooring is part of the prep, and the estimate spells out what\'s included, so there\'s no guesswork about the old material.',
                ],
                [
                    'question' => 'Does engineered hardwood need to acclimate?',
                    'answer' => 'Most products do. Follow the flooring manufacturer\'s instructions, since they vary between products, and we\'ll plan the install around them.',
                ],
                [
                    'question' => 'Do you reinstall or replace baseboards after the new floor goes in?',
                    'answer' => 'Yes. Baseboards, shoe moulding, transitions and stair nosing are part of how we finish a floor, whether we reinstall the existing trim or put in new.',
                ],
            ],
            'projects' => [
                ['slug' => 'engineering-hard-wood-flooring-installation', 'label' => 'Engineered hardwood flooring, Ashton area (rural Ottawa)', 'text' => 'engineered hardwood across a 240 sq ft room, with flush-mount vents and clean transitions.'],
                ['slug' => '2nd-floor-and-stairs-renovation', 'label' => '2nd floor and stairs renovation, South Keys (Ottawa)', 'text' => 'carpet removed from the second floor and stairs, with new flooring installed throughout.'],
                ['slug' => 'basement-flood-restoration', 'label' => 'Basement flood restoration, Carleton Place', 'text' => 'new flooring through two rooms and a hallway after a flood, with new baseboards and shoe moulding.'],
                ['slug' => 'basement-finishing', 'label' => 'Basement finishing, Barrhaven', 'text' => 'flooring as part of a full basement finish.'],
            ],
            'reviews' => [
                [
                    'quote' => 'Jonas from Zanini Renovations did an excellent job on our flooring project. He was professional, easy to work with, and delivered outstanding workmanship with great attention to detail, even though the job was relatively small.',
                    'author' => 'Robert W.',
                ],
                [
                    'quote' => 'I am very happy with the work done by Zanini Renovations. ... The installation of the flooring on the second floor of my house was excellent...',
                    'author' => 'Antonio S.',
                ],
            ],
        ],

        'drywall' => [
            'published' => true,
            'slug' => 'drywall',
            'nav_label' => 'Drywall',
            'hub_label' => 'Drywall',
            'title' => 'Drywall Installation & Repair – Carleton Place | Zanini',
            'meta_description' => 'Drywall hanging, taping and finishing for basements, renovations and flood repairs in Carleton Place, Beckwith, Kanata and Stittsville. Free estimates.',
            'h1' => 'Drywall Installation, Taping & Repair',
            'breadcrumb_name' => 'Drywall',
            'service_type' => 'Drywall installation and repair',
            'fallback_image' => 'images/services/basement-renovation.webp',
            'hero_alt' => 'Drywall installation and finishing in progress',
            'hero_project_slug' => 'basement-flood-restoration',
            'cta_heading' => 'Get a free drywall estimate',
            'cta_text' => 'Send a few photos and the rough size of the area. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Drywall FAQ',
            'faqs' => [
                [
                    'question' => 'Do you take small drywall repair jobs?',
                    'answer' => 'Often, yes. Send photos and the size of the area, and we\'ll let you know whether it\'s a fit.',
                ],
                [
                    'question' => 'Can you repair drywall after a leak or flood?',
                    'answer' => 'Yes, once the source of the water is fixed and the area has dried. We cut out the damaged drywall, install new board, and tape and sand it to blend with the existing walls. That\'s what we did in a Carleton Place basement with a 2 foot flood cut through two rooms and a hallway.',
                ],
                [
                    'question' => 'Do you prime and paint after taping?',
                    'answer' => 'Let us know if you\'d like priming and painting, and we\'ll cover it when we go over the quote.',
                ],
                [
                    'question' => 'How do you keep dust under control?',
                    'answer' => 'Before the work starts, we\'ll go over how the area will be closed off and cleaned up, and we clean up at the end of the job.',
                ],
                [
                    'question' => 'Why does drywall take several visits?',
                    'answer' => 'Each coat of compound needs time to dry before the next one, so drywall takes several visits rather than one long day. Your schedule comes with the quote.',
                ],
            ],
            'projects' => [
                ['slug' => 'basement-flood-restoration', 'label' => 'Basement flood restoration, Carleton Place', 'text' => 'a 2 ft flood cut of new drywall through two rooms and a hallway, taped and sanded to blend with the upper walls.'],
                ['slug' => 'fireplace-surround-niche-carleton-place', 'label' => 'Fireplace surround with niche, Carleton Place', 'text' => 'a drywall bump-out with a recessed niche around a living room fireplace.'],
                ['slug' => 'basement-finishing', 'label' => 'Basement finishing, Barrhaven (Ottawa)', 'text' => 'smooth drywall throughout a finished family room.'],
                ['slug' => 'custom-classic-fireplace-mantel', 'label' => 'Custom classic fireplace mantel, Perth', 'text' => 'drywall taping, mudding and sanding to prepare the wall for the mantel and paint.'],
            ],
            'reviews' => [
                [
                    'quote' => '...he built our basement from scratch, and the drywall finishing was fantastic...',
                    'author' => 'Leo B.',
                ],
                [
                    'quote' => 'We especially appreciated how organized and clean he was throughout the entire process.',
                    'author' => 'Kawana B.',
                ],
            ],
        ],

        'kitchen-remodel' => [
            'published' => true,
            'slug' => 'kitchen-remodel',
            'nav_label' => 'Kitchen remodel',
            'hub_label' => 'Kitchen remodel',
            'title' => 'Kitchen Remodel – Carleton Place & Ottawa | Zanini',
            'meta_description' => 'Custom cabinet doors, countertops, backsplash tile, built-ins and trim for kitchen remodels in Carleton Place, Kanata, Nepean and Ottawa. Fully insured.',
            'h1' => 'Kitchen Remodels & Updates in Carleton Place and Ottawa',
            'breadcrumb_name' => 'Kitchen remodel',
            'service_type' => 'Kitchen remodeling',
            'fallback_image' => 'images/services/tiles.webp',
            'hero_alt' => 'Kitchen remodel with custom cabinet doors and backsplash',
            'hero_project_slug' => 'kitchen-renovations',
            'cta_heading' => 'Get a free kitchen estimate',
            'cta_text' => 'Send us photos of your kitchen as it is now, and a picture of the look you want if you have one. Monday to Friday, 9 AM to 6 PM. Fully insured ($3M) · in business since 2020 · based in Carleton Place.',
            'faqs_heading' => 'Kitchen remodel FAQ',
            'faqs' => [
                [
                    'question' => 'Can you update my kitchen without replacing the cabinets?',
                    'answer' => 'Yes, if the cabinet boxes are in good shape. New custom doors, drawer fronts, hardware, countertops and a backsplash can transform a kitchen while keeping the existing layout. In a Nepean kitchen we built 16 new doors and four drawers for the existing cabinets.',
                ],
                [
                    'question' => 'Do you install countertops?',
                    'answer' => 'Yes. We\'ve templated and installed countertops, including a two-tiered breakfast bar. We\'ll go over material options with you; some materials are fabricated by a supplier.',
                ],
                [
                    'question' => 'Do you do the plumbing and electrical in a kitchen?',
                    'answer' => 'We arrange qualified, certified electricians and plumbers as part of the project and coordinate their work with ours. Gas work isn\'t included. Your written estimate sets out who does what.',
                ],
                [
                    'question' => 'Do you install tile backsplashes?',
                    'answer' => 'Yes. We installed a white hexagon tile backsplash in a Kanata kitchen, with precise cuts around outlets and corners.',
                ],
                [
                    'question' => 'How long will my kitchen be out of use?',
                    'answer' => 'It depends on the scope and on material lead times. We\'ll give you a schedule with your written quote.',
                ],
            ],
            'projects' => [
                ['slug' => 'kitchen-renovations', 'label' => 'Kitchen renovations, Nepean (Ottawa)', 'text' => '16 custom white cabinet doors, four custom drawers with soft-close hardware, dark matte countertops with a two-tiered breakfast bar, and a new undermount sink and faucet.'],
                ['slug' => 'backsplash-installation', 'label' => 'Backsplash installation, Kanata', 'text' => 'a white hexagon tile backsplash with precise cuts around outlets and consistent grout lines.'],
                ['slug' => 'built-in-shelving-wall-kanata', 'label' => 'Built-in shelving wall, Kanata', 'text' => 'a full-wall built-in in the same home as the kitchen update.'],
            ],
            'reviews' => [
                [
                    'quote' => 'Jonas responded to my enquires and came to site really quickly, delivering an excellent service for a custom kitchen renovation. Medium project, but one that would have taken me a lot longer!. Thanks Jonas!. Highly recommended.',
                    'author' => 'Marcelo S.',
                ],
                [
                    'quote' => 'I had a great experience with Jonas. They did an excellent job at my kitchen and living room, very professional and all was delivered on time.',
                    'author' => 'Douglas O.S.',
                ],
            ],
        ],

    ],

];
