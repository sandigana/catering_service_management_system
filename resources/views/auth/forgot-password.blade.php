{{-- resources/views/auth/forgot-password.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password - CaterEase</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="auth-page">
<div class="auth-wrapper">

    <div class="auth-showcase">
        <div class="brand">
            <div class="brand-icon">🍽️</div>
            <div><h2>Cater<span>Ease</span></h2><small>CATERING SERVICE</small></div>
        </div>

        <div class="showcase-content">
            <div class="food-circle">🔑</div>
            <h1>Forgot your <span>password?</span></h1>
            <p>No problem. Enter your e-mail and we will send you a secure link to choose a new one.</p>
        </div>

        <div class="showcase-footer">© {{ date('Y') }} CaterEase Catering Service</div>
    </div>

    <div class="auth-form-area">
        <div class="auth-box">
            <div class="mobile-logo">🍽️ Cater<span>Ease</span></div>

            <div class="form-heading">
                <span class="welcome-label">ACCOUNT RECOVERY</span>
                <h1>Reset your password</h1>
                <p>We will e-mail you a link that works for one hour.</p>
            </div>

            @if (session('success'))
                <div class="success-message">✅ {{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-message">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autofocus>
                    </div>
                </div>

                <button type="submit" class="auth-button">Send reset link →</button>
            </form>

            <div class="divider"><span>OR</span></div>

            <p class="auth-link">Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
        </div>
    </div>

</div>
</body>
</html>
