<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\Api\OdooService;
use App\Traits\ImageSave;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
	use ImageSave;

	/**
	 * ✅ MIGRATED to Inertia/Vue — renders resources/js/Pages/Profile/
	 * Edit.vue. Previously view('profile.form', ...). update()'s
	 * validation/business logic below is UNCHANGED except for one
	 * fix — see that method's own comment.
	 */
	public function edit()
	{
		$user = auth()->user();
		/**
		 * @var User $user
		 */
		$hasOdooCredentials = $user->companies->contains(fn (Company $company) => $company->hasOdooCredentials());

		return \Inertia\Inertia::render('Profile/Edit', [
			'user' => [
				'name' => $user->getName(),
				'email' => $user->email,
				'avatar_url' => $user->getFirstMediaUrl() ?: null,
				'odoo_username' => $user->odoo_username,
				'odoo_db_password' => $user->odoo_db_password,
			],
			'hasOdooCredentials' => $hasOdooCredentials,
			'submitUrl' => route('profile.update'),
		]);
	}

	public function update(Request $request)
	{
		$user = auth()->user();
		/**
		 * @var User $user
		 */
		$request->validate([
			'name' => ['required', 'string', 'max:255'],
			'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
			'avatar' => ['nullable', 'image'],
			'odoo_username' => ['nullable', 'string', 'max:255'],
			'odoo_db_password' => ['nullable', 'string', 'max:255'],
		]);

		$user->update($request->only('name', 'email'));

		ImageSave::saveIfExist('avatar', $user);

		$hasOdooCredentials = $user->companies->contains(fn (Company $company) => $company->hasOdooCredentials());

		if ($hasOdooCredentials) {
			$odooCredentialsChanged = $request->odoo_username !== $user->odoo_username
				|| $request->odoo_db_password !== $user->odoo_db_password;

			$user->update([
				'odoo_username' => $request->odoo_username,
				'odoo_db_password' => $request->odoo_db_password,
			]);

			if ($odooCredentialsChanged) {
				$user->update(['odoo_id' => null]);
				$this->refreshOdooId($user);
			}
		}

		// ⚠️ Was toastr()->success(...) — a separate package
		// (php-flasher/flasher-toastr-laravel) whose flash data never
		// flows into this app's real convention (session('success')/
		// ('fail'), read by AppLayout.vue's ToastStack — see that
		// component's own docblock). The confirmation was silently
		// never shown in the migrated UI. Fixed to use the same
		// convention every other page in this app already uses.
		return redirect()->back()->with('success', __('Updated Successfully'));
	}

	public function toggleTheme()
	{
		$user = auth()->user();
		/**
		 * @var User $user
		 */
		$user->dark_mode = ! $user->dark_mode;
		$user->save();

		return redirect()->back();
	}

	private function refreshOdooId(User $user): void
	{
		$odooCompany = $user->companies->first(fn (Company $company) => $company->hasOdooCredentials());

		if (!$odooCompany || !$user->getOdooDBUserName() || !$user->getOdooDBPassword()) {
			return;
		}

		try {
			new OdooService($odooCompany);
		} catch (\Exception $e) {
		}
	}
}
