<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Providers\RouteServiceProvider;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
class LoginController extends Controller
{
    use AuthenticatesUsers;

	/**
	 * ✅ MIGRATED to Inertia/Vue — renders
	 * resources/js/Pages/Auth/Login.vue, a pixel-faithful rebuild of
	 * the existing 'auth-cashvero' Blade design (left branding panel +
	 * right sign-in form) — same layout, same colors, same copy,
	 * nothing restyled. Session flash keys ('status', 'expired-login')
	 * are passed explicitly since this standalone guest page doesn't
	 * go through AppLayout.vue's shared flash-toast mechanism the way
	 * every authenticated page does.
	 */
	public function showLoginForm()
    {
        return \Inertia\Inertia::render('Auth/Login', [
            'status' => session('status'),
            'expiredLogin' => session('expired-login'),
            'loginUrl' => route('login'),
            'passwordRequestUrl' => route('password.request'),
        ]);
    }
    public function redirectTo()
    {
        return route('home');
    }

	/**
	 * ⚠️ Real bug fixed here: Laravel's default sendLoginResponse()
	 * (from the AuthenticatesUsers trait) falls back to
	 * redirect()->intended($this->redirectPath()) — which honors
	 * whatever URL was stored in the session as "intended" (set by
	 * the auth middleware whenever a guest tries to visit a protected
	 * page and gets bounced to the login form) BEFORE falling back to
	 * redirectTo() above. That stored URL has nothing to do with which
	 * user is actually logging in — it's just whatever the browser/
	 * session last tried to reach. Confirmed as the cause of a real,
	 * reported bug: a brand-new, single-company user's first login
	 * landed on the Super Admin Users table, simply because that was
	 * the page open in the browser right before logging in (e.g. an
	 * admin viewing Users, logging out, then logging in as the new
	 * user in the same browser/session). Overriding authenticated()
	 * here short-circuits sendLoginResponse() before it ever reaches
	 * redirect()->intended() (see the trait: `if ($response =
	 * $this->authenticated(...)) { return $response; }`), so every
	 * successful login now unconditionally goes through
	 * HomeController::index()'s own routing decision (1 company →
	 * Cash Status dashboard, 2+ → Company Picker, 0 → logged out),
	 * exactly as intended, regardless of any stale "intended" URL.
	 */
	protected function authenticated(Request $request, $user)
	{
		return redirect()->route('home');
	}
	
	public function login(Request $request)
    {
		
        $this->validateLogin($request);

   
        if (
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
			if($request->user()->AccountExpired()){
				session()->put('expired-login','Your Free Trail Has Been Expired .. Please Subscribe');
				return redirect()->route('login');
			}
			session()->forget('expired-login');
            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
