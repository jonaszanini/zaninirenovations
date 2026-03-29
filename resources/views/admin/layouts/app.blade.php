<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title', 'Admin') — Zanini Renovations</title>
  <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
  @stack('head')
</head>
<body class="admin-body">
  <div class="admin-app">
    <aside class="admin-sidebar">
      <div class="admin-sidebar-brand">
        <a href="{{ route('admin.projects.index') }}">Zanini Renovations</a>
        <span>Admin</span>
      </div>
      <ul class="admin-nav">
        <li>
          <a href="{{ route('admin.projects.index') }}" class="{{ request()->routeIs('admin.projects.*') ? 'is-active' : '' }}">
            <i class="fa fa-folder-open"></i>
            Latest projects
          </a>
        </li>
      </ul>
      <div class="admin-sidebar-footer">
        <div>{{ auth()->user()->email }}</div>
        <form action="{{ route('admin.logout') }}" method="post">
          @csrf
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" style="margin-top:0.5rem;width:100%;">Log out</button>
        </form>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <h1>@yield('heading')</h1>
      </header>
      <main class="admin-content">
        @if (session('success'))
          <div class="admin-flash admin-flash--success">{{ session('success') }}</div>
        @endif
        @yield('content')
      </main>
    </div>
  </div>
  @stack('scripts')
</body>
</html>
