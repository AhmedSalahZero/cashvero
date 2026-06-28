@extends('layouts.auth-cashvero')

@section('title', __('Confirm Password') . ' — CashVero')

@section('auth-header')
<div class="zav-form-header">
    <h2 class="zav-welcome">{{ __('Confirm Password') }}</h2>
    <div class="zav-welcome-line"></div>
    <p class="zav-welcome-sub">{{ __('Please confirm your password before continuing') }}</p>
</div>
@endsection

@section('content')
<form class="zav-form" method="POST" action="{{ route('password.confirm', [], false) }}">
    @csrf

    <div class="zav-field">
        <label class="zav-label" for="password">{{ __('Password') }}</label>
        <div class="zav-input-wrap">
            <input
                id="password"
                type="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                class="zav-input zav-input-pw @error('password') is-invalid @enderror"
                required
                autofocus
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

    <button type="submit" class="zav-btn-submit">
        {{ __('Confirm Password') }}
    </button>

    @if (Route::has('password.request'))
        <a href="{{ route('password.request', [], false) }}" class="zav-btn-secondary">{{ __('Forgot Your Password?') }}</a>
    @endif
</form>
@endsection

@section('auth-footer')
@include('layouts.partials.auth-cashvero-footer')
@endsection
