<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * ✅ MIGRATED to Inertia/Vue — renders resources/js/Pages/Auth/
     * ForgotPassword.vue. sendResetLinkEmail() (the rest of this
     * trait) is UNCHANGED — it already flashes `status` on success,
     * which this page reads exactly the way Login.vue already reads
     * `status`/`expiredLogin`.
     */
    public function showLinkRequestForm()
    {
        return \Inertia\Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'passwordEmailUrl' => route('password.email'),
            'loginUrl' => route('login'),
        ]);
    }
}
