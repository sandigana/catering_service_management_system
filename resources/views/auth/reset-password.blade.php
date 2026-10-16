{{-- resources/views/auth/reset-password.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a new password - CaterEase</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="auth-page">
<div class="auth-wrapper">

    <div class="auth-showcase register-showcase">
        <div class="brand">
            <div class="brand-icon">🍽️</div>
            <div><h2>Cater<span>Ease</span></h2><small>CATERING SERVICE</small></div>
        </div>

        <div class="showcase-content">
            <div class="food-circle">🔒</div>
            <h1>Choose a <span>new password</span></h1>
            <p>Use at least 8 characters with letters and numbers.</p>
        </div>

        <div class="showcase-footer">© {{ date('Y') }} CaterEase Catering Service</div>
    </div>

    <div class="auth-form-area">
        <div class="auth-box">
            <div class="mobile-logo">🍽️ Cater<span>Ease</span></div>

            <div class="form-heading">
                <span class="welcome-label">NEW PASSWORD</span>
                <h1>Set your password</h1>
                <p>Then you can sign in as usual.</p>
            </div>

            @if ($errors->any())
                <div class="error-message">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input id="password" type="password" name="password" placeholder="New password" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔐</span>
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat the password" required>
                    </div>
                </div>

                <button type="submit" class="auth-button">Reset password →</button>
            </form>

            <div class="divider"><span>OR</span></div>

            <p class="auth-link"><a href="{{ route('login') }}">Back to sign in</a></p>
        </div>
    </div>

</div>
</body>
</html>
