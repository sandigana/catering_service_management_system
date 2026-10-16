<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Catering Service</title>
    @include('partials.favicon')

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body class="auth-page">

<div class="auth-wrapper">

    <!-- LEFT SIDE -->
    <div class="auth-showcase">

        <div class="brand">
            <div class="brand-icon">🍽️</div>

            <div>
                <h2>Cater<span>Ease</span></h2>
                <small>CATERING SERVICE</small>
            </div>
        </div>

        <div class="showcase-content">

            <div class="food-circle">
                🍴
            </div>

            <h1>
                Good Food.<br>
                <span>Great Moments.</span>
            </h1>

            <p>
                Make every celebration special with delicious
                food and exceptional catering service.
            </p>

            <div class="features">

                <div>
                    <b>✓</b>
                    <span>Quality Food</span>
                </div>

                <div>
                    <b>✓</b>
                    <span>Professional Service</span>
                </div>

                <div>
                    <b>✓</b>
                    <span>Memorable Events</span>
                </div>

            </div>

        </div>

        <div class="showcase-footer">
            © {{ date('Y') }} CaterEase Catering Service
        </div>

    </div>


    <!-- RIGHT SIDE -->
    <div class="auth-form-area">

        <div class="auth-box">

            <div class="mobile-logo">
                🍽️ Cater<span>Ease</span>
            </div>

            <div class="form-heading">

                <span class="welcome-label">
                    WELCOME BACK
                </span>

                <h1>Sign in to your account</h1>

                <p>
                    Enter your details to continue.
                </p>

            </div>


            {{-- Success Message --}}

            @if(session('success'))

                <div class="success-message">
                    <span>✓</span>
                    {{ session('success') }}
                </div>

            @endif


            {{-- Validation Errors --}}

            @if($errors->any())

                <div class="error-message">

                    <strong>Please check the following:</strong>

                    @foreach($errors->all() as $error)
                        <p>• {{ $error }}</p>
                    @endforeach

                </div>

            @endif


            <form action="{{ route('login.submit') }}" method="POST">

                @csrf


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            ✉
                        </span>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            autocomplete="off"
                            required
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            🔒
                        </span>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="off"
                            required
                        >

                    </div>

                </div>


                <div class="remember-row">

                    <label class="remember">

                        <input
                            type="checkbox"
                            name="remember"
                        >

                        <span>
                            Remember me
                        </span>

                    </label>

                    <a href="{{ route('password.request') }}">
                        Forgot password?
                    </a>

                </div>


                <button
                    type="submit"
                    class="auth-button"
                >
                    Sign In
                    <span>→</span>
                </button>

            </form>


            <div class="divider">
                <span>OR</span>
            </div>


            <p class="auth-link">

                Don't have an account?

                <a href="{{ route('register') }}">
                    Create an account
                </a>

            </p>


            <div class="security-note">

                🔐 Your information is securely protected.

            </div>

        </div>

    </div>

</div>

</body>
</html>