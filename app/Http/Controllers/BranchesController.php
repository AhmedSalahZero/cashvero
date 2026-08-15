<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Models\CashVeroBranch;
use App\Models\Company;
use App\Repositories\SafeRepository;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * BranchesController
 * ------------------------------------------------------------------
 * "Safe Accounts" in the sidebar — confirmed to be this exact
 * feature. ⚠️ Uses App\Models\CashVeroBranch, NOT App\Models\Branch —
 * both point to the same `branch` table, but CashVeroBranch is the
 * one actually used throughout this controller (route model binding,
 * $company->branches, the BRANCHES constant); Branch appears to be a
 * legacy/duplicate class. Matched exactly here.
 *
 * ⚠️ The create form is a REPEATER, not a single-record form — a
 * company can create several safes in one submission (each row: Name
 * + Currency, plus a Chart Of Account Number/odoo_code field only
 * when the company has Odoo integration configured). The edit form
 * only ever shows one row (no "Repeat" button in the original when
 * editing) — matched exactly.
 *
 * ⚠️ Confirmed real bug, NOT present in this fixed version: the
 * original store()/update() returned a raw JSON body
 * (`{'redirectTo' => ...}`) instead of an HTTP redirect — same
 * category of bug already found and fixed on Factoring Contract, LC
 * Issuance's updateExpense(), and LG Issuance's
 * editAmountToBeDecreased(). Fixed here by returning a normal
 * redirect() response instead; financial/Odoo-sync logic UNCHANGED.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/SafeAccounts/Index.vue and
 *      .../Form.vue.
 *   ⚠️ store() / update() → response type fixed (JSON → redirect, see
 *      above). SafeRepository::store() call, Odoo sync
 *      (OdooService::syncBranchSafe()) UNCHANGED.
 *   ✅ destroy() / getBranchesForCurrency() → UNCHANGED, deliberately.
 *      Already redirect/JSON-appropriately; getBranchesForCurrency()
 *      is a pure AJAX data endpoint used elsewhere, not touched here.
 */
class BranchesController
{
    use GeneralFunctions;

    protected function applyFilter(Request $request, Collection $collection): Collection
    {
        if (!count($collection)) {
            return $collection;
        }

        $searchFieldName = $request->get('field');
        $dateFieldName = 'created_at';
        $from = $request->get('from');
        $to = $request->get('to');
        $value = $request->query('value');

        return $collection
            ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
                return $collection->filter(function ($item) use ($value, $searchFieldName) {
                    $currentValue = $item->{$searchFieldName};

                    return false !== stristr((string) $currentValue, (string) $value);
                });
            })
            ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
                return $collection->where($dateFieldName, '>=', $from);
            })
            ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
                return $collection->where($dateFieldName, '<=', $to);
            });
    }

    /**
     * The main "Safe Accounts" list — one flat list, no tabs (matches
     * the original exactly — it only ever had one tab, "Safe").
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/SafeAccounts/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        // NOTE: Safe Accounts are master data, not date-scoped
        // transactions, and this page has no date-range control in
        // the UI (SafeAccounts/Index.vue). A previous default
        // created_at filter (last 18 months) was silently hiding
        // older safes from the list — removed. If a genuine need for
        // date filtering surfaces later, it should come with a
        // visible filter control on the page, not a silent default.
        $branches = $company->branches;
        $branches = $this->applyFilter($request, $branches);

        return \Inertia\Inertia::render('SafeAccounts/Index', [
            'company' => ['id' => $company->id],
            'canCreate' => hasAuthFor('create branches'),
            'createUrl' => route('branches.create', ['company' => $company->id]),
            'rows' => $branches->map(function (CashVeroBranch $branch) use ($company) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->getName(),
                    'currency' => $branch->getCurrencyName(),
                    'created_at_formatted' => $branch->getCreatedAtFormatted(),
                    'edit_url' => route('branches.edit', ['company' => $company->id, 'branch' => $branch->id]),
                    'delete_url' => route('branches.destroy', ['company' => $company->id, 'branch' => $branch->id]),
                ];
            })->values(),
            'canUpdate' => hasAuthFor('update branches'),
            'canDelete' => hasAuthFor('delete branches'),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Shows the "Add Safe" form — a repeater, so several safes can be
     * created in one submission.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/SafeAccounts/Form.vue), distinguished
     * by the `mode: 'create'` prop.
     */
    public function create(Company $company)
    {
        return \Inertia\Inertia::render('SafeAccounts/Form', [
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'currencies' => getCurrencies(),
            'hasOdoo' => $company->hasOdooIntegrationCredentials(),
            'model' => null,
            'submitUrl' => route('branches.store', ['company' => $company->id]),
            'backUrl' => route('branches.index', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Creates one or more Safe Accounts in a single submission.
     * UNCHANGED except the response type (JSON → redirect, see class
     * docblock).
     */
    public function store(Company $company, StoreBranchRequest $request, SafeRepository $safeRepository)
    {
        $odooSyncFailureMessages = [];

        foreach ($request->get('safe', []) as $currentSafeArr) {
            $currentSafeArr = array_merge($currentSafeArr, [
                'company_id' => $company->id,
                'created_by' => auth()->user()->id,
            ]);
            $model = $safeRepository->store($currentSafeArr);
            $failureMessage = $this->syncSafeWithOdoo($company, $model->odoo_code);
            if ($failureMessage) {
                $odooSyncFailureMessages[] = $failureMessage;
            }
        }

        $redirect = redirect()
            ->route('branches.index', ['company' => $company->id, 'active' => CashVeroBranch::BRANCHES]);

        if ($odooSyncFailureMessages) {
            return $redirect->with('fail', implode(' | ', array_unique($odooSyncFailureMessages)));
        }

        return $redirect->with('success', __('Data Store Successfully'));
    }

    /**
     * Shows the "Edit Safe" form — always a single row (no repeater
     * "Add" affordance in edit mode, matching the original exactly).
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/SafeAccounts/Form.vue),
     * distinguished by the `mode: 'edit'` prop.
     */
    public function edit(Company $company, CashVeroBranch $branch)
    {
        return \Inertia\Inertia::render('SafeAccounts/Form', [
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'currencies' => getCurrencies(),
            'hasOdoo' => $company->hasOdooIntegrationCredentials(),
            'model' => [
                'id' => $branch->id,
                'name' => $branch->getName(),
                'currency' => $branch->getCurrencyName(),
                'odoo_code' => $branch->getOdooCode(),
            ],
            'submitUrl' => route('branches.update', ['company' => $company->id, 'branch' => $branch->id]),
            'backUrl' => route('branches.index', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Updates a Safe Account. UNCHANGED except the response type
     * (JSON → redirect, see class docblock).
     */
    public function update(Company $company, StoreBranchRequest $request, CashVeroBranch $branch)
    {
        $odooSyncFailureMessages = [];

        foreach ($request->get('safe', []) as $safeArr) {
            $branch->update($safeArr);
            $failureMessage = $this->syncSafeWithOdoo($company, $branch->odoo_code);
            if ($failureMessage) {
                $odooSyncFailureMessages[] = $failureMessage;
            }
        }

        $redirect = redirect()
            ->route('branches.index', ['company' => $company->id, 'active' => CashVeroBranch::BRANCHES]);

        if ($odooSyncFailureMessages) {
            return $redirect->with('fail', implode(' | ', array_unique($odooSyncFailureMessages)));
        }

        return $redirect->with('success', __('Item Has Been Updated Successfully'));
    }

    /**
     * * بترجع رسالة الخطا لو المزامنة مع اودو ما نجحتش و null لو كل حاجة تمام
     * * الحفظ نفسه بيكون خلص قبل كدا , فا احنا بس بنعرف المستخدم ان الربط مع اودو
     * * هو اللي ما اتحدثش , لان من غير الرسالة دي الربط القديم بيفضل موجود
     * * من غير ما حد ياخد باله والشيكات اللي في الخزنة بتقع في اودو بعد كدا
     */
    private function syncSafeWithOdoo(Company $company, ?string $odooCode): ?string
    {
        /**
         * * الشركة اصلا مش مربوطة باودو , فمفيش مزامنة اساسا و مفيش حاجة نقولها
         */
        if (! $company->hasOdooCredentials()) {
            return null;
        }
        /**
         * * الشركة مربوطة باودو بس اليوزر اللي عامل لوجن مالوش يوزر/باسورد في اودو
         * * ده كان بيعدي في صمت : الخزنة بتتحفظ , المزامنة ما بتشتغلش , وماحدش ياخد باله
         */
        if (! $company->hasOdooIntegrationCredentials()) {
            return __('The Safe Has Been Saved But It Was Not Synced With Odoo Because The Current User Has No Odoo Username Or Password');
        }
        if (! $odooCode) {
            return null;
        }
        try {
            $isSynced = (new OdooService($company))->syncBranchSafe($odooCode, $company->id);
        } catch (\Throwable $exception) {
            report($exception);

            return __('The Safe Has Been Saved But Syncing With Odoo Failed').' : '.$exception->getMessage();
        }
        if (! $isSynced) {
            return __('The Safe Has Been Saved But The Odoo Code Was Not Found In Odoo Chart Of Accounts').' : '.$odooCode;
        }

        return null;
    }

    /**
     * Deletes a Safe Account. UNCHANGED.
     */
    public function destroy(Company $company, CashVeroBranch $branch)
    {
        $branch->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * Pure AJAX data endpoint used elsewhere. UNCHANGED, deliberately.
     */
    public function getBranchesForCurrency(Request $request, Company $company)
    {
        $currency = $request->get('currencyName');
        $branches = CashVeroBranch::where('company_id', $company->id)->where('currency', $currency)->pluck('id', 'name')->toArray();

        return response()->json([
            'branches' => $branches,
        ]);
    }
}
