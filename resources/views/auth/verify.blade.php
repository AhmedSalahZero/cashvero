@extends('layouts.auth-cashvero')

@section('title', __('Verify Email') . ' — CashVero')

@section('auth-header')
<div class="zav-form-header">
    <h2 class="zav-welcome">{{ __('Verify Your Email') }}</h2>
    <div class="zav-welcome-line"></div>
    <p class="zav-welcome-sub">{{ __('Check your inbox for the verification link') }}</p>
</div>
@endsection

@section('content')
<div class="zav-form">
    <p class="zav-welcome-sub" style="margin-bottom: 1.25rem; line-height: 1.7;">
        {{ __('Before proceeding, please check your email for a verification link.') }}
    </p>

    @if (session('resent'))
        <div class="zav-alert zav-alert-success" role="alert">
            {{ __('A fresh verification link has been sent to your email address.') }}
        </div>
    @endif

    <p class="zav-welcome-sub" style="margin-bottom: 1rem;">
        {{ __('If you did not receive the email') }},
    </p>

    <form method="POST" action="{{ route('verification.resend', [], false) }}">
        @csrf
        <button type="submit" class="zav-btn-submit">
            {{ __('Resend verification email') }}
        </button>
    </form>

    <a href="{{ route('login', [], false) }}" class="zav-btn-secondary">{{ __('Back to Sign In') }}</a>
</div>
@endsection

@section('auth-footer')
@include('layouts.partials.auth-cashvero-footer')
@endsection
