<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Zanini Renovations</title>
  <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
  <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) ?: time() }}">
</head>
<body class="admin-body">
  <div class="admin-login-page">
    <div class="admin-login-wrap">
      <div class="admin-login-card">
        <div class="admin-login-card-strip" role="presentation"></div>
        <div class="admin-login-card-inner">
          <div class="admin-login-brand">
            <a href="{{ url('/') }}" class="admin-login-logo-link" title="Zanini Renovations — home">
              <img src="{{ asset('images/logo.png') }}" alt="Zanini Renovations" class="admin-login-logo">
            </a>
            <strong>Admin</strong>
          </div>
          <h1>Sign in</h1>
          <p class="subtitle">Site administration only. Use the account provided by your team.</p>

          @if (session('status'))
            <div class="admin-auth-status">{{ session('status') }}</div>
          @endif

          <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="admin-form-group">
              <label for="email">Email</label>
              <input class="admin-input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
              @error('email')
                <div class="admin-form-error">{{ $message }}</div>
              @enderror
            </div>
            <div class="admin-form-group">
              <label for="password">Password</label>
              <div class="admin-password-field">
                <input class="admin-input" type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="admin-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                  <i class="fa fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>
            <div class="admin-form-group admin-login-row">
              <label class="admin-login-remember">
                <input type="checkbox" class="admin-checkbox__input" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Remember me</span>
              </label>
              <a class="admin-login-link" href="{{ route('password.request') }}">Forgot your password?</a>
            </div>
            <button type="submit" class="admin-btn admin-btn--primary">Sign in</button>
          </form>

          <a class="back" href="{{ url('/') }}"><i class="fa fa-arrow-left"></i> Back to website</a>
        </div>
      </div>
    </div>
  </div>
  @include('admin.auth.partials.password-toggle-script')
</body>
</html>
