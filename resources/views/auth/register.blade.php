<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Register - Catering Service</title>
    @include('partials.favicon')

    <link
        rel="stylesheet"
        href="{{ asset('css/style.css') }}"
    >

</head>

<body class="auth-page">

<div class="auth-wrapper">


    <!-- LEFT SIDE -->

    <div class="auth-showcase register-showcase">

        <div class="brand">

            <div class="brand-icon">
                🍽️
            </div>

            <div>

                <h2>
                    Cater<span>Ease</span>
                </h2>

                <small>
                    CATERING SERVICE
                </small>

            </div>

        </div>


        <div class="showcase-content">

            <div class="food-circle">
                👨‍🍳
            </div>

            <h1>
                Let's create<br>
                <span>something special.</span>
            </h1>

            <p>
                Join CaterEase and easily plan your next
                celebration with our professional catering service.
            </p>


            <div class="features">

                <div>
                    <b>✓</b>
                    <span>Easy Online Booking</span>
                </div>

                <div>
                    <b>✓</b>
                    <span>Choose Your Package</span>
                </div>

                <div>
                    <b>✓</b>
                    <span>Track Your Events</span>
                </div>

            </div>

        </div>


        <div class="showcase-footer">

            © {{ date('Y') }} CaterEase Catering Service

        </div>

    </div>


    <!-- RIGHT SIDE -->

    <div class="auth-form-area">

        <div class="auth-box register-box">


            <div class="mobile-logo">
                🍽️ Cater<span>Ease</span>
            </div>


            <div class="form-heading">

                <span class="welcome-label">
                    GET STARTED
                </span>

                <h1>
                    Create your account
                </h1>

                <p>
                    Register as a customer and start planning
                    your special event.
                </p>

            </div>


            {{-- Validation Errors --}}

            @if($errors->any())

                <div class="error-message">

                    <strong>
                        Please check the following:
                    </strong>

                    @foreach($errors->all() as $error)

                        <p>
                            • {{ $error }}
                        </p>

                    @endforeach

                </div>

            @endif


            <form
                action="{{ route('register.submit') }}"
                method="POST"
            >

                @csrf


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            👤
                        </span>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Enter your full name"
                            autocomplete="off"
                            required
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label for="phone">Mobile Number <span style="font-weight:400;color:#9ca3af">(optional, for event reminders)</span></label>

                    <div class="input-wrapper">

                        <span class="input-icon">📱</span>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="e.g. 0917 123 4567"
                            autocomplete="tel"
                        >

                    </div>

                </div>


                <!-- EMAIL -->

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


                <!-- PASSWORD -->

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
                            placeholder="Create a password"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="password_confirmation">
                        Confirm Password
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            🔐
                        </span>

                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <label class="terms">

                    <input
                        type="checkbox"
                        required
                    >

                    <span>
                        I agree to the
                        <a href="#">Terms & Conditions</a>
                    </span>

                </label>


                <button
                    type="submit"
                    class="auth-button"
                >

                    Create Account

                    <span>→</span>

                </button>

            </form>


            <div class="divider">
                <span>OR</span>
            </div>


            <p class="auth-link">

                Already have an account?

                <a href="{{ route('login') }}">
                    Sign in
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