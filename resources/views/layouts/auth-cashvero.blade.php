<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Sign In — CashVero')</title>
    <link rel="shortcut icon" href="{{ secure_asset('assets/media/logos/logo_va.png') }}" />
    @include('layouts.partials.auth-cashvero-styles')
    @stack('styles')
</head>
<body>
@php
    $authLogo = secure_asset('images/cashvero-logo.png');
@endphp
<div class="zav-root">
    @include('layouts.partials.auth-cashvero-left')

    <div class="zav-right">
        <div class="zav-logo-area">
            <img src="{{ $authLogo }}" alt="CashVero" class="zav-logo-img" />
        </div>

        <div class="zav-form-wrap">
            <div class="zav-form-inner">
                <div class="mobile-logo">
                    <img src="{{ $authLogo }}" alt="CashVero" />
                </div>

                @if (session('status'))
                    <div class="zav-alert zav-alert-success" role="alert">{{ session('status') }}</div>
                @endif

                @if (session('expired-login'))
                    <div class="zav-alert zav-alert-danger" role="alert">{{ session('expired-login') }}</div>
                @endif

                @if ($errors->any())
                    <div class="zav-alert zav-alert-danger" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @hasSection('auth-header')
                    @yield('auth-header')
                @endif

                @yield('content')

                @hasSection('auth-footer')
                    @yield('auth-footer')
                @endif
            </div>
        </div>
    </div>
</div>

@include('layouts.partials.auth-cashvero-scripts')
@stack('scripts')
</body>
</html>
