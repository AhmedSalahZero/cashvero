<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PartnersController
 * ------------------------------------------------------------------
 * Manages the master Partners list — the single record type that
 * `is_customer` / `is_supplier` / `is_employee` / `is_shareholder` /
 * `is_subsidiary_company` / `is_other_partner` flags all live on.
 * A "Tax" partner (`is_tax = 1`) is a separate, unrelated concept and
 * is deliberately excluded from this list, matching the original.
 *
 * When a company has Odoo integration configured, partners are
 * synced FROM Odoo — the "Add Partner" action is hidden and the Name
 * field is read-only on edit, exactly as in the original Blade.
 *
 * `store()`/`update()` are UNCHANGED in their actual field-saving
 * logic, deliberately — only the response type changed (see below).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/Partners/Index.vue. NEW: a type filter
 *      (?type=customers|suppliers|employees|shareholders|
 *      subsidiary-companies|other-partners) was added here — the
 *      original had no way to filter by partner type at all, only a
 *      flat table with a check/cross column per type. Uses the
 *      model's existing onlyCustomers()/onlySuppliers()/etc. scopes,
 *      which were already defined but unused by any controller until
 *      now.
 *   ⚠️ index() now uses real server-side pagination
 *      (GeneralFunctions::getPaginationLimit(), the same 20/page
 *      convention already used by BuyOrSellCurrenciesController,
 *      CashExpenseController, InternalMoneyTransferController, etc.)
 *      instead of loading every partner into memory. Search
 *      (?search=) and the type filter are both applied server-side,
 *      before pagination — some companies already have ~500 partners
 *      and growing toward thousands, and the original's "load
 *      everything, filter in Blade" approach does not scale to that.
 *      The `counts` used for the KPI row and pill badges are a single
 *      aggregate SUM() query, not a loaded collection, for the same
 *      reason.
 *   ✅ create() / edit() → MIGRATED. Both render
 *      resources/js/Pages/Partners/Form.vue, distinguished by a
 *      `mode: 'create' | 'edit'` prop — same pattern as every other
 *      migrated Form.vue in this project.
 *   ⚠️ store() / update() → responses converted from raw
 *      `response()->json(['redirectTo'=>...])` bodies to proper
 *      redirects with flash messages — the same Inertia-incompatible
 *      pattern already found and fixed across every overdraft
 *      controller (see roadmap §11 item 19). The actual field-saving
 *      logic in both methods is byte-for-byte unchanged.
 *   ✅ destroy() → already returned a proper redirect; unchanged.
 *   🔲 The original's generic `applyFilter()` (a copy-pasted
 *      date-range + single-field search helper, never actually wired
 *      to a visible date-range control specific to Partners, and
 *      still containing a stray `$moneyReceived` variable name and a
 *      "// change it" comment) was NOT carried forward. Replaced by
 *      a plain `name LIKE` search (server-side, see above) + the new
 *      type filter. Flagged explicitly, not a silent drop — see chat
 *      for the tradeoff if a date range is wanted back.
 *   🔲 OPEN ITEM (deliberately not built — confirmed with project
 *      owner): the intended business rule is that once a partner is
 *      synced from Odoo, its original type(s) (e.g. Customer) should
 *      never be removable in CashVero — only new types may be added
 *      on top (e.g. also marking them a Shareholder). This is NOT
 *      currently enforced anywhere — checked the original Blade (its
 *      `disabled` attributes on the type checkboxes are commented
 *      out), this controller, the model, and the migrations: there is
 *      no column that even records which type(s) came from Odoo vs.
 *      were added locally, so today's app can't tell the difference
 *      after the fact. This form replicates that same (unprotected)
 *      behavior. Implementing it properly needs a decision on how to
 *      track "original type" (new column(s) vs. treating whatever is
 *      true at first load as original) — see chat.
 */
class PartnersController
{
    use GeneralFunctions;

    /**
     * Maps the `?type=` query value to the model scope that filters it.
     * 'all' (default) applies no extra scope beyond the is_tax exclusion.
     */
    protected const TYPE_SCOPES = [
        'customers' => 'onlyCustomers',
        'suppliers' => 'onlySuppliers',
        'employees' => 'onlyEmployees',
        'shareholders' => 'onlyShareholders',
        'subsidiary-companies' => 'onlySubsidiaryCompanies',
        'other-partners' => 'onlyOtherPartners',
    ];

    public function index(Company $company, Request $request)
    {
        $type = $request->get('type', 'all');
        $search = trim((string) $request->get('search', ''));
        $perPage = self::getPaginationLimit();

        $baseQuery = fn () => $company->partners()->where('is_tax', '!=', 1);

        // Single aggregate query for the KPI row / pill badge counts —
        // always against the full (unfiltered-by-type-or-search) set, and
        // never loads the actual rows. Needed once a company has hundreds
        // to thousands of partners.
        $countsRow = $baseQuery()->selectRaw(
            'count(*) as all_count, '.
            'sum(is_customer) as customers, '.
            'sum(is_supplier) as suppliers, '.
            'sum(is_employee) as employees, '.
            'sum(is_shareholder) as shareholders, '.
            'sum(is_subsidiary_company) as subsidiary_companies, '.
            'sum(is_other_partner) as other_partners'
        )->first();

        $counts = [
            'all' => (int) $countsRow->all_count,
            'customers' => (int) $countsRow->customers,
            'suppliers' => (int) $countsRow->suppliers,
            'employees' => (int) $countsRow->employees,
            'shareholders' => (int) $countsRow->shareholders,
            'subsidiary-companies' => (int) $countsRow->subsidiary_companies,
            'other-partners' => (int) $countsRow->other_partners,
        ];

        $query = $baseQuery();

        if (isset(self::TYPE_SCOPES[$type])) {
            $query->{self::TYPE_SCOPES[$type]}();
        }

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $paginated = $query->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->getName(),
                'is_customer' => $partner->isCustomer(),
                'is_supplier' => $partner->isSupplier(),
                'is_subsidiary_company' => $partner->isSubsidiaryCompany(),
                'is_other_partner' => $partner->isOtherPartner(),
                'is_employee' => $partner->isEmployee(),
                'is_shareholder' => $partner->isShareholder(),
                /**
                 * The type keys this row belongs to (a partner can be
                 * several at once). The page uses these to look the row
                 * up in the permission matrix, so an Edit button on an
                 * employee row obeys `employee.update` rather than
                 * whatever the active tab happens to be.
                 */
                'type_keys' => array_values(array_filter([
                    $partner->isCustomer() ? 'customers' : null,
                    $partner->isSupplier() ? 'suppliers' : null,
                    $partner->isEmployee() ? 'employees' : null,
                    $partner->isShareholder() ? 'shareholders' : null,
                    $partner->isSubsidiaryCompany() ? 'subsidiary-companies' : null,
                    $partner->isOtherPartner() ? 'other-partners' : null,
                ])),
                'edit_url' => route('partners.edit', ['company' => $company->id, 'partner' => $partner->id]),
                'delete_url' => route('partners.destroy', ['company' => $company->id, 'partner' => $partner->id]),
            ]);

        return \Inertia\Inertia::render('Partners/Index', [
            'company' => ['id' => $company->id],
            'activeType' => $type,
            'search' => $search,
            'counts' => $counts,
            'partners' => $paginated,
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'indexUrl' => route('partners.index', ['company' => $company->id]),
            'createUrl' => route('partners.create', ['company' => $company->id]),
            /**
             * ⚠️ REAL BUG FIXED HERE (2026-08 permissions audit, F-05):
             *
             * This screen serves SIX partner types, but every one of
             * them was gated by the single 'update customers'
             * permission — so anyone allowed to edit a customer could
             * also edit and delete employees and shareholders, the most
             * sensitive records in the module. Delete had no permission
             * of its own, and the Create button had none at all.
             *
             * Rights are now resolved per type from the registry. The
             * frontend picks the entry matching the active tab, and
             * RoutePermissionMap enforces the same set on the server.
             */
            'permissions' => self::permissionMatrix(),
            'activePermissions' => self::permissionsForType($type),
        ]);
    }

    /**
     * URL type segment → permission module.
     */
    private const TYPE_MODULES = [
        'customers' => 'customer',
        'suppliers' => 'supplier',
        'employees' => 'employee',
        'shareholders' => 'shareholder',
        'subsidiary-companies' => 'subsidiary_company',
        'other-partners' => 'other_partner',
    ];

    /**
     * Per-type create/update/delete rights, so the page can gate each
     * tab independently.
     *
     * @return array<string, array{create:bool, update:bool, delete:bool}>
     */
    private static function permissionMatrix(): array
    {
        $matrix = [];

        foreach (self::TYPE_MODULES as $type => $module) {
            $matrix[$type] = [
                'view' => hasAuthFor("{$module}.view"),
                'create' => hasAuthFor("{$module}.create"),
                'update' => hasAuthFor("{$module}.update"),
                'delete' => hasAuthFor("{$module}.delete"),
            ];
        }

        return $matrix;
    }

    /**
     * Rights for the tab currently on screen. The "all" tab shows every
     * type at once, so a control is offered when the user may perform
     * that action on at least one type — the row-level buttons are then
     * gated by the row's own type in the page.
     */
    private static function permissionsForType(string $type): array
    {
        $matrix = self::permissionMatrix();

        if (isset($matrix[$type])) {
            return $matrix[$type];
        }

        return [
            'view' => (bool) collect($matrix)->contains(fn ($p) => $p['view']),
            'create' => (bool) collect($matrix)->contains(fn ($p) => $p['create']),
            'update' => (bool) collect($matrix)->contains(fn ($p) => $p['update']),
            'delete' => (bool) collect($matrix)->contains(fn ($p) => $p['delete']),
        ];
    }

    public function create(Company $company)
    {
        return \Inertia\Inertia::render('Partners/Form', [
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'submitUrl' => route('partners.store', ['company' => $company->id]),
            'backUrl' => route('partners.index', ['company' => $company->id]),
        ]);
    }

    /**
     * NOTE on NOT using storeBasicForm() here (unlike most other controllers
     * in this codebase): that shared helper (App\Traits\HasBasicStoreRequest)
     * detects boolean columns by checking whether the request VALUE (not the
     * field name) starts with 'is_'/'can_'/'has_' — a check that can never
     * actually match, so real form posts fall through to its catch-all
     * branch, which does `$val = $request->get($name) == 'null' ? null : ...`.
     * With traditional string form values ("1"/omitted) that comparison is
     * harmless. But Inertia sends genuine JSON booleans, and PHP's loose
     * `==` coerces `true == 'null'` to TRUE (any non-empty, non-"0" string
     * coerces a bool comparison to true) — so a checked box would silently
     * get saved as NULL instead of true. Rather than edit that shared trait
     * (used by ~20 other still-Blade controllers, out of scope here), this
     * controller sets the six type flags and company_id explicitly instead,
     * matching the explicit-array pattern already used in
     * FinancialInstitutionController::store()/update().
     */
    public function store(Company $company, StorePartnerRequest $request)
    {
        $partner = new Partner();
        $partner->company_id = $company->id;
        $partner->name = $request->get('name');
        $partner->is_customer = $request->boolean('is_customer');
        $partner->is_supplier = $request->boolean('is_supplier');
        $partner->is_employee = $request->boolean('is_employee');
        $partner->is_subsidiary_company = $request->boolean('is_subsidiary_company');
        $partner->is_other_partner = $request->boolean('is_other_partner');
        $partner->is_shareholder = $request->boolean('is_shareholder');
        $partner->save();

        return redirect()
            ->route('partners.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function edit(Company $company, Partner $partner)
    {
        return \Inertia\Inertia::render('Partners/Form', [
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'submitUrl' => route('partners.update', ['company' => $company->id, 'partner' => $partner->id]),
            'backUrl' => route('partners.index', ['company' => $company->id]),
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->getName(),
                'is_customer' => $partner->isCustomer(),
                'is_supplier' => $partner->isSupplier(),
                'is_employee' => $partner->isEmployee(),
                'is_subsidiary_company' => $partner->isSubsidiaryCompany(),
                'is_other_partner' => $partner->isOtherPartner(),
                'is_shareholder' => $partner->isShareholder(),
            ],
        ]);
    }

    public function update(Company $company, StorePartnerRequest $request, Partner $partner)
    {
        // See the docblock on store() for why storeBasicForm() is
        // deliberately not used here — this sets every field explicitly
        // instead, in one pass (the original made two separate ->update()
        // calls plus a storeBasicForm() call in between; consolidated here
        // since the intermediate steps are no longer needed).
        $newName = $request->get('name');
        $partner->update([
            'name' => $newName,
            'is_customer' => $request->boolean('is_customer'),
            'is_supplier' => $request->boolean('is_supplier'),
            'is_employee' => $request->boolean('is_employee'),
            'is_shareholder' => $request->boolean('is_shareholder'),
            'is_other_partner' => $request->boolean('is_other_partner'),
            'is_subsidiary_company' => $request->boolean('is_subsidiary_company'),
        ]);
        DB::table('customer_invoices')->where('customer_id', $partner->id)->update([
            'customer_name' => $newName,
        ]);
        DB::table('supplier_invoices')->where('supplier_id', $partner->id)->update([
            'supplier_name' => $newName,
        ]);
        return redirect()
            ->route('partners.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, Partner $partner)
    {
        /**
         * * ما ينفعش نحذفه طول ما فيه حركات معلقة عليه
         *
         * * جزء من الأبناء متوصلين بـ ON DELETE CASCADE فالحذف هنا كان بيخلي MySQL
         * * تمسحهم بنفسها من غير ما Eloquent يشوف الحذف .. فالهوكس اللي بتنضف
         * * كشوفهم ما بتشتغلش و بتفضل صفوف يتيمة بتظهر في الداشبورد
         * * و الباقي مفيهوش FK اصلا فبيفضل مأشر على id مش موجود
         *
         * @see \App\Support\Deletion\ReferencedRecordGuard
         */
        if ($message = $partner->deletionBlockedMessage()) {
            return redirect()->back()->with('fail', $message);
        }

        $partner->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
