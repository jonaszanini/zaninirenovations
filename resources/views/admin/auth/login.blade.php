<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin sign in — Zanini Renovations</title>
  <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body">
  <div class="admin-login-page">
    <div class="admin-login-card">
      <div class="admin-login-brand">
        <span>Zanini Renovations</span>
        <strong>Admin</strong>
      </div>
      <h1>Sign in</h1>
      <p class="subtitle">Site administration only.</p>

      <form method="post" action="{{ route('login') }}">
        @csrf
        <div class="admin-form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
          @error('email')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
        <div class="admin-form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <div class="admin-form-group">
          <label style="display:flex;align-items:center;gap:0.5rem;text-transform:none;font-weight:500;">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            Remember me
          </label>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">Sign in</button>
      </form>

      <a class="back" href="{{ url('/') }}"><i class="fa fa-arrow-left"></i> Back to website</a>
    </div>
  </div>
</body>
</html>
