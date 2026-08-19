@php
  $home = rtrim(url('/'), '/');
  $socialFacebookUrl = (string) config('social.facebook_url');
  $socialInstagramUrl = (string) config('social.instagram_url');
  $socialWhatsappUrl = (string) config('social.whatsapp_url');
  $isWarranty = request()->routeIs('warranty');
@endphp

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
              <a href="{{ $home }}#testimonials" class="nav_menu">Reviews</a>
              <span class="separator-line"></span>
              <a href="{{ route('warranty') }}"@if ($isWarranty) class="text-primary-color"@endif>Warranty</a>
              <span class="separator-line"></span>
              <a href="{{ $home }}#contact" class="nav_menu">Contact</a>
              <span class="separator-line"></span>
              <a href="{{ url('/admin/login') }}">Admin</a>
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
              <a href="{{ $home }}" title="Zanini Renovations — Home">
                <img src="{{ url('images/logo.png') }}" alt="Zanini Renovations" width="254" height="63" decoding="async">
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
                <a class="btn btn-theme btn-square btn-header-cta margin-top-36 pull-right nav_menu" href="{{ $home }}#contact-location" title="GET FREE ESTIMATE">FREE ESTIMATE</a>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="navbar navbar-default">
      <div class="container clearfix">
        <div class="navbar-collapse collapse pull-left" id="main-nav-collapse">
          <ul class="nav navbar-nav">
            <li>
              <a href="{{ $home }}#home" class="nav_menu @if (! $isWarranty) link-active @endif" title="Home">Home</a>
            </li>
            <li>
              <a href="{{ $home }}#services" class="nav_menu" title="Services">Services</a>
            </li>
            <li>
              <a href="{{ $home }}#projects" class="nav_menu" title="Latest projects">Projects</a>
            </li>
            <li>
              <a href="{{ $home }}#about" class="nav_menu" title="About us">About</a>
            </li>
            <li>
              <a href="{{ route('blog.index') }}" class="nav_menu @if (request()->routeIs('blog.*')) link-active @endif" title="Blog">Blog</a>
            </li>
            <li>
              <a href="{{ $home }}#testimonials" class="nav_menu" title="Testimonials">Testimonials</a>
            </li>
            <li>
              <a href="{{ $home }}#contact" class="nav_menu" title="Contact">Contact</a>
            </li>
          </ul>
        </div>
        <div class="navbar-header pull-right">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#main-nav-collapse" aria-controls="main-nav-collapse" aria-expanded="false" aria-label="Open menu">
            <span class="sr-only">Menu</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <div class="navbar-contact">

            <div class="top-bar-social margin-top-16">
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

  </section>

</header>

@push('scripts')
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

    var $collapse = $('#main-nav-collapse');
    var $toggle = $('.main-header-2 .navbar-toggle');
    if (!$collapse.length) return;

    $collapse.on('shown.bs.collapse', function () {
      $toggle.attr('aria-expanded', 'true');
    });
    $collapse.on('hidden.bs.collapse', function () {
      $toggle.attr('aria-expanded', 'false');
    });

    $('#home-faq-accordion').on('shown.bs.collapse hidden.bs.collapse', function (e) {
      var $panel = $(e.target);
      var $trigger = $('a[href="#' + e.target.id + '"]');
      $trigger.attr('aria-expanded', e.type === 'shown' ? 'true' : 'false');
    });

    $('.main-header-2 a.nav_menu').on('click', function () {
      if (!isMobile()) return;
      if ($collapse.hasClass('in')) {
        $collapse.collapse('hide');
      }
    });
  });
})(window.jQuery);
</script>
@endpush
