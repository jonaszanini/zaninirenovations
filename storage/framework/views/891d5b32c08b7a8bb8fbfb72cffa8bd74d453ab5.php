<?php
  $home = rtrim(url('/'), '/');
  $socialFacebookUrl = (string) config('social.facebook_url');
  $socialInstagramUrl = (string) config('social.instagram_url');
  $socialWhatsappUrl = (string) config('social.whatsapp_url');
  $isWarranty = request()->routeIs('warranty');
?>

<header class="main-header-2 clearfix" data-sticky_header="true">

  <section class="header-wrapper navgiation-wrapper">

    <div class="main-top-header hidden-sm hidden-xs clearfix">
      <div class="container">
        <div class="row">

          <div class="col-lg-6 col-md-6 col-sm-6 text-left ">
            <div class="top-bar-link">
              <strong>PHONE NUMBER</strong>
              : (647)-834-7570
            </div>
          </div>

          <div class="col-lg-6 col-md-6 col-sm-6 text-right">

            <div class="top-bar-link">
              <a href="<?php echo e($home); ?>#testimonials" class="nav_menu">Reviews</a>
              <span class="separator-line"></span>
              <a href="<?php echo e(route('warranty')); ?>"<?php if($isWarranty): ?> class="text-primary-color"<?php endif; ?>>Warranty</a>
              <span class="separator-line"></span>
              <a href="<?php echo e($home); ?>#contact" class="nav_menu">Contact</a>
              <span class="separator-line"></span>
              <a href="<?php echo e(url('/admin/login')); ?>">Admin</a>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="header-top">
      <div class="container">
        <div class="row">

          <div class="col-md-4 col-sm-12 col-xs-12 header-col-logo">
            <div class="header-logo logo">
              <a href="<?php echo e($home); ?>" title="Zanini Renovations — Home">
                <img src="<?php echo e(url('images/logo.png')); ?>" alt="Logo">
              </a>
            </div>
          </div>

          <div class="col-md-8 col-sm-12 hidden-xs">
            <div class="row">

              <div class="col-md-4 col-sm-4">
                <div class="header-icon-box">
                  <div class="icon-container">
                    <i class="fa fa-building-o"></i>
                  </div>
                  <div class="text">
                    <span class="head-heading">we are located at</span>
                    <span class="head-content">
                      Carleton Place, ON
                    </span>
                  </div>
                </div>
              </div>

              <div class="col-md-4 col-sm-4">
                <div class="header-icon-box">
                  <div class="icon-container">
                    <i class="fa fa-calendar-check-o"></i>
                  </div>
                  <div class="text">
                    <span class="head-heading">WORKING HOUR</span>
                    <span class="head-content">
                      Monday-Friday / 9am-6pm
                    </span>
                  </div>
                </div>
              </div>

              <div class="col-md-4 col-sm-4 clearfix">
                <a class="btn btn-theme btn-square btn-header-cta margin-top-36 pull-right nav_menu" href="<?php echo e($home); ?>#contact-location" title="GET FREE ESTIMATE">FREE ESTIMATE</a>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="navbar navbar-default">
      <div class="container clearfix">
        <div class="navbar-collapse collapse pull-left">
          <ul class="nav navbar-nav">
            <li>
              <a href="<?php echo e($home); ?>#home" class="nav_menu <?php if(! $isWarranty): ?> link-active <?php endif; ?>" title="Home">Home</a>
            </li>
            <li>
              <a href="<?php echo e($home); ?>#services" class="nav_menu" title="Services">Services</a>
            </li>
            <li>
              <a href="<?php echo e($home); ?>#projects" class="nav_menu" title="Latest projects">Projects</a>
            </li>
            <li>
              <a href="<?php echo e($home); ?>#about" class="nav_menu" title="About us">About</a>
            </li>
            <li>
              <a href="<?php echo e($home); ?>#testimonials" class="nav_menu" title="Testimonials">Testimonials</a>
            </li>
            <li>
              <a href="<?php echo e($home); ?>#contact" class="nav_menu" title="Contact">Contact</a>
            </li>
          </ul>
        </div>
        <div class="navbar-header pull-right">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <div class="navbar-contact">

            <div class="top-bar-social margin-top-16">
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

  </section>

</header>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/site/partials/public-header.blade.php ENDPATH**/ ?>