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
                <img src="<?php echo e(url('images/logo.png')); ?>" alt="Zanini Renovations — Licensed Carpenter Ottawa; Custom Millwork Ottawa; Carleton Place">
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

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
  // Mobile-only scroll-spy: keep `.link-active` on the navbar link whose
  // section the user is currently viewing. Desktop keeps its server-rendered
  // active state untouched.
  var MOBILE_BREAKPOINT = 991;

  function isMobile() {
    return window.innerWidth <= MOBILE_BREAKPOINT;
  }

  var navLinks = Array.prototype.slice.call(
    document.querySelectorAll('.main-header-2 .navbar-nav > li > a.nav_menu')
  );
  var sections = navLinks
    .map(function (link) {
      var href = link.getAttribute('href') || '';
      var hash = href.indexOf('#') >= 0 ? href.split('#')[1] : '';
      if (!hash) return null;
      var el = document.getElementById(hash);
      if (!el) return null;
      return { link: link, el: el };
    })
    .filter(Boolean);

  if (!sections.length) return;

  function headerOffset() {
    var header = document.querySelector('.main-header-2');
    var headerH = header ? header.offsetHeight : 0;
    return headerH + 24;
  }

  function clearActive() {
    sections.forEach(function (s) { s.link.classList.remove('link-active'); });
  }

  function setActive(link) {
    clearActive();
    if (link) link.classList.add('link-active');
  }

  function update() {
    if (!isMobile()) return;
    var probeY = window.pageYOffset + headerOffset() + 1;
    var current = sections[0];
    for (var i = 0; i < sections.length; i++) {
      var top = sections[i].el.getBoundingClientRect().top + window.pageYOffset;
      if (top <= probeY) current = sections[i];
    }
    setActive(current.link);
  }

  // On desktop, restore the server-rendered active state (Home).
  function restoreDesktopActive() {
    if (isMobile()) return;
    sections.forEach(function (s) {
      var hash = (s.link.getAttribute('href') || '').split('#')[1];
      if (hash === 'home') s.link.classList.add('link-active');
      else s.link.classList.remove('link-active');
    });
  }

  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () {
      update();
      ticking = false;
    });
  }

  function init() {
    if (isMobile()) update();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () {
      if (isMobile()) update();
      else restoreDesktopActive();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// Mobile-only: tapping a nav link closes the collapsed menu. The template's
// own delegated handler is blocked by `return false` in smooth_scrolling, so
// without this the menu would stay open after each tap.
(function ($) {
  if (!window.jQuery) return;
  $(function () {
    var BREAKPOINT = 991;
    function isMobile() { return window.innerWidth <= BREAKPOINT; }

    var $collapse = $('.main-header-2 .navbar-default .navbar-collapse').first();
    if (!$collapse.length) return;

    $('.main-header-2 a.nav_menu').on('click', function () {
      if (!isMobile()) return;
      if ($collapse.hasClass('in')) {
        $collapse.collapse('hide');
      }
    });
  });
})(window.jQuery);
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\site\partials\public-header.blade.php ENDPATH**/ ?>