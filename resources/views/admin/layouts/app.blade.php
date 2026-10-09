<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <title>Zanini Renovations</title>
  <script>
    (function () {
      try {
        if (localStorage.getItem('zanini-admin-theme') === 'dark') {
          document.documentElement.classList.add('admin-dark');
        }
      } catch (e) {}
    })();
  </script>
  <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
  <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/admin-search-select.css') }}?v={{ @filemtime(public_path('css/admin-search-select.css')) ?: time() }}">
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
  <link rel="stylesheet" href="{{ asset('css/admin-millwork.css') }}?v={{ @filemtime(public_path('css/admin-millwork.css')) ?: time() }}">
  @endif
  @if ($loadsBlogCss)
  <link rel="stylesheet" href="{{ asset('css/admin-blog.css') }}?v={{ @filemtime(public_path('css/admin-blog.css')) ?: time() }}">
  @endif
  @stack('head')
  <link rel="stylesheet" href="{{ asset('css/admin-dark.css') }}?v={{ @filemtime(public_path('css/admin-dark.css')) ?: time() }}">
</head>
<body class="admin-body">
  <script>
    if (document.documentElement.classList.contains('admin-dark')) {
      document.body.classList.add('admin-dark');
    }
  </script>
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

        $isSharedPrices = request()->routeIs('admin.quotations.shared-prices.*');
        $isQuotation = request()->routeIs('admin.quotations.*');
        $isMaterials = request()->routeIs('admin.materials.*');
        $isVehicles = request()->routeIs('admin.vehicles.*');
        $isFinancialsInvoices = request()->routeIs('admin.financials.invoices');
        $isFinancialsExpenses = request()->routeIs('admin.financials.expenses*');
        $isFinancialsEtransfers = request()->routeIs('admin.financials.etransfers*');
        $isFinancials = $isVehicles || $isFinancialsInvoices || $isFinancialsExpenses || $isFinancialsEtransfers;
        $isComms = request()->routeIs('admin.communications.*');
        $isUtilities = request()->routeIs('admin.utilities.*')
            || request()->routeIs('admin.ai-instructions.*')
            || request()->routeIs('admin.image-creation.*')
            || request()->routeIs('admin.supplier-sites.*');
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

        <li>
          <a href="{{ route('admin.tasks.index') }}" class="{{ request()->routeIs('admin.tasks.*') ? 'is-active' : '' }}">
            <i class="fa fa-check-square-o"></i>
            Tasks
            @if ($sidebarOpenTaskCount > 0)
              <span class="admin-nav-badge">{{ $sidebarOpenTaskCount }}</span>
            @endif
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
                  @if (($sidebarPendingCommentCount ?? 0) > 0)
                    <span class="admin-nav-badge">{{ $sidebarPendingCommentCount }}</span>
                  @endif
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
                <a href="{{ route('admin.quotations.index') }}" class="{{ $isQuotation && ! $isSharedPrices ? 'is-active' : '' }}">
                  <i class="fa fa-file-text-o"></i>
                  Quotation
                </a>
              </li>
              <li>
                <a href="{{ route('admin.quotations.shared-prices.edit') }}" class="{{ $isSharedPrices ? 'is-active' : '' }}">
                  <i class="fa fa-tags"></i>
                  Shared prices
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
          <details class="admin-nav-details" {{ $isFinancials ? 'open' : '' }}>
            <summary class="admin-nav-parent {{ $isFinancials ? 'is-active' : '' }}">
              <i class="fa fa-money"></i>
              <span class="admin-nav-parent__label">Financials</span>
              @if (($sidebarPendingEtransferCount ?? 0) > 0)
                <span class="admin-nav-badge">{{ $sidebarPendingEtransferCount }}</span>
              @endif
              <i class="fa fa-chevron-down admin-nav-caret" aria-hidden="true"></i>
            </summary>
            <ul class="admin-subnav">
              <li>
                <a href="{{ route('admin.financials.invoices') }}" class="{{ $isFinancialsInvoices ? 'is-active' : '' }}">
                  <i class="fa fa-file-text-o"></i>
                  Invoices
                </a>
              </li>
              <li>
                <a href="{{ route('admin.financials.etransfers') }}" class="{{ $isFinancialsEtransfers ? 'is-active' : '' }}">
                  <i class="fa fa-exchange"></i>
                  Incoming e-transfers
                  @if (($sidebarPendingEtransferCount ?? 0) > 0)
                    <span class="admin-nav-badge">{{ $sidebarPendingEtransferCount }}</span>
                  @endif
                </a>
              </li>
              <li>
                <a href="{{ route('admin.financials.expenses') }}" class="{{ $isFinancialsExpenses ? 'is-active' : '' }}">
                  <i class="fa fa-credit-card"></i>
                  Expenses
                </a>
              </li>
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
              <li>
                <a href="{{ route('admin.supplier-sites.index') }}" class="{{ request()->routeIs('admin.supplier-sites.*') ? 'is-active' : '' }}">
                  <i class="fa fa-bookmark"></i>
                  Suppliers
                </a>
              </li>
            </ul>
          </details>
        </li>

        @if (auth()->user()?->isAdmin())
          <li>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
              <i class="fa fa-users"></i>
              Team
            </a>
          </li>
        @endif
      </ul>
      <div class="admin-sidebar-footer">
        <div class="admin-theme-toggle">
          <button type="button"
                  class="admin-theme-toggle__btn"
                  id="admin-theme-toggle"
                  role="switch"
                  aria-checked="false"
                  aria-pressed="false"
                  aria-label="Switch to dark theme">
            <span class="admin-theme-toggle__icon" aria-hidden="true">
              <i class="fa fa-moon-o" data-theme-icon="moon"></i>
              <i class="fa fa-sun-o" data-theme-icon="sun" hidden></i>
            </span>
            <span class="admin-theme-toggle__label">Dark theme</span>
            <span class="admin-theme-toggle__switch" aria-hidden="true"></span>
          </button>
        </div>
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
          <button type="button" class="admin-global-search-btn" onclick="AdminSearchPalette.open()" title="Search (Ctrl+K or Cmd+K)">
            <i class="fa fa-search admin-global-search__icon" aria-hidden="true"></i>
            <span class="admin-global-search__text">Search…</span>
            <kbd class="admin-global-search__kbd">⌘K</kbd>
          </button>
        </div>
      </header>
      <main class="admin-content">
        @if (session('success'))
          <div class="admin-flash admin-flash--success" data-admin-flash="success">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
          <div class="admin-flash admin-flash--warning" data-admin-flash="warning">{{ session('warning') }}</div>
        @endif
        @if (session('error'))
          <div class="admin-flash admin-flash--error" data-admin-flash="error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
          <div class="admin-flash admin-flash--error" data-admin-flash="error" role="alert">
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

  <div id="admin-dialog" class="admin-dialog" hidden aria-hidden="true">
    <div class="admin-dialog-backdrop" data-admin-dialog-dismiss tabindex="-1" aria-hidden="true"></div>
    <div class="admin-dialog-panel" role="alertdialog" aria-modal="true" aria-labelledby="admin-dialog-title" aria-describedby="admin-dialog-message">
      <div class="admin-dialog-icon-wrap admin-dialog-icon-wrap--confirm" aria-hidden="true">
        <i class="fa fa-question-circle admin-dialog-icon" aria-hidden="true"></i>
      </div>
      <h2 id="admin-dialog-title" class="admin-dialog-title">Confirm</h2>
      <p id="admin-dialog-message" class="admin-dialog-message"></p>
      <div class="admin-dialog-actions"></div>
    </div>
  </div>

  <script src="{{ asset('js/admin-theme.js') }}?v={{ @filemtime(public_path('js/admin-theme.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-avif-to-webp.js') }}?v={{ @filemtime(public_path('js/admin-avif-to-webp.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-dialog.js') }}?v={{ @filemtime(public_path('js/admin-dialog.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-search-select.js') }}?v={{ @filemtime(public_path('js/admin-search-select.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-filters-accordion.js') }}?v={{ @filemtime(public_path('js/admin-filters-accordion.js')) ?: time() }}"></script>
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
          window.AdminDialog.alert('Could not disconnect Google Photos. Try again.');
        })
        .catch(function () {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
          window.AdminDialog.alert('Could not disconnect Google Photos. Try again.');
        });
    });

  </script>
  <script src="{{ asset('js/admin-actions-dropdown.js') }}?v={{ @filemtime(public_path('js/admin-actions-dropdown.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-client-projects.js') }}?v={{ @filemtime(public_path('js/admin-client-projects.js')) ?: time() }}"></script>
  @if (request()->routeIs('admin.quotations.*'))
  <script src="{{ asset('js/admin-quotation-link-project.js') }}?v={{ @filemtime(public_path('js/admin-quotation-link-project.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-purchase-list-choice.js') }}?v={{ @filemtime(public_path('js/admin-purchase-list-choice.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-quotation-leave-save.js') }}?v={{ @filemtime(public_path('js/admin-quotation-leave-save.js')) ?: time() }}"></script>
  @endif

  {{-- Shared save toast + Ctrl/Cmd+S (admin only) --}}
  @php
    $adminToastFields = [];
    if ($errors->any()) {
        foreach ($errors->messages() as $fieldName => $messages) {
            $adminToastFields[] = [
                'name' => $fieldName,
                'message' => $messages[0] ?? 'is invalid',
            ];
        }
    }
    $adminToastFlash = [
        'success' => session('success'),
        'error' => session('error'),
        'warning' => session('warning'),
        'fields' => $adminToastFields,
    ];
  @endphp
  <script type="application/json" id="admin-toast-flash">{!! json_encode($adminToastFlash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
  <script src="{{ asset('js/admin-toast.js') }}?v={{ @filemtime(public_path('js/admin-toast.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-hotkey-save.js') }}?v={{ @filemtime(public_path('js/admin-hotkey-save.js')) ?: time() }}"></script>

  {{-- Global search palette (Ctrl+K / Cmd+K) --}}
  <div id="admin-search-palette" class="asp" hidden>
    <div class="asp__backdrop" onclick="AdminSearchPalette.close()"></div>
    <div class="asp__panel">
      <div class="asp__input-wrap">
        <i class="fa fa-search asp__icon" aria-hidden="true"></i>
        <input type="text" id="asp-input" class="asp__input" placeholder="Search projects, clients…" autocomplete="off">
        <kbd class="asp__kbd">ESC</kbd>
      </div>
      <div id="asp-results" class="asp__results"></div>
      <div class="asp__footer">
        <span class="asp__hint"><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
        <span class="asp__hint"><kbd>Enter</kbd> open</span>
        <span class="asp__hint"><kbd>ESC</kbd> close</span>
      </div>
    </div>
  </div>
  <script>
  (function() {
    var palette = document.getElementById('admin-search-palette');
    var input = document.getElementById('asp-input');
    var results = document.getElementById('asp-results');
    var searchUrl = @json(route('admin.search.api'));
    var debounceTimer = null;
    var selectedIndex = -1;
    var items = [];

    window.AdminSearchPalette = {
      open: function() {
        palette.hidden = false;
        document.body.classList.add('asp-open');
        input.value = '';
        results.innerHTML = '';
        selectedIndex = -1;
        items = [];
        setTimeout(function() { input.focus(); }, 50);
      },
      close: function() {
        palette.hidden = true;
        document.body.classList.remove('asp-open');
      },
      isOpen: function() {
        return !palette.hidden;
      }
    };

    function renderResults(data) {
      if (!data.results || data.results.length === 0) {
        results.innerHTML = '<div class="asp__empty">No results found</div>';
        items = [];
        selectedIndex = -1;
        return;
      }
      var html = '';
      items = [];
      data.results.forEach(function(group) {
        html += '<div class="asp__group">';
        html += '<div class="asp__group-label">' + escapeHtml(group.label) + '</div>';
        group.items.forEach(function(item, idx) {
          var globalIdx = items.length;
          items.push(item);
          html += '<a href="' + escapeHtml(item.url) + '" class="asp__item" data-idx="' + globalIdx + '">';
          html += '<i class="fa fa-' + escapeHtml(item.icon || 'file') + ' asp__item-icon"></i>';
          html += '<div class="asp__item-text">';
          html += '<div class="asp__item-title">' + escapeHtml(item.title) + '</div>';
          if (item.subtitle) {
            html += '<div class="asp__item-sub">' + escapeHtml(item.subtitle) + '</div>';
          }
          html += '</div>';
          if (item.status) {
            html += '<span class="asp__item-badge" style="background:' + escapeHtml(item.status_color || '#6b7280') + '20;color:' + escapeHtml(item.status_color || '#6b7280') + ';">' + escapeHtml(item.status) + '</span>';
          }
          html += '</a>';
        });
        html += '</div>';
      });
      results.innerHTML = html;
      selectedIndex = -1;
      updateSelection();
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
      });
    }

    function updateSelection() {
      var els = results.querySelectorAll('.asp__item');
      els.forEach(function(el, i) {
        el.classList.toggle('is-selected', i === selectedIndex);
      });
      if (selectedIndex >= 0 && els[selectedIndex]) {
        els[selectedIndex].scrollIntoView({ block: 'nearest' });
      }
    }

    function doSearch() {
      var q = input.value.trim();
      if (q.length < 2) {
        results.innerHTML = '<div class="asp__empty">Type at least 2 characters</div>';
        items = [];
        selectedIndex = -1;
        return;
      }
      results.innerHTML = '<div class="asp__loading"><i class="fa fa-spinner fa-spin"></i> Searching…</div>';
      fetch(searchUrl + '?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(renderResults)
        .catch(function() {
          results.innerHTML = '<div class="asp__empty">Search failed</div>';
        });
    }

    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(doSearch, 200);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (items.length > 0) {
          selectedIndex = (selectedIndex + 1) % items.length;
          updateSelection();
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (items.length > 0) {
          selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
          updateSelection();
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && items[selectedIndex]) {
          window.location.href = items[selectedIndex].url;
        }
      } else if (e.key === 'Escape') {
        AdminSearchPalette.close();
      }
    });

    results.addEventListener('mouseover', function(e) {
      var item = e.target.closest('.asp__item');
      if (item) {
        selectedIndex = parseInt(item.dataset.idx, 10);
        updateSelection();
      }
    });

    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (AdminSearchPalette.isOpen()) {
          AdminSearchPalette.close();
        } else {
          AdminSearchPalette.open();
        }
      }
      if (e.key === 'Escape' && AdminSearchPalette.isOpen()) {
        AdminSearchPalette.close();
      }
    });
  })();
  </script>
  @stack('scripts')
</body>
</html>
