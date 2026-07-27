<?php

declare(strict_types=1);

use Flasher\Prime\Configuration;

/*
 * CashVero uses Inertia + AppLayout ToastStack for flash messages.
 *
 * ⚠️ CRITICAL: php-flasher's default flash_bag mapping steals Laravel's
 * session('success') / session('error') on every response (converts them
 * into flasher::envelopes and session()->forget()'s the original keys).
 * That is why Inertia pages saw success toasts only on the *next*
 * navigation — by the time HandleInertiaRequests read session('success')
 * on the redirected GET, Flasher\Laravel\Middleware\SessionMiddleware had
 * already consumed it on the preceding redirect response.
 *
 * flash_bag => false disables that conversion. Controllers that still call
 * toastr()/flash() write directly to flasher envelopes and keep working;
 * controllers that use redirect()->with('success'|'fail', ...) keep their
 * session keys so Inertia can share them (and bridge into Inertia::flash()).
 */
return Configuration::from([
    'default' => 'flasher',
    'main_script' => '/vendor/flasher/flasher.min.js',
    'public_path' => '',
    'styles' => [
        '/vendor/flasher/flasher.min.css',
    ],
    'inject_assets' => true,
    'translate' => true,
    'excluded_paths' => [],
    'flash_bag' => false,
]);
