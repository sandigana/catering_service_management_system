@extends('layouts.customer')

@section('title', 'My Profile')

@section('content')

<div class="c-head">
    <div>
        <h1>My Profile</h1>
        <p>Update your name, email or password</p>
    </div>
</div>

<div class="c-card" style="max-width:640px;">
    <form action="{{ route('customer.profile.update') }}" method="POST" class="c-form">
        @csrf
        @method('PUT')

        <div class="full">
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
        </div>

        <div class="full">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
        </div>

        <div class="full">
            <label for="phone">Mobile number (used for event reminders)</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 0917 123 4567">
        </div>

        <div>
            <label for="password">New password (optional)</label>
            <input id="password" type="password" name="password" autocomplete="new-password">
        </div>

        <div>
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
        </div>

        <div class="full">
            <button type="submit" class="c-btn">Save changes</button>
        </div>
    </form>
</div>

@endsection
