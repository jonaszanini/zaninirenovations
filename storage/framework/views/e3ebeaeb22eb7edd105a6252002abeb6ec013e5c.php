<?php
  $home = rtrim(url('/'), '/');
  $socialFacebookUrl = (string) config('social.facebook_url');
  $socialInstagramUrl = (string) config('social.instagram_url');
  $socialWhatsappUrl = (string) config('social.whatsapp_url');
?>

<footer>

  <section class="footer-widget-area footer-widget-area-bg section-custom-bg" data-bg_img="<?php echo e(url('images/footer_bg.webp')); ?>" data-bg_color="#111111" data-bg_opacity="0.80">

    <div class="container">

      <div class="row">

        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">

          <div class="about-footer">

            <div class="row">

              <div class="col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12">

                <figure>
                  <img src="<?php echo e(url('images/logo-footer.webp')); ?>" alt="Zanini Renovations — WSIB Clearance Certificate; General Liability Insurance $2M; Part 9 Housing &amp; Small Buildings" loading="lazy" decoding="async">
                </figure>
                <p class="margin-top-24">

                Zanini Renovations is Ontario’s trusted expert for general carpentry and complete renovations. From custom trim to full builds, we provide residential and commercial excellence with superior warranties and the best professional pricing.

                </p>
                <div class="footer-social-icons">

                  <a href="<?php echo e($socialFacebookUrl); ?>"<?php if(str_starts_with($socialFacebookUrl, 'http')): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                    <i class="fa fa-facebook"></i>
                  </a>

                  <a href="<?php echo e($socialInstagramUrl); ?>"<?php if(str_starts_with($socialInstagramUrl, 'http')): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                    <i class="fa fa-instagram"></i>
                  </a>

                  <a href="<?php echo e($socialWhatsappUrl); ?>"<?php if(str_starts_with($socialWhatsappUrl, 'http')): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?> title="WhatsApp">
                    <i class="fa fa-whatsapp"></i>
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
                      <a href="mailto:<?php echo e(config('mail.from.address')); ?>"><?php echo e(config('mail.from.address')); ?></a>
                    </p>
                  </div>
                </div>

                <div class="footer-contact-item">
                  <i class="fa fa-location-arrow fa-contact" aria-hidden="true"></i>
                  <div class="footer-contact-body">
                    <p>
                      Carleton Place, ON<br>
                      <a href="https://maps.app.goo.gl/WsKrQcEq2KdPKoN19" target="_blank" rel="noopener noreferrer">Google Maps</a>
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
                    Basement Renovation
                  </li>
                  <li>
                    <i class="fa fa-caret-right fa-footer"></i>
                    Custom Kitchen Remodel
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
          <p class="copyright-text">Copyright © <?php echo e(date('Y')); ?>. All rights reserved by Zanini Renovations</p>
          <p class="copyright-text" style="margin-top: 4px;">
            <a href="<?php echo e(route('privacy-policy')); ?>">Privacy Policy</a>
            &nbsp;|&nbsp;
            <a href="<?php echo e(route('terms-and-conditions')); ?>">Terms and Conditions</a>
          </p>
        </div>

      </div>

    </div>

  </section>

</footer>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/site/partials/public-footer.blade.php ENDPATH**/ ?>