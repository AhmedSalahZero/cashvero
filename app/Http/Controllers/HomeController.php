<?php

namespace App\Http\Controllers;


use App\Jobs\CheckDueAndPastedInvoicesJob;
use App\Jobs\ImportForeignExchangeRates;
use App\Jobs\ReactiveCurrentAccountStatement;
use App\Models\Company;

use App\Models\User;
use App\Traits\GeneralFunctions;
use Auth;
use Illuminate\Http\Request;

/**
 * HomeController
 * ------------------------------------------------------------------
 * The post-login landing flow. Confirmed by tracing every route:
 *   - 0 companies  → logs the user out, redirects to login.
 *   - 1 company    → redirects straight to the Cash Status dashboard
 *                     for that company (project-owner requested change
 *                     — previously rendered an intermediate
 *                     "homePage"/"Where You Want To Go" panel first;
 *                     now skips it entirely, same destination
 *                     redirectFun() already sends a picked company to).
 *   - 2+ companies → renders the company-picker page. Clicking a
 *                     company card goes straight to
 *                     redirectFun() → the Cash Status dashboard —
 *                     same destination as the 1-company path above,
 *                     just reached one click later. Consistent by
 *                     design now, not an asymmetry.
 *
 * ⚠️ Confirmed genuinely dead code, NOT touched: welcomePage() — the
 * method that dispatches sync jobs (ReactiveCurrentAccountStatement,
 * CheckDueAndPastedInvoicesJob, ImportForeignExchangeRates) — has NO
 * route pointing to it anywhere in this app. It is never reachable.
 * The single-company path in index() does not call it and never has.
 * Left completely untouched, unrouted, exactly as in the original.
 *
 * ⚠️ resources/js/Pages/Home/Dashboard.vue (the old per-company
 * "Where You Want To Go" panel — Upload Customer/Supplier Invoices +
 * Go To Cash Vero links) is no longer reachable from index() after
 * this change, but is deliberately left in place, unlinked — same
 * "leave old code registered, just unlinked" approach used everywhere
 * else in this migration, in case it's ever wanted again.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia for the 2+ companies branch
 *      (renders resources/js/Pages/Home/CompanyPicker.vue). The
 *      1-company branch is now a plain redirect (see above), and the
 *      0-companies branch is unchanged (logout + redirect, not a page).
 *   ✅ redirectFun() → UNCHANGED, deliberately — a plain redirect, not
 *      a page.
 *   ⚪ welcomePage() → untouched, confirmed dead/unrouted. Now redirects
 *      to Cash Status (Blade homePage removed with dashboard layout).
 */
class HomeController extends Controller
{
	use GeneralFunctions;

	public function index(Request $request)
	{
		$user = Auth::user();
		/**
		 * @var User $user
		 */
		$companies = $user->companies;

		/**
		 * An account with no permissions at all.
		 *
		 * This is a real state, not an edge case: with user-based
		 * permissions a newly created user holds nothing until an
		 * administrator configures them. Falling through would redirect
		 * them straight into a dashboard they cannot open, so they'd meet
		 * a bare 403 with no explanation and nowhere to go.
		 *
		 * Show them what actually happened instead. Nothing here is
		 * sensitive — it is the absence of access being reported.
		 */
		if (\App\Support\Permissions\PermissionResolver::grantedKeys($user) === []) {
			return \Inertia\Inertia::render('Home/NoAccess', [
				'userName' => $user->getName(),
				'roleName' => $user->getRoleName(),
			]);
		}

		if (count($companies) > 1) {
			return \Inertia\Inertia::render('Home/CompanyPicker', [
				'companies' => $companies->map(fn (Company $c) => [
					'id' => $c->id,
					'name' => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? ''),
					'image_url' => $c->getFirstMediaUrl(),
					'go_url' => route('home.redirect', ['company' => $c->id]),
				])->values(),
			]);
		} else {
			if (count($companies) == 0) {
				auth()->logout();
				return redirect()->route('login');
			}
			$company = $companies[0];
			return redirect()->route('view.customer.invoice.dashboard.cash', ['company' => $company->id]);
		}
	}

	/**
	 * UNCHANGED, deliberately — a plain redirect, not a page.
	 */
	public function redirectFun(Company $company)
	{
		return redirect()->route('view.customer.invoice.dashboard.cash', [$company]);
	}

	/**
	 * ⚪ Confirmed genuinely dead code — no route anywhere points to
	 * this method. Blade homePage removed; redirects to Cash Status.
	 */
	public function welcomePage(Request $request, Company $company)
	{
		if($company->hasCashVero()){
			dispatch_sync(new ReactiveCurrentAccountStatement($company->id));
		}
		if($company->hasCashVero()){
			dispatch_sync(new CheckDueAndPastedInvoicesJob($company->id));
		}
		if($company->hasOdooIntegrationCredentials()){
			dispatch_sync(new ImportForeignExchangeRates($company->id));
		}
		
		return redirect()->route('view.customer.invoice.dashboard.cash', [$company]);
	}




	
	
}
