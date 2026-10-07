@php
  $home = rtrim(url('/'), '/');
  $socialFacebookUrl = (string) config('social.facebook_url');
  $socialInstagramUrl = (string) config('social.instagram_url');
  $socialWhatsappUrl = (string) config('social.whatsapp_url');
@endphp

<footer>

  <section class="footer-widget-area footer-widget-area-bg section-custom-bg" data-bg_img="{{ url('images/footer_bg.webp') }}" data-bg_color="#111111" data-bg_opacity="0.80">

    <div class="container">

      <div class="row">

        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">

          <div class="about-footer">

            <div class="row">

              <div class="col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12">

                <figure>
                  <img src="{{ url('images/logo-footer.webp') }}" alt="Zanini Renovations" loading="lazy" decoding="async" width="254" height="63">
                </figure>
                <p class="margin-top-24">
                Zanini Renovations is a one-person carpentry and renovation business based in Carleton Place, in business since 2020. Jonas handles residential and commercial work, including kitchen remodels.
                </p>
                <div class="footer-social-icons">

                  <a href="{{ $socialFacebookUrl }}"@if (str_starts_with($socialFacebookUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif aria-label="Facebook">
                    <i class="fa fa-facebook" aria-hidden="true"></i>
                  </a>

                  <a href="{{ $socialInstagramUrl }}"@if (str_starts_with($socialInstagramUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif aria-label="Instagram">
                    <i class="fa fa-instagram" aria-hidden="true"></i>
                  </a>

                  <a href="{{ $socialWhatsappUrl }}"@if (str_starts_with($socialWhatsappUrl, 'http')) target="_blank" rel="noopener noreferrer"@endif aria-label="WhatsApp">
                    <i class="fa fa-whatsapp" aria-hidden="true"></i>
                  </a>

                </div>
              </div>

            </div>

          </div>

        </div>

      </div>

      <div class="row">

        <div class="col-md-4 col-sm-6 col-xs-12">

          <div class="footer-widget">

            <div class="sidebar-widget-wrapper">

              <div class="footer-widget-header clearfix">
                <h3>OFFICE ADDRESS</h3>
              </div>

              <div class="textwidget footer-contact-textwidget">

                <div class="footer-contact-item">
                  <i class="fa fa-envelope-o fa-contact" aria-hidden="true"></i>
                  <div class="footer-contact-body">
                    <p>
                      <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
                    </p>
                  </div>
                </div>

                <div class="footer-contact-item">
                  <i class="fa fa-location-arrow fa-contact" aria-hidden="true"></i>
                  <div class="footer-contact-body">
                    <p>
                      Carleton Place, ON<br>
                      <a href="{{ \App\Support\SeoStructuredData::googleBusinessUrl() }}" target="_blank" rel="noopener noreferrer">Google Maps</a>
                    </p>
                  </div>
                </div>

                <div class="footer-contact-item">
                  <i class="fa fa-phone fa-contact" aria-hidden="true"></i>
                  <div class="footer-contact-body">
                    <p><a href="tel:+16478347570">(647) 834-7570</a></p>
                  </div>
                </div>

              </div>

            </div>

          </div>

        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">

          <div class="footer-widget">

            <div class="sidebar-widget-wrapper">

              <div class="footer-widget-header clearfix">
                <h3>SERVICES</h3>
              </div>

              <div class="textwidget">

                <ul class="footer-useful-links clearfix">
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Fencing
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Decks
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Basement Reno
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Kitchen Remodel
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Trim &amp; Moulding
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Shelving
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Flooring
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Tile
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Accent Walls
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Stairs
                  </li>
                </ul>

              </div>

            </div>

          </div>

        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">

          <div class="footer-widget">

            <div class="sidebar-widget-wrapper">

              <div class="footer-widget-header clearfix">
                <h3>BUSINESS HOURS</h3>
              </div>

              <div class="textwidget">
                <ul class="opening-shedule">
                  <li class="clearfix">
                    <span>Monday &ndash; Friday</span>
                    <div class="pull-right">9am &ndash; 6pm</div>
                  </li>
                </ul>

              </div>

            </div>

          </div>

        </div>

      </div>

    </div>

  </section>

  <section class="footer-contents">

    <div class="container">

      <div class="row clearfix">
        <div class="col-md-12 col-sm-12 text-center clearfix">
          <p class="copyright-text">Copyright © {{ date('Y') }}. All rights reserved by Zanini Renovations</p>
          <p class="copyright-text" style="margin-top: 4px;">
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            &nbsp;|&nbsp;
            <a href="{{ route('terms-and-conditions') }}">Terms and Conditions</a>
          </p>
        </div>

      </div>

    </div>

  </section>

</footer>
