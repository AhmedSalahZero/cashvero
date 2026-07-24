<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * ✅ MIGRATED to Inertia/Vue — renders resources/js/Pages/Auth/
     * ResetPassword.vue. Same (Request, $token = null) signature and
     * same token/email extraction Laravel's default trait body
     * already used — reset() itself (password update logic) is
     * UNCHANGED.
     */
    public function showResetForm(Request $request, $token = null)
    {
        return \Inertia\Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->email,
            'passwordUpdateUrl' => route('password.update'),
            'loginUrl' => route('login'),
        ]);
    }

    public function redirectTo()
    {
        return route('home');
    }
}
