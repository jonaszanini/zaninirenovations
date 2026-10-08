@extends('site.layouts.public')

@section('page_title', 'Workmanship Warranty & Insurance | Zanini Renovations')
@section('meta_description', 'One-year workmanship warranty and fully insured coverage, explained. Renovate with confidence in Lanark County and Ottawa.')
@section('canonical', url('/warranty'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(array_merge(['@context' => 'https://schema.org'], \App\Support\SeoStructuredData::breadcrumb([
    ['name' => 'Home', 'url' => url('/')],
    ['name' => 'Warranty & Protection', 'url' => url('/warranty')],
]))) !!}</script>
@endpush

@section('content')
  @php
    $home = rtrim(url('/'), '/');
  @endphp

  <main id="warranty" class="page-warranty">

    <section class="section-content-block section-primary-bg page-warranty-hero">
      <div class="container">
        <div class="row section-heading-wrapper">
          <div class="col-md-12 col-sm-12 text-center">
            <h4 class="heading-alt-style text-uppercase text-light-color">Zanini Renovations</h4>
            <span class="heading-separator heading-separator-horizontal"></span>
            <h1 class="subheading-alt-style text-light-color page-warranty-h1">
              Warranty &amp; Protection
            </h1>
            <p class="page-warranty-hero-lead text-light-color">
              Craftsmanship you can trust — backed by clear warranty terms and strong insurance.
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
              At Zanini Renovations, we take pride in our craftsmanship and the durability of our work. We stand behind every cut, joint, and finish we create. To ensure your investment is protected, we provide a comprehensive warranty on all our services.
              <br/>
              <br/>
            </p>

            <h2 class="contact-title">Our Workmanship Guarantee</h2>
            <p>
              We offer a <strong>one-year limited warranty</strong> on all workmanship. This covers any defects resulting from the installation or construction process for services including:
            </p>
            <ul class="warranty-list">
              <li><strong>Custom Carpentry:</strong> Built-ins, shelving, and finish trim.</li>
              <li><strong>Structural Work:</strong> Framing, decks, and fencing.</li>
              <li><strong>Interior Renovations:</strong> Flooring, drywall, and basement finishing.</li>
            </ul>
            <p>
              All work is performed strictly according to the <strong>Ontario Building Code (O.Reg. 332/12)</strong>, ensuring that your renovation is not only beautiful but safe and compliant with provincial standards.
            </p>

            <h2 class="contact-title">Material Warranties</h2>
            <p>
              While our warranty covers the labor and installation, the materials used (such as flooring, windows, or decking boards) are covered by the specific manufacturer&rsquo;s warranty. We source high-quality materials from reputable Ontario and Canadian suppliers to ensure longevity.
            </p>

            <h2 class="contact-title">Superior Coverage &amp; Peace of Mind</h2>
            <p>
              Your home is likely your biggest asset, and we treat it with the respect it deserves. We go above and beyond industry standards to ensure our clients are protected.
            </p>
            <ul class="warranty-list warranty-list--spaced">
              <li>
                <strong>Liability insurance:</strong> We are fully insured with Comprehensive General Liability coverage. This ensures that even the most complex projects are fully protected against any unforeseen property damage or third-party injury.
              </li>
            </ul>

            <h2 class="contact-title">Request a Quote or Service</h2>
            <p class="warranty-cta-text">
              If you have questions about our warranty or would like to see our certificate of insurance before starting your project, please contact us. We are committed to making sure you are 100% satisfied with the final result.
            </p>
            <p class="text-center margin-top-32 margin-bottom-0 warranty-cta-buttons">
              <a href="{{ url('/contact') }}" class="btn btn-theme btn-square">Get in touch</a>
            </p>

          </div>
        </div>
      </div>
    </section>

  </main>
@endsection
