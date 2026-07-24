<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ConfirmsPasswords;

class ConfirmPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Confirm Password Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password confirmations and
    | uses a simple trait to include the behavior. You're free to explore
    | this trait and override any functions that require customization.
    |
    */

    use ConfirmsPasswords;

    /**
     * ✅ MIGRATED to Inertia/Vue — renders resources/js/Pages/Auth/
     * ConfirmPassword.vue. confirm() (the rest of this trait) is
     * UNCHANGED.
     */
    public function showConfirmForm()
    {
        return \Inertia\Inertia::render('Auth/ConfirmPassword', [
            'passwordConfirmUrl' => route('password.confirm'),
        ]);
    }
  
    public function redirectTo()
    {
        return route('home');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
}
