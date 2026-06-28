@extends('layouts.auth-cashvero')

@section('title', __('Reset Password') . ' — CashVero')

@section('auth-header')
<div class="zav-form-header">
    <h2 class="zav-welcome">{{ __('Reset Password') }}</h2>
    <div class="zav-welcome-line"></div>
    <p class="zav-welcome-sub">{{ __('Enter your email to receive a reset link') }}</p>
</div>
@endsection

@section('content')
<form class="zav-form" method="POST" action="{{ route('password.email', [], false) }}">
    @csrf

    <div class="zav-field">
        <label class="zav-label" for="email">{{ __('Email Address') }}</label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="your@email.com"
            autocomplete="email"
            class="zav-input @error('email') is-invalid @enderror"
            required
            autofocus
        />
        @error('email')
            <span class="zav-field-error">{{ $message }}</span>
        @enderror
    </div>

    <button type="submit" class="zav-btn-submit">
        {{ __('Send Password Reset Link') }}
    </button>

    <a href="{{ route('login', [], false) }}" class="zav-btn-secondary">{{ __('Back to Sign In') }}</a>
</form>
@endsection

@section('auth-footer')
@include('layouts.partials.auth-cashvero-footer')
@endsection
