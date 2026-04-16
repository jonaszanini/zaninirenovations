@extends('site.layouts.public')

@section('meta_description', 'Zanini Renovations offers expert Kitchen Cabinet Refacing Ottawa in Kanata. Specialized in high-end finish carpentry and OBC-compliant framing. Get your CAD$ quote today in Kanata.')
@section('meta_keywords', config('seo.lsi_verbatim'))
@section('canonical', url('/services/custom-kitchen-remodel'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::kitchenGraph()) !!}</script>
@endpush

@section('content')
  @php
    $home = rtrim(url('/'), '/');
  @endphp

  <main id="custom-kitchen-remodel" class="page-warranty page-service-kitchen">

    <section class="section-content-block section-primary-bg page-warranty-hero">
      <div class="container">
        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-light-color">Zanini Renovations</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h1 class="subheading-alt-style text-light-color page-warranty-h1">
              Custom Kitchen Remodel
            </h1>
            <p class="page-warranty-hero-lead text-light-color">
              Ottawa &amp; Carleton Place — finish carpentry, custom millwork, and O.Reg. 332/12 compliant structural work.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="section-content-block section-secondary-bg home-contact-palette page-warranty-body">
      <div class="container">
        <div class="row">
          <div class="col-md-10 col-md-offset-1 col-sm-12 col-xs-12 warranty-prose">

            <p class="warranty-lead">
              Kitchen remodels, Kitchen Cabinet Refacing Ottawa, flush-mount cabinetry, and Custom Millwork Ottawa for homeowners from Kanata to Orléans — Red Seal Certified Carpenter, WSIB Clearance Certificate, Part 9 Housing &amp; Small Buildings.
            </p>

            <p class="text-center margin-top-32 margin-bottom-0 warranty-cta-buttons">
              <a href="{{ $home }}#contact-location" class="btn btn-theme btn-square nav_menu">Get in touch</a>
            </p>

          </div>
        </div>
      </div>
    </section>

  </main>
@endsection
