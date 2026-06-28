@extends('layouts.auth-cashvero')

@section('title', __('Register') . ' — CashVero')

@section('auth-header')
<div class="zav-form-header">
    <h2 class="zav-welcome">{{ __('Create account') }}</h2>
    <div class="zav-welcome-line"></div>
    <p class="zav-welcome-sub">{{ __('Register to access CashVero') }}</p>
</div>
@endsection

@section('content')
<form class="zav-form" method="POST" action="{{ route('register', [], false) }}">
    @csrf

    <div class="zav-field">
        <label class="zav-label" for="name">{{ __('Name') }}</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name') }}"
            class="zav-input @error('name') is-invalid @enderror"
            required
            autofocus
            autocomplete="name"
        />
        @error('name')
            <span class="zav-field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="zav-field">
        <label class="zav-label" for="email">{{ __('Email Address') }}</label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="your@email.com"
            class="zav-input @error('email') is-invalid @enderror"
            required
            autocomplete="email"
        />
        @error('email')
            <span class="zav-field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="zav-field">
        <label class="zav-label" for="password">{{ __('Password') }}</label>
        <div class="zav-input-wrap">
            <input
                id="password"
                type="password"
                name="password"
                placeholder="••••••••"
                class="zav-input zav-input-pw @error('password') is-invalid @enderror"
                required
                autocomplete="new-password"
            />
            <button
                type="button"
                class="zav-eye-btn"
                tabindex="-1"
                data-zav-password-toggle
                data-target-input="password"
                data-icon-open="iconEyeOpen"
                data-icon-closed="iconEyeClosed"
            >
                <svg id="iconEyeOpen" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="iconEyeClosed" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
        @error('password')
            <span class="zav-field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="zav-field">
        <label class="zav-label" for="password-confirm">{{ __('Confirm Password') }}</label>
        <div class="zav-input-wrap">
            <input
                id="password-confirm"
                type="password"
                name="password_confirmation"
                placeholder="••••••••"
                class="zav-input zav-input-pw"
                required
                autocomplete="new-password"
            />
            <button
                type="button"
                class="zav-eye-btn"
                tabindex="-1"
                data-zav-password-toggle
                data-target-input="password-confirm"
                data-icon-open="iconEyeOpenConfirm"
                data-icon-closed="iconEyeClosedConfirm"
            >
                <svg id="iconEyeOpenConfirm" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="iconEyeClosedConfirm" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
    </div>

    <button type="submit" class="zav-btn-submit">
        {{ __('Register') }}
    </button>

    <a href="{{ route('login', [], false) }}" class="zav-btn-secondary">{{ __('Back to Sign In') }}</a>
</form>
@endsection

@section('auth-footer')
@include('layouts.partials.auth-cashvero-footer')
@endsection
