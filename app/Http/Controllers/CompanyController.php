<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySystem;
use App\Models\Partner;
use App\Models\User;
use App\Traits\ImageSave;
use Illuminate\Http\Request;

/**
 * CompanyController
 * ------------------------------------------------------------------
 * Super-admin-only, global (no {company} in the URL — this manages
 * ALL companies, not one). Route::resource('companySection', ...).
 *
 * ⚠️ destroy() exists but is genuinely UNUSED in the original — the
 * real delete mechanism the list page actually calls is a separate
 * AJAX endpoint, RemoveCompanyController (route 'remove.company'),
 * which also runs an Artisan `delete:all` cleanup command first.
 * Matched exactly: Index.vue's delete button hits that endpoint, not
 * this controller's destroy().
 *
 * ⚠️ Company name is a per-language JSON field (cast to array),
 * confirmed from the model's $casts and the original form's
 * `name[{lang}]` inputs. Only 2 languages exist in this app (English
 * "en", Arabic "ar") — hardcoded in AppServiceProvider, not a real
 * Language model/table — matched here the same way rather than
 * inventing a language-list endpoint that doesn't exist.
 *
 * ⚠️ Only 1 "system" currently exists (CompanySystem::getAllSystemNames()
 * returns just [CASH_VERO]) — the multi-select is built generically
 * to match the original, even though today it only ever has one
 * option.
 *
 * ⚠️ The per-user Odoo username/password mapping table only appears
 * when editing an existing company with users already attached —
 * matched exactly (create mode has no users yet, so it can't show).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/SuperAdmin/Companies/Index.vue and
 *      .../Form.vue.
 *   ✅ store() → Real bug fixed (see its own docblock): was
 *      `redirect()->back()`, landing the user back on the empty
 *      Create form after a successful save instead of the Companies
 *      list. Now redirects explicitly to companySection.index.
 *   ⚠️ update() → still ends in `redirect()->back()`, the same bug
 *      class as store() had — after saving, this returns to the Edit
 *      form itself (pre-filled), not the Companies list. Left
 *      UNCHANGED for now since only create() was reported broken;
 *      flagging this as the same root cause in case the project
 *      owner wants it matched too.
 *   ✅ destroy() → UNCHANGED — genuinely unused/dead in the original,
 *      left exactly as-is.
 */
class CompanyController extends Controller
{
    /**
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/SuperAdmin/Companies/Index.vue.
     */
    public function index()
    {
        $companies = Company::orderBy('id', 'desc')->get();

        return \Inertia\Inertia::render('SuperAdmin/Companies/Index', [
            'rows' => $companies->map(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? ''),
                'image_url' => $c->getFirstMediaUrl(),
                'edit_url' => route('companySection.edit', ['companySection' => $c->id]),
                'remove_image_url' => $c->getFirstMediaUrl() ? route('remove.company.image', ['lang' => app()->getLocale(), 'company' => $c->id]) : null,
            ])->values(),
            'createUrl' => route('companySection.create'),
            'removeUrl' => route('remove.company'),
        ]);
    }

    /**
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/SuperAdmin/Companies/Form.vue).
     */
    public function create()
    {
        return \Inertia\Inertia::render('SuperAdmin/Companies/Form', $this->getCommonViewVars(null));
    }

    /**
     * Stores a new Company.
     *
     * ✅ Real bug fixed here: previously ended with `redirect()->back()`,
     * which sends the browser to the HTTP Referer — for a POST from
     * the Create form, that's the Create form itself, not the
     * Companies list. The save always worked; it just looked broken,
     * since the user stayed on an empty create form with no visible
     * confirmation their company was actually created. Fixed by
     * redirecting explicitly to companySection.index, matching
     * RemoveCompanyController's already-correct pattern. Everything
     * else (systems sync, image save, tax-partner sync) UNCHANGED.
     */
    public function store(Request $request)
    {
        $request->validate([
            'opening_balance_date' => 'required|date',
        ]);

        toastr()->success('Created Successfully');
        $companySection = Company::create($request->except(['image', 'systems', 'is_api']));
        foreach ($request->get('systems') as $systemName) {
            $companySection->systems()->create([
                'system_name' => $systemName,
            ]);
        }
        if ($request->has('is_api')) {
            return $companySection;
        }
        ImageSave::saveIfExist('image', $companySection);
        Partner::handleTaxesColumnsToPartnerTable($companySection);

        return redirect()->route('companySection.index');
    }

    /**
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/SuperAdmin/Companies/Form.vue).
     */
    public function edit(Company $companySection)
    {
        return \Inertia\Inertia::render('SuperAdmin/Companies/Form', $this->getCommonViewVars($companySection));
    }

    protected function getCommonViewVars(?Company $companySection): array
    {
        return [
            'mode' => $companySection ? 'edit' : 'create',
            'model' => $companySection ? [
                'id' => $companySection->id,
                'name' => [
                    'en' => $companySection->name['en'] ?? '',
                    'ar' => $companySection->name['ar'] ?? '',
                ],
                'main_functional_currency' => $companySection->main_functional_currency,
                'opening_balance_date' => $companySection->opening_balance_date,
                'odoo_db_url' => $companySection->odoo_db_url,
                'odoo_db_name' => $companySection->odoo_db_name,
                'odoo_integration_start_date' => $companySection->odoo_integration_start_date,
                'systems' => $companySection->getSystemsNames(),
                'image_url' => $companySection->getFirstMediaUrl(),
                'users' => $companySection->users->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'odoo_username' => $u->odoo_username,
                    'odoo_db_password' => $u->odoo_db_password,
                ])->values(),
            ] : null,
            'defaultOpeningBalanceDate' => '2025-01-01',
            'systemOptions' => CompanySystem::getAllSystemNames(),
            'currencies' => getCurrencies(),
            'languages' => [
                ['code' => 'en', 'name' => 'English'],
                ['code' => 'ar', 'name' => 'Arabic'],
            ],
            'submitUrl' => $companySection
                ? route('companySection.update', ['companySection' => $companySection->id])
                : route('companySection.store'),
            'backUrl' => route('companySection.index'),
        ];
    }

    /**
     * Updates a Company. UNCHANGED, deliberately.
     */
    public function update(Request $request, Company $companySection)
    {
        $request->validate([
            'opening_balance_date' => 'required|date',
        ]);

        toastr()->success('Updated Successfully');
        $oldSystems = $companySection->getSystemsNames();
        $newSystems = $request->get('systems');
        $systemsToPreserve = array_intersect($oldSystems, $newSystems);
        $newSystemsToBeAdded = array_diff($newSystems, $oldSystems);
        $companySection->users->each(function ($user) {
            $user->update([
                'odoo_id' => null,
            ]);
        });

        foreach ($request->get('odoo_username') as $userId => $odooUsername) {
            $user = User::find($userId);
            $user->update([
                'odoo_username' => $odooUsername,
                'odoo_db_password' => $request->input('odoo_db_password.'.$userId),
            ]);
        }
        Partner::handleTaxesColumnsToPartnerTable($companySection);
        $companySection->update($request->except(['image', 'systems', 'odoo_username', 'odoo_db_password']));

        $companySection->systems()->delete();
        foreach ($newSystems as $systemName) {
            $companySection->systems()->create(['system_name' => $systemName]);
        }
        ImageSave::saveIfExist('image', $companySection);
        $companySection->syncPermissionForAllUser($systemsToPreserve, $newSystemsToBeAdded);
        toastr()->success('Updated Successfully');

        return redirect()->back();
    }

    /**
     * Genuinely unused in the original — the list page's delete
     * button calls RemoveCompanyController instead. UNCHANGED.
     */
    public function destroy(Company $companySection)
    {
        toastr()->error('Deleted Successfully');

        $companySection->delete();

        return redirect()->back();
    }
}
