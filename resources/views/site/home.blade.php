@extends('site.layouts.public')

@section('title', 'Licensed Carpenter Ottawa | O.Reg. 332/12 | Zanini Renovations')
@section('meta_description', 'Licensed Carpenter Ottawa & Carleton Place — finish carpentry, rough carpentry, custom millwork, decks, basements. WSIB Clearance Certificate, Part 9 Housing & Small Buildings, O.Reg. 332/12 compliant. CAD$ quotes Kanata, Barrhaven, Orléans.')
@section('meta_keywords', config('seo.lsi_verbatim'))
@section('canonical', url('/'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::homeGraph()) !!}</script>
@endpush

@section('content')

    @php
      $socialFacebookUrl = (string) config('social.facebook_url');
      $socialInstagramUrl = (string) config('social.instagram_url');
      $socialWhatsappUrl = (string) config('social.whatsapp_url');
    @endphp

    <!--  HOME SLIDER BLOCK  -->

    <div id="home" class="slider-wrap">
      <div id="slider_1" class="owl-carousel" data-nav="true" data-dots="true" data-autoplay="false" data-autoplaytimeout="10000" data-bg_effect="true">

        <div class="slider_item_container" data-bg_img="images/home_1_slider_1.webp" data-bg_color="#111111" data-bg_opacity="0.5">
          <div class="item">
            <div class="slider-content">
              <div class="container text-left">
                <div class="row">
                  <div class="slider-bg" data-animation-in="fadeInUp" data-animation-out="zoomInDown">
                    <div class="col-sm-12 wow fadeInDown" data-wow-duration="1s">
                      <h3>Decks & Outdoor Living</h3>
                      <h2 class="margin-bottom-12">
                        QUALITY DECKS
                        <br>
                        MAKE HOMES BEAUTIFUL
                      </h2>
                      <a href="#contact-location" class="btn btn-theme nav_menu">Get Free Estimate</a>
                      <a href="#services" class="btn btn-theme btn-theme-white nav_menu">Learn More</a>
                    </div>
                  </div>
                  <!-- end .col-sm-12  -->
                </div>
                <!-- end .row  -->
              </div>
              <!-- end .container -->
            </div>
            <!--  end .slider-content -->
          </div>
          <!-- end .item  -->
        </div>
        <!-- end .slider_item_container  -->

        <div class="slider_item_container" data-bg_img="images/home_1_slider_2.webp" data-bg_color="#111111" data-bg_opacity="0.5">
          <div class="item">
            <div class="slider-content">
              <div class="container text-left">
                <div class="row">
                  <div class="slider-bg" data-animation-in="fadeInLeft" data-animation-out="zoomInDown">
                    <div class="col-sm-12 wow fadeInLeft" data-wow-duration="1s">
                      <h2 class="margin-bottom-12" data-animation-in="zoomIn" data-animation-out="animate-out fadeOutDown">
                        PERFECT TRIMMING
                        <br>
                        BRINGS ELEGANCE HOME
                      </h2>
                      <h3 class="margin-bottom-24" data-animation-in="zoomIn" data-animation-out="animate-out rollOut">Elevate Your Interior With Perfect Trimming Details</h3>

                      <a href="#services" class="btn btn-theme nav_menu" data-animation-in="zoomInRight" data-animation-out="animate-out fadeOutRight">Learn More</a>
                      <a href="#contact-location" class="btn btn-theme btn-theme-white nav_menu" data-animation-in="zoomInLeft" data-animation-out="animate-out fadeOutRight">Get Free Estimate</a>


                    </div>
                  </div>
                  <!-- end .col-sm-12  -->
                </div>
                <!-- end .row  -->
              </div>
              <!-- end .container -->
            </div>
            <!--  end .slider-content -->
          </div>
          <!-- end .item  -->
        </div>
        <!-- end .slider_item_container  -->

        <div class="slider_item_container" data-bg_img="images/home_1_slider_3.webp" data-bg_color="#111111" data-bg_opacity="0.5">
          <div class="item">
            <div class="slider-content">
              <div class="container text-center">
                <div class="row">
                  <div class="slider-bg" data-animation-in="fadeInDown" data-animation-out="zoomInDown">
                    <div class="col-sm-12 wow fadeInLeft" data-wow-duration="1s">
                      <h2 class="margin-bottom-12">
                        STUNNING BASEMENTS
                        <br>
                        CREATE LIVING SPACE
                      </h2>
                      <h3 class="margin-bottom-24">
                        Transform Your Basement With Expert Finishing Craftsmanship
                      </h3>
                      <a href="#contact-location" class="btn btn-theme btn-theme-white nav_menu">Get Free Estimate</a>
                      <a href="#services" class="btn btn-theme nav_menu">Learn More</a>

                    </div>
                  </div>
                  <!-- end .col-sm-12  -->
                </div>
                <!-- end .row  -->
              </div>
              <!-- end .container -->
            </div>
            <!--  end .slider-content -->
          </div>
          <!-- end .item  -->
        </div>
        <!-- end .slider_item_container  -->

      </div>
      <!-- end .slider_1  -->
    </div>
    <!-- end .slider-wrap.  -->

    <!--  CORE SERVICES  -->

    <section id="services" class="section-content-block section-core-services">

      <div class="container">

        <div class="row section-heading-wrapper">

          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-dark-color">Services</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h2 class="subheading-alt-style">
              Finishing, structural, and outdoor work — crafted with care so your home looks sharp and holds up for years.
            </h2>
          </div>
          <!-- end .col-sm-12  -->

        </div>

        <div class="row">

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/fence.webp" alt="Residential Fencing Services Ottawa; Barrhaven Deck and Fence Builders; WSIB Clearance Certificate; General Liability Insurance $2M" loading="lazy" decoding="async">
                <span class="fa fa-bars"></span>
              </figure>

              <h3>Fencing</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/deck.webp" alt="Professional Deck Builder Ottawa; Timber Frame Porch Construction; Sheathing and House Wrap; O.Reg. 332/12 compliant" loading="lazy" decoding="async">
                <span class="fa fa-tree"></span>
              </figure>

              <h3>Decks</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/basement-renovation.webp" alt="Basement Framing Contractors; Kanata Basement Framing; Legal basement apartment conversion; Sub-floor depressurization rough-in (A 2026 OBC requirement for radon)" loading="lazy" decoding="async">
                <span class="fa fa-home"></span>
              </figure>

              <h3>Basement Renovation</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/trim.webp" alt="Interior Trim Installation Ottawa; Crown Moulding Installation; Baseboard and Casing Replacement; Coped Joints and Miters; Hintonburg Modern Trim Design" loading="lazy" decoding="async">
                <span class="fa fa-wrench"></span>
              </figure>

              <h3>Trim &amp; Moulding</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/shelves.webp" alt="Custom Built-in Shelving Ottawa; Floating Shelf Installation; Zero-clearance Built-ins; Scribe-to-wall fitting; Concealed hinge installation" loading="lazy" decoding="async">
                <span class="fa fa-cubes"></span>
              </figure>

              <h3>Shelving</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/flooring.webp" alt="Hardwood Flooring Installation Ottawa; Riverside South Flooring Installers; Joist bridging and blocking; Furniture-grade finishing" loading="lazy" decoding="async">
                <span class="fa fa-th-large"></span>
              </figure>

              <h3>Flooring</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/tiles.webp" alt="Drywall Installation and Taping; Soundproof Drywall Installation for Offices; Fire-resistance ratings (FRR)" loading="lazy" decoding="async">
                <span class="fa fa-th"></span>
              </figure>

              <h3>Tile</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/accent-walls.webp" alt="Wainscoting and Wall Panelling; MDF &amp; Solid Wood Profiling; Modern Fireplace Surround Carpentry; Architectural Woodworking" loading="lazy" decoding="async">
                <span class="fa fa-paint-brush"></span>
              </figure>

              <h3>Accent Walls</h3>

            </article>
          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

          <div class="col-md-4 col-sm-10 col-xs-10 col-md-offset-0 col-sm-offset-1 col-xs-offset-1">

            <article class="service-block-2 text-center">
              <figure>
                <img src="images/services/stairs.webp" alt="Wood Staircase Refinishing; Under-Stairs Storage Solutions Ottawa; Structural load-bearing verification; LVL Beam Installation (Laminated Veneer Lumber)" loading="lazy" decoding="async">
                <span class="fa fa-sort-amount-asc"></span>
              </figure>

              <h3>Stairs</h3>

            </article>

          </div>
          <!--  end .col-lg-4 col-md-4 col-sm-12  -->

        </div>
        <!--  end .row  -->

      </div>
      <!--  end .container  -->

    </section>
    <!--  end .section-content-block -->

    <!--  SECTION CTA 04 -->

    <section id="cta" class="cta-layout-02 section-primary-bg">
      <div class="container">
        <div class="row">
          <div class="col-lg-9 col-md-9 col-sm-12 col-xs-12">
            <h2 class="text-light-color">
              Do you have any questions ? Feel free to contact.
            </h2>
            <p class="text-light-color">

              Client oriented, long experienced and professional builders are ready
                                                                                                                                                          to get you built your dream project.

            </p>
          </div>
          <!--  end .col-md-8  -->
          <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 text-center">
            <a class="btn btn-theme btn-square btn-theme-invert wow bounceIn nav_menu" href="#contact-location">CONTACT US</a>
          </div>
          <!--  end .col-md-4  -->
        </div>
        <!--  end .row  -->
      </div>
    </section>

    <!--  PORTFOLIO CONTENT  -->

    <section id="projects" class="section-content-block section-portfolio-bg">

      <div class="container">

        <div class="row section-heading-wrapper">

          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-capitalize text-dark-color">LATEST PROJECTS</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h2 class="subheading-alt-style">
              A selection of our recent carpentry and renovation work across the Ottawa region.
            </h2>
          </div>
          <!-- end .col-sm-12  -->

        </div>

        @if ($projects->isNotEmpty() && $projectFilterCategories->isNotEmpty())
        <div class="row latest-projects-filter-row wow fadeInUp">
          <div class="col-md-12 col-sm-12 col-xs-12 text-center">
            <div class="latest-projects-filters" role="group" aria-label="Filter projects by category">
              <button type="button" class="latest-project-filter-btn is-active" data-filter="all" aria-pressed="true">
                All
              </button>
              @foreach ($projectFilterCategories as $cat)
                <button type="button" class="latest-project-filter-btn" data-filter="{{ $cat->slug }}" aria-pressed="false">
                  {{ $cat->name }}
                </button>
              @endforeach
            </div>
          </div>
        </div>
        @endif

        <div class="row wow fadeInUp">
          @include('site.partials.latest-projects', ['projects' => $projects])
        </div>
        <!-- end .row  -->

      </div>
      <!-- end .container -->

    </section>
    <!-- end .section-content-block  -->

    <!-- Latest projects: gallery modal (Bootstrap modal + lightweight CSS track carousel) -->
    <div class="modal fade project-gallery-modal" id="latestProjectGalleryModal" tabindex="-1" role="dialog" aria-labelledby="latestProjectGalleryModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header project-gallery-modal-header">
            <button type="button" class="close project-gallery-close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title" id="latestProjectGalleryModalLabel"></h4>
          </div>
          <div class="modal-body project-gallery-modal-body">
            <div class="project-gallery-viewport">
              <div class="project-gallery-track" id="projectGalleryTrack"></div>
            </div>
            <button type="button" class="project-gallery-nav project-gallery-nav-prev" id="projectGalleryPrev" aria-label="Previous photo">
              <svg class="project-gallery-nav-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
            </button>
            <button type="button" class="project-gallery-nav project-gallery-nav-next" id="projectGalleryNext" aria-label="Next photo">
              <svg class="project-gallery-nav-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
            </button>
            <ul class="project-gallery-dots" id="projectGalleryDots"></ul>
          </div>
        </div>
      </div>
    </div>

    <!-- TEAM SECTION 01 -->

    <section id="about" class="section-content-block section-secondary-bg">

      <div class="container">

        <div class="row">
          <div class="col-md-4 col-sm-12">

            <div class="row section-heading-wrapper">

              <div class="col-md-12 col-sm-12 text-left">
                <h4 class="heading-alt-style text-uppercase text-dark-color">About us</h4>
                <span class="heading-separator heading-separator-horizontal"></span>
                <h2 class="subheading-alt-style">
                  Hi, I am Jonas Zanini. After 20 years in IT with global leaders like IBM and Infosys, I moved from Brazil to Ontario in 2019 and discovered a true passion for construction. At Zanini Renovations, I apply that same software developer precision to every carpentry and renovation project I build. My goal is to deliver high quality craftsmanship that makes you and your family fall in love with your home all over again.

          
                </h2>
                <a href="#about" class="btn btn-theme margin-top-24 nav_menu">More about us</a>
              </div>
              <!-- end .col-sm-12  -->

            </div>

          </div>
          <div class="col-md-8 col-sm-12">
            <div class="row">

              <div class="team-carousel owl-carousel" data-items="2">

                <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">

                  <div class="team-layout-1 text-center">

                    <figure class="team-member">
                      <a href="#" title="Jonas Zanini">
                        <img src="images/about-us/jonas.webp" alt="Jonas Zanini — Red Seal Certified Carpenter; Licensed Carpenter Ottawa; Form 1000 Compliance (Construction safety specific)" loading="lazy" decoding="async">
                      </a>
                    </figure>
                    <!-- end. team-member  -->

                    <article class="team-info">

                      <h3>Jonas Zanini</h3>
                      <h4>Owner & Contractor</h4>

                      <div class="team-social-share clearfix">
                        <a href="{{ $socialFacebookUrl }}"@if (str_starts_with($socialFacebookUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif title="Facebook">
                          <i class="fa fa-facebook rectangle"></i>
                        </a>
                        <a href="#" title="Twitter">
                          <i class="fa fa-twitter rectangle"></i>
                        </a>
                        <a href="{{ $socialInstagramUrl }}"@if (str_starts_with($socialInstagramUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif title="Instagram">
                          <i class="fa fa-instagram rectangle"></i>
                        </a>
                        <a href="#" title="Linkedin">
                          <i class="fa fa-linkedin rectangle"></i>
                        </a>
                      </div>
                      <!-- end .author-social-box  -->

                    </article>
                  </div>
                  <!--  end team layout-1 -->

                </div>
                <!--  end .col-lg-3 col-md-3 col-sm-6 col-xs-12 -->

                <div class="col-md-3 col-sm-6 col-xs-10 col-xs-offset-1 col-sm-offset-0 col-md-offset-0">

                  <div class="team-layout-1 text-center">

                    <figure class="team-member">
                      <a href="#" title="Mayara Zanini">
                        <img src="images/about-us/mayara.webp" alt="Mayara Zanini — Zanini Renovations office; Carleton Place Rough Carpentry scheduling; WSIB Clearance Certificate inquiries" loading="lazy" decoding="async">
                      </a>
                    </figure>
                    <!-- end. team-member  -->

                    <article class="team-info">
                      <h3>Mayara Zanini</h3>
                      <h4>Office & Marketing</h4>
                      <div class="team-social-share clearfix">
                        <a href="{{ $socialFacebookUrl }}"@if (str_starts_with($socialFacebookUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif title="Facebook">
                          <i class="fa fa-facebook rectangle"></i>
                        </a>
                        <a href="#" title="Twitter">
                          <i class="fa fa-twitter rectangle"></i>
                        </a>
                        <a href="{{ $socialInstagramUrl }}"@if (str_starts_with($socialInstagramUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif title="Instagram">
                          <i class="fa fa-instagram rectangle"></i>
                        </a>
                        <a href="#" title="Linkedin">
                          <i class="fa fa-linkedin rectangle"></i>
                        </a>
                      </div>
                      <!-- end .author-social-box  -->

                    </article>
                  </div>
                  <!--  end team-layout-1 -->

                </div>
                <!--  end .col-lg-3 col-md-3 col-sm-6 col-xs-12  -->

              </div>
            </div>
            <!-- end .row  -->
          </div>
        </div>


      </div>
      <!-- end .container  -->

    </section>
    <!--  end .section-our-team -->

    <!-- SECTION TESTIMONIAL  03 -->

    <section id="testimonials" class="section-content-block ">

      <div class="container">
        <div class="row section-heading-wrapper">

          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-dark-color">Our Testimonials</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h2 class="subheading-alt-style">
              What homeowners say about working with Zanini Renovations—sourced from real Google reviews.
            </h2>
          </div>
          <!-- end .col-sm-12  -->

        </div>
      </div>

      <div class="container theme-custom-box-shadow section-secondary-bg">

        <div class="row">

          <div class="col-lg-6 col-md-12 col-sm-12">

            <div class="testimonial-container testimonial-carousel--nav owl-carousel text-left wow fadeInLeft" data-items="1" data-nav="true" data-dots="true">

              <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">

                <div class="testimony-layout-1 transparent-bg">

                  <p class="testimony-text">
                    I highly recommend Jonas! He was very professional, friendly and did a great job dry walling and installing flooring and trim in our basement. Communication was great and price was fair as well!
                  </p>

                  <div class="testimony-info testimony-info--with-avatar">
                    <div class="img-responsive testimony-avatar-initial" aria-hidden="true">A</div>
                    <h4>Alana Hamilton</h4>
                    <h6 class="bq-author-info">See this review on <a href="https://maps.app.goo.gl/yWkw5fWBoC9QgCpi8" target="_blank" rel="noopener noreferrer">Google</a></h6>
                  </div>
                </div>
                <!-- end .testimony-layout-1  -->

              </div>

              <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">

                <div class="testimony-layout-1 transparent-bg">
                  <p class="testimony-text">
                    Jonas was an excellent professional for a project we had in our home. From the very beginning, he was attentive to our needs, always available to answer questions. We especially appreciated how organized and clean he was throughout the entire process. We are happy with the results and will definitely work with him again in the future. We highly recommend his services.
                  </p>
                  <div class="testimony-info testimony-info--with-avatar">
                    <div class="img-responsive testimony-avatar-initial" aria-hidden="true">K</div>
                    <h4>Kawana Bazzo</h4>
                    <h6 class="bq-author-info">See this review on <a href="https://maps.app.goo.gl/VRXkNr2AFfkuuRBr8" target="_blank" rel="noopener noreferrer">Google</a></h6>
                  </div>
                </div>
                <!-- end .testimony-layout-1  -->

              </div>

              <div class="col-lg-4 col-md-4 col-sm-4 col-xs-12">

                <div class="testimony-layout-1 transparent-bg">
                  <p class="testimony-text">
                    Jonas, it&rsquo;s amazing to work with, he did a beautiful fence in our backyard for our dogs, amongst other projects in the house. Highly recommend his work, professional, prompt, and gets the job complete on a quick timeframe no loose ends. Highly recommend recommended.!!
                  </p>
                  <div class="testimony-info testimony-info--with-avatar">
                    <div class="img-responsive testimony-avatar-initial" aria-hidden="true">M</div>
                    <h4>Mike Dobson</h4>
                    <h6 class="bq-author-info">See this review on <a href="https://maps.app.goo.gl/h9NqsHKsLCRz6Gcb8" target="_blank" rel="noopener noreferrer">Google</a></h6>
                  </div>
                </div>
                <!-- end .testimony-layout-1  -->

              </div>

            </div>
            <!-- end .testimonial-container  -->

          </div>

          <div class="col-lg-6 hidden-md hidden-xs hidden-sm no-padding">
            <figure>
              <img src="images/reviews.webp" alt="Client testimonials — Home Renovation Carpentry Ottawa; Finish Carpentry Services Ontario; Egress window compliance projects" class="db">
            </figure>
          </div>

        </div>

      </div>
      <!-- end .container (testimonials + image card)  -->

      <div class="container testimonials-google-cta-outer">
        <div class="row">
          <div class="col-md-12 col-sm-12 text-center">
            <div class="testimonials-google-cta">
              <a
                class="btn btn-theme btn-square"
                href="https://www.google.com/maps/place/Zanini+Renovations/@45.0145689,-76.3839057,8z/data=!4m8!3m7!1s0x270af127fae3b7c1:0x6a89028fc92f8eda!8m2!3d45.120446!4d-76.0081985!9m1!1b1!16s%2Fg%2F11mm5zjskr?hl=en&amp;entry=ttu&amp;g_ep=EgoyMDI2MDMyOS4wIKXMDSoASAFQAw%3D%3D"
                target="_blank"
                rel="noopener noreferrer"
              >
                See all reviews on Google
              </a>
            </div>
          </div>
        </div>
      </div>

    </section>

    <!--  SECTION CONTACT (replaces newsletter)  -->

    <section id="contact" class="section-content-block section-secondary-bg border-bottom-1p-solid-light home-contact-palette">

      <div class="container">

        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-dark-color">Contact</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h2 class="subheading-alt-style">
              Reach out for an estimate or any questions about your renovation or carpentry project.
            </h2>
          </div>
        </div>

        <div class="row">

          <div class="col-md-12">
            <h2 class="contact-title">Get in touch</h2>
          </div>

          <div class="col-md-3 col-sm-6 col-xs-12">
            <ul class="contact-info">
              <li>
                <span class="icon-container"><i class="fa fa-home"></i></span>
                <address>Carleton Place, ON</address>
              </li>
            </ul>
          </div>

          <div class="col-md-3 col-sm-6 col-xs-12">
            <ul class="contact-info">
              <li>
                <span class="icon-container"><i class="fa fa-phone"></i></span>
                <address><a href="tel:+16478347570">(647) 834-7570</a></address>
              </li>
            </ul>
          </div>

          <div class="col-md-3 col-sm-6 col-xs-12">
            <ul class="contact-info">
              <li>
                <span class="icon-container"><i class="fa fa-map-marker"></i></span>
                <address><a href="https://maps.app.goo.gl/WsKrQcEq2KdPKoN19" target="_blank" rel="noopener noreferrer">Google Maps</a></address>
              </li>
            </ul>
          </div>

          <div class="col-md-3 col-sm-6 col-xs-12">
            <ul class="contact-info">
              <li>
                <span class="icon-container"><i class="fa fa-clock-o"></i></span>
                <address>Monday&ndash;Friday / 9am&ndash;6pm</address>
              </li>
            </ul>
          </div>

        </div>

        <div class="row home-contact-whatsapp-row">
          <div class="col-md-12 col-sm-12 text-center">
            <a
              href="{{ $socialWhatsappUrl }}"
              class="home-contact-whatsapp-btn"
              @if (str_starts_with($socialWhatsappUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif
            >
              <i class="fa fa-whatsapp" aria-hidden="true"></i>
              <span>Message us on WhatsApp</span>
            </a>
          </div>
        </div>

      </div>

    </section>

    <section id="contact-location" class="section-content-block section-pure-white-bg section-contact-block home-contact-palette">

      <div class="container">

        <div class="row">

          <div class="col-sm-6 wow fadeInRight">

            <h2 class="contact-title">Our location</h2>

            <div class="section-google-map-block wow fadeInUp home-google-map-embed">
              <iframe
                title="Zanini Renovations on Google Maps"
                src="https://www.google.com/maps?q=Zanini+Renovations,+Carleton+Place,+ON,+Canada&amp;output=embed"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
              <p class="text-center margin-top-12">
                <a href="https://maps.app.goo.gl/WsKrQcEq2KdPKoN19" target="_blank" rel="noopener noreferrer" class="text-primary-color">Open in Google Maps</a>
              </p>
            </div>

          </div>

          <div class="col-sm-6 wow fadeInLeft">

            <div class="contact-form-block">

              <h2 class="contact-title">Send a message</h2>
              <p class="home-contact-form-whatsapp-note">
                <a
                  href="{{ $socialWhatsappUrl }}"
                  class="home-contact-form-whatsapp-link"
                  @if (str_starts_with($socialWhatsappUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif
                ><i class="fa fa-whatsapp" aria-hidden="true"></i> Or reach us on WhatsApp</a>
              </p>

              <form role="form" action="#" method="post" id="contact-form">

                <div class="form-group">
                  <input type="text" class="form-control" id="user_name" name="user_name" placeholder=" Name" data-msg="Please enter your name">
                </div>

                <div class="form-group">
                  <input type="email" class="form-control" id="user_email" name="user_email" placeholder=" Email" data-msg="Please enter a valid email">
                </div>

                <div class="form-group">
                  <input type="text" class="form-control" id="location_city" name="location_city" placeholder=" Location / city" data-msg="Please enter your location or city">
                </div>

                <div class="form-group">
                  <label class="home-contact-date-label" for="starting_date">Starting date <span class="text-muted">(optional)</span></label>
                  <input type="date" class="form-control home-contact-date-input" id="starting_date" name="starting_date">
                </div>

                <div class="form-group">
                  <label class="home-contact-date-label" for="budget">What is your budget? <span class="text-muted">(optional)</span></label>
                  <input type="text" class="form-control" id="budget" name="budget" placeholder="e.g. $10,000–$25,000 or a ballpark amount" maxlength="500" autocomplete="off">
                </div>

                <div class="form-group">
                  <input type="text" class="form-control" id="email_subject" name="email_subject" placeholder=" Subject" data-msg="Please enter a subject">
                </div>

                <div class="form-group">
                  <textarea class="form-control" rows="5" name="email_message" id="email_message" placeholder=" Describe your project, timeline, and what you have in mind" data-msg="Please enter your message"></textarea>
                </div>

                <div class="form-group">
                  <button type="submit" class="btn btn-theme btn-custom">Send now</button>
                </div>

                <div id="contact-form-feedback" class="home-contact-form-feedback" role="status" aria-live="polite"></div>

              </form>

            </div>

          </div>

        </div>

      </div>

    </section>
    <!--  end contact section -->

@endsection
