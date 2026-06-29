<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Choose a new password — Zanini Renovations</title>
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
          <h1>Choose a new password</h1>
          <p class="subtitle">Pick a strong password of at least 8 characters.</p>

          <form method="post" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="admin-form-group">
              <label for="email">Email</label>
              <input class="admin-input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" {{ $email ? 'readonly' : '' }}>
              @error('email')
                <div class="admin-form-error">{{ $message }}</div>
              @enderror
            </div>

            <div class="admin-form-group">
              <label for="password">New password</label>
              <div class="admin-password-field">
                <input class="admin-input" type="password" id="password" name="password" required autofocus autocomplete="new-password">
                <button type="button" class="admin-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                  <i class="fa fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              @error('password')
                <div class="admin-form-error">{{ $message }}</div>
              @enderror
            </div>

            <div class="admin-form-group">
              <label for="password_confirmation">Confirm new password</label>
              <div class="admin-password-field">
                <input class="admin-input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                <button type="button" class="admin-password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                  <i class="fa fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="admin-btn admin-btn--primary">Reset password</button>
          </form>

          <a class="back" href="{{ route('login') }}"><i class="fa fa-arrow-left"></i> Back to sign in</a>
        </div>
      </div>
    </div>
  </div>
  @include('admin.auth.partials.password-toggle-script')
</body>
</html>
