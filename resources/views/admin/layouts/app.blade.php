<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <title>Zanini Renovations</title>
  <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
  <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) ?: time() }}">
  @php
    $loadsClientProjectsCss = request()->routeIs(
      'admin.client-projects.*',
      'admin.clients.*',
      'admin.quotations.*',
      'admin.portfolio.*',
      'admin.categories.*',
      'admin.helpers.*',
      'admin.communications.*',
      'admin.vehicles.*',
      'admin.dashboard',
    );
    $loadsBlogCss = request()->routeIs('admin.blog.*');
  @endphp
  @if ($loadsClientProjectsCss)
  <link rel="stylesheet" href="{{ asset('css/admin-client-projects.css') }}?v={{ @filemtime(public_path('css/admin-client-projects.css')) ?: time() }}">
  @endif
  @if ($loadsBlogCss)
  <link rel="stylesheet" href="{{ asset('css/admin-blog.css') }}?v={{ @filemtime(public_path('css/admin-blog.css')) ?: time() }}">
  @endif
  @stack('head')
</head>
<body class="admin-body">
  <div class="admin-app" id="admin-app">
    <div class="admin-nav-backdrop" id="admin-nav-backdrop" aria-hidden="true" hidden></div>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
      <div class="admin-sidebar-brand">
        <a href="{{ route('admin.dashboard') }}">Zanini Renovations</a>
        <span>Admin</span>
      </div>
      @php
        $isCalendar = request()->routeIs('admin.client-projects.calendar');
        $isAllProjects = request()->routeIs('admin.client-projects.*') && ! $isCalendar;
        $isPhotos = request()->routeIs('admin.portfolio.*') || request()->routeIs('admin.categories.*');
        $projectsOpen = $isAllProjects || $isCalendar || $isPhotos;

        $isQuotation = request()->routeIs('admin.quotations.*');
        $isMaterials = request()->routeIs('admin.materials.*');
        $isVehicles = request()->routeIs('admin.vehicles.*');
        $isComms = request()->routeIs('admin.communications.*');
        $isUtilities = request()->routeIs('admin.utilities.*')
            || request()->routeIs('admin.ai-instructions.*')
            || request()->routeIs('admin.image-creation.*');
        $isBlog = request()->routeIs('admin.blog.*');
      @endphp
      <ul class="admin-nav">
        <li>
          <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
            <i class="fa fa-tachometer"></i>
            Dashboard
          </a>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $projectsOpen ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $projectsOpen ? 'is-active' : '' }}">
              <i class="fa fa-briefcase"></i>
              <span class="admin-nav-parent__label">Projects</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.client-projects.index') }}" class="{{ $isAllProjects ? 'is-active' : '' }}">
                  <i class="fa fa-briefcase"></i>
                  All Projects
                </a>
              </li>
              <li>
                <a href="{{ route('admin.client-projects.calendar') }}" class="{{ $isCalendar ? 'is-active' : '' }}">
                  <i class="fa fa-calendar"></i>
                  Project Calendar
                </a>
              </li>
              <li>
                <a href="{{ route('admin.portfolio.index') }}" class="{{ $isPhotos ? 'is-active' : '' }}">
                  <i class="fa fa-folder-open"></i>
                  Latest Projects Photos
                </a>
              </li>
            </ul>
          </details>
        </li>

        <li>
          <a href="{{ route('admin.clients.index') }}" class="{{ request()->routeIs('admin.clients.*') ? 'is-active' : '' }}">
            <i class="fa fa-address-book"></i>
            Clients
          </a>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $isBlog ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isBlog ? 'is-active' : '' }}">
              <i class="fa fa-rss"></i>
              <span class="admin-nav-parent__label">Blog</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.blog.posts.index') }}" class="{{ request()->routeIs('admin.blog.posts.*') ? 'is-active' : '' }}">
                  <i class="fa fa-file-text"></i>
                  Posts
                </a>
              </li>
              <li>
                <a href="{{ route('admin.blog.categories.index') }}" class="{{ request()->routeIs('admin.blog.categories.*') ? 'is-active' : '' }}">
                  <i class="fa fa-folder"></i>
                  Categories
                </a>
              </li>
              <li>
                <a href="{{ route('admin.blog.tags.index') }}" class="{{ request()->routeIs('admin.blog.tags.*') ? 'is-active' : '' }}">
                  <i class="fa fa-tags"></i>
                  Tags
                </a>
              </li>
              <li>
                <a href="{{ route('admin.blog.comments.index') }}" class="{{ request()->routeIs('admin.blog.comments.*') ? 'is-active' : '' }}">
                  <i class="fa fa-comments"></i>
                  Comments
                </a>
              </li>
            </ul>
          </details>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $isQuotation ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isQuotation ? 'is-active' : '' }}">
              <i class="fa fa-calculator"></i>
              <span class="admin-nav-parent__label">Quotations</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.quotations.index') }}" class="{{ $isQuotation ? 'is-active' : '' }}">
                  <i class="fa fa-file-text-o"></i>
                  Quotation
                </a>
              </li>
            </ul>
          </details>
        </li>

        <li>
          <a href="{{ route('admin.materials.index') }}" class="{{ $isMaterials ? 'is-active' : '' }}">
            <i class="fa fa-cubes"></i>
            Materials
          </a>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $isVehicles ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isVehicles ? 'is-active' : '' }}">
              <i class="fa fa-money"></i>
              <span class="admin-nav-parent__label">Financials</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.vehicles.index') }}" class="{{ $isVehicles ? 'is-active' : '' }}">
                  <i class="fa fa-car"></i>
                  Vehicles
                </a>
              </li>
            </ul>
          </details>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $isComms ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isComms ? 'is-active' : '' }}">
              <i class="fa fa-comments"></i>
              <span class="admin-nav-parent__label">Communication</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.communications.index') }}" class="{{ $isComms ? 'is-active' : '' }}">
                  <i class="fa fa-paper-plane"></i>
                  Email/SMS Log
                </a>
              </li>
            </ul>
          </details>
        </li>

        <li class="admin-nav-group">
          <details class="admin-nav-details" {{ $isUtilities ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isUtilities ? 'is-active' : '' }}">
              <i class="fa fa-cogs"></i>
              <span class="admin-nav-parent__label">Tools</span>
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.ai-instructions.index') }}" class="{{ request()->routeIs('admin.ai-instructions.*') ? 'is-active' : '' }}">
                  <i class="fa fa-magic"></i>
                  AI Instructions
                </a>
              </li>
              <li>
                <a href="{{ route('admin.image-creation.index') }}" class="{{ request()->routeIs('admin.image-creation.*') ? 'is-active' : '' }}">
                  <i class="fa fa-picture-o"></i>
                  AI Image
                </a>
              </li>
              <li>
                <a href="{{ route('admin.utilities.index') }}" class="{{ request()->routeIs('admin.utilities.*') ? 'is-active' : '' }}">
                  <i class="fa fa-wrench"></i>
                  Utilities
                </a>
              </li>
            </ul>
          </details>
        </li>
      </ul>
      <div class="admin-sidebar-footer">
        <div class="admin-sidebar-user">{{ auth()->user()->email }}</div>
        <form action="{{ route('admin.logout') }}" method="post">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm admin-sidebar-logout">Log out</button>
        </form>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-inner">
          <button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="admin-sidebar">
            <i class="fa fa-bars admin-menu-icon-open" aria-hidden="true"></i>
            <i class="fa fa-times admin-menu-icon-close" aria-hidden="true"></i>
          </button>
          <h1>@yield('heading')</h1>
        </div>
      </header>
      <main class="admin-content">
        @if (session('success'))
          <div class="admin-flash admin-flash--success">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
          <div class="admin-flash admin-flash--warning">{{ session('warning') }}</div>
        @endif
        @if (session('error'))
          <div class="admin-flash admin-flash--error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
          <div class="admin-flash admin-flash--error" role="alert">
            <strong>Please fix the following:</strong>
            <ul style="margin:8px 0 0 18px;">
              @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
              @endforeach
            </ul>
          </div>
        @endif
        @yield('content')
      </main>
    </div>
  </div>
  <script>
    (function () {
      var app = document.getElementById('admin-app');
      var toggle = document.getElementById('admin-menu-toggle');
      var backdrop = document.getElementById('admin-nav-backdrop');
      if (!app || !toggle || !backdrop) return;

      var mq = window.matchMedia('(max-width: 900px)');

      function setOpen(open) {
        app.classList.toggle('admin-nav-open', open);
        document.body.classList.toggle('admin-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        backdrop.hidden = !open;
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function closeNav() {
        setOpen(false);
      }

      toggle.addEventListener('click', function () {
        setOpen(!app.classList.contains('admin-nav-open'));
      });

      backdrop.addEventListener('click', closeNav);

      document.querySelectorAll('.admin-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
          if (mq.matches) closeNav();
        });
      });

      mq.addEventListener('change', function (e) {
        if (!e.matches) closeNav();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && app.classList.contains('admin-nav-open')) {
          closeNav();
          toggle.focus();
        }
      });
    })();

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-admin-google-disconnect]');
      if (!btn) return;
      var url = btn.getAttribute('data-disconnect-url');
      var meta = document.querySelector('meta[name="csrf-token"]');
      if (!url || !meta) return;
      e.preventDefault();
      if (btn.getAttribute('aria-busy') === 'true') return;
      btn.setAttribute('aria-busy', 'true');
      btn.disabled = true;
      var t = meta.getAttribute('content') || '';
      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': t,
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json, text/html, */*;q=0.8'
        },
        body: '_token=' + encodeURIComponent(t)
      })
        .then(function (res) {
          if (res.ok) {
            window.location.reload();
            return;
          }
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
          window.alert('Could not disconnect Google Photos. Try again.');
        })
        .catch(function () {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
          window.alert('Could not disconnect Google Photos. Try again.');
        });
    });
  </script>
  <script src="{{ asset('js/admin-client-projects.js') }}?v={{ @filemtime(public_path('js/admin-client-projects.js')) ?: time() }}"></script>
  @stack('scripts')
</body>
</html>
