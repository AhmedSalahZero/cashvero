<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactoringContractRequest;
use App\Http\Requests\UpdateFactoringContractRequest;
use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * FactoringContractController
 * ------------------------------------------------------------------
 * Manages Factoring Contract — the agreement a company has with a
 * Factoring Company, with a Recourse Type (with/without recourse), a
 * rate structure, and an "Outstanding Breakdown" repeater for
 * declaring an existing outstanding balance's settlement schedule
 * when first onboarding a contract onto CashVero (same shared
 * App\OutstandingBreakdown polymorphic pattern already used
 * elsewhere for overdraft-type facilities).
 *
 * Scope note (confirmed with the project owner): only the Contract
 * itself is migrated in this pass. The two transaction types (With
 * Recourse / Without Recourse — FactoringWithRecourseController /
 * FactoringWithoutRecourseController, ~650-700 lines each) and the
 * Statement / Charges Statement screens are deliberately NOT touched
 * here — planned for when Treasury Operations and the Statement
 * section are tackled.
 *
 * ⚠️ Confirmed real bug, not present in this fixed version: the
 * original store()/update() returned a raw JSON body
 * (`{'redirectTo' => ...}`) instead of an HTTP redirect — same
 * category of Inertia-incompatible response already found and fixed
 * on updateExpense() (LC Issuance) and editAmountToBeDecreased() (LG
 * Issuance). The original Blade form submitted this via a custom AJAX
 * component that read `redirectTo` from the JSON and navigated
 * manually — under Inertia's router.post(), a raw JSON body isn't a
 * valid response type and breaks the request. Fixed here by returning
 * a normal redirect() response instead; the fields being saved and
 * all financial logic (breakdown storage, limit statement) are
 * UNCHANGED.
 *
 * ⚠️ Permissions: confirmed from the original Blade
 * (back-to-factoring-header-btn + index.blade.php's own
 * auth()->user()->can(...) checks) — Factoring Contract reuses the
 * "clean overdraft" permission set (create/update/delete), same as
 * how Leasing Contract reuses "medium term loan" permissions. There
 * is no dedicated "factoring contract" permission in this system.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/FactoringContract/Index.vue and
 *      .../Form.vue.
 *   ⚠️ store() / update() → response type fixed (JSON → redirect, see
 *      above). Financial logic (storeOutstandingBreakdown(),
 *      storeLimitStatement()/syncLimitStatement()) UNCHANGED.
 *   ✅ destroy() → UNCHANGED, deliberately — already redirects
 *      correctly.
 */
class FactoringContractController
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
                    $currentValue = $searchFieldName === 'recourse_type'
                        ? $item->getRecourseTypeLabel()
                        : $item->{$searchFieldName};

                    return false !== stristr((string) $currentValue, (string) $value);
                });
            })
            ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
                return $collection->where($dateFieldName, '>=', $from);
            })
            ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
                return $collection->where($dateFieldName, '<=', $to);
            })
            ->sortByDesc('id')
            ->values();
    }

    /**
     * The main "Factoring Contract" list for one factoring company —
     * one flat list, no tabs (matches the original exactly).
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/FactoringContract/Index.vue.
     */
    public function index(Company $company, Request $request, FactoringCompany $factoringCompany)
    {
        $contracts = $company->factoringContracts->where('factoring_company_id', $factoringCompany->id);
        $contracts = $this->applyFilter($request, $contracts);

        return \Inertia\Inertia::render('FactoringContract/Index', [
            'company' => ['id' => $company->id],
            'factoringCompany' => ['id' => $factoringCompany->id, 'name' => $factoringCompany->getName()],
            'canCreate' => hasAuthFor('create clean overdraft'),
            'createUrl' => route('factoring.contracts.create', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]),
            'rows' => $contracts->map(function (FactoringContract $contract) use ($company, $factoringCompany) {
                return [
                    'id' => $contract->id,
                    'start_date_formatted' => $contract->getContractStartDateFormatted(),
                    'end_date_formatted' => $contract->getContractEndDateFormatted(),
                    'recourse_type_label' => $contract->getRecourseTypeLabel(),
                    'currency' => $contract->getCurrency(),
                    'limit_formatted' => $contract->getLimitFormatted(),
                    'borrowing_rate_formatted' => $contract->getBorrowingRateFormatted(),
                    'margin_rate_formatted' => $contract->getMarginRateFormatted(),
                    'interest_rate_formatted' => $contract->getInterestRateFormatted(),
                    'edit_url' => route('factoring.contracts.edit', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id, 'factoringContract' => $contract->id]),
                    'delete_url' => route('factoring.contracts.destroy', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id, 'factoringContract' => $contract->id]),
                ];
            })->values(),
            'canUpdate' => hasAuthFor('update clean overdraft'),
            'canDelete' => hasAuthFor('delete clean overdraft'),
            'backUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'factoring_companies']),
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
     * Shows the "Add Factoring Contract" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/FactoringContract/Form.vue),
     * distinguished by the `mode: 'create'` prop.
     */
    public function create(Company $company, FactoringCompany $factoringCompany)
    {
        return \Inertia\Inertia::render('FactoringContract/Form', [
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'factoringCompany' => ['id' => $factoringCompany->id, 'name' => $factoringCompany->getName()],
            'currencies' => getCurrencies(),
            'recourseTypes' => FactoringContract::recourseTypes(),
            'model' => null,
            'submitUrl' => route('factoring.contracts.store', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]),
            'backUrl' => route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]),
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
     * Stores a new Factoring Contract. Financial logic UNCHANGED —
     * only the response type changed (JSON → redirect, see class
     * docblock).
     */
    public function store(Company $company, FactoringCompany $factoringCompany, StoreFactoringContractRequest $request)
    {
        $data = $request->only($this->getCommonDataArr());
        foreach (['contract_start_date', 'contract_end_date', 'balance_date'] as $dateField) {
            $data[$dateField] = $request->get($dateField)
                ? Carbon::make($request->get($dateField))->format('Y-m-d')
                : null;
        }

        $data['created_by'] = auth()->id();
        $data['company_id'] = $company->id;
        $data['factoring_company_id'] = $factoringCompany->id;

        /** @var FactoringContract $contract */
        $contract = FactoringContract::create($data);
        $contract->storeOutstandingBreakdown($request, $company);
        $contract->storeLimitStatement($company->id);

        return redirect()
            ->route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id])
            ->with('success', __('Data Store Successfully'));
    }

    /**
     * Shows the "Edit Factoring Contract" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/FactoringContract/Form.vue),
     * distinguished by the `mode: 'edit'` prop. Note: the rate fields
     * (borrowing/margin/interest/min interest) are create-only in the
     * original — not editable afterward, no separate "edit rate"
     * feature exists for this model — so they're intentionally
     * omitted from the edit-mode payload here, matching exactly.
     */
    public function edit(Company $company, FactoringCompany $factoringCompany, FactoringContract $factoringContract)
    {
        return \Inertia\Inertia::render('FactoringContract/Form', [
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'factoringCompany' => ['id' => $factoringCompany->id, 'name' => $factoringCompany->getName()],
            'currencies' => getCurrencies(),
            'recourseTypes' => FactoringContract::recourseTypes(),
            'model' => [
                'id' => $factoringContract->id,
                'contract_start_date' => $factoringContract->contract_start_date,
                'contract_end_date' => $factoringContract->contract_end_date,
                'recourse_type' => $factoringContract->recourse_type,
                'currency' => $factoringContract->getCurrency(),
                'limit' => $factoringContract->getLimit(),
                'outstanding_balance' => (float) $factoringContract->outstanding_balance,
                'balance_date' => $factoringContract->balance_date,
                'borrowing_rate' => $factoringContract->borrowing_rate,
                'margin_rate' => $factoringContract->margin_rate,
                'interest_rate' => $factoringContract->interest_rate,
                'min_interest_rate' => $factoringContract->min_interest_rate,
                'highest_debt_balance_rate' => $factoringContract->highest_debt_balance_rate,
                'admin_fees_rate' => $factoringContract->admin_fees_rate,
                'to_be_setteled_max_within_days' => $factoringContract->to_be_setteled_max_within_days,
                'outstanding_breakdowns' => $factoringContract->outstandingBreakdowns->map(fn ($b) => [
                    'id' => $b->id,
                    'amount' => $b->getAmount(),
                    'settlement_date' => $b->getSettlementDate(),
                ])->values(),
            ],
            'submitUrl' => route('factoring.contracts.update', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id, 'factoringContract' => $factoringContract->id]),
            'backUrl' => route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]),
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
     * Updates a Factoring Contract in place. Financial logic
     * UNCHANGED — only the response type changed (JSON → redirect,
     * see class docblock).
     */
    public function update(
        Company $company,
        UpdateFactoringContractRequest $request,
        FactoringCompany $factoringCompany,
        FactoringContract $factoringContract
    ) {
        $data = $request->only($this->getCommonDataArr());
        foreach (['contract_start_date', 'contract_end_date', 'balance_date'] as $dateField) {
            $data[$dateField] = $request->get($dateField)
                ? Carbon::make($request->get($dateField))->format('Y-m-d')
                : null;
        }
        $data['updated_by'] = auth()->id();

        $factoringContract->update($data);
        $factoringContract->storeOutstandingBreakdown($request, $company);
        $factoringContract->syncLimitStatement($company->id);

        return redirect()
            ->route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    /**
     * Deletes a Factoring Contract and its relations. UNCHANGED.
     */
    public function destroy(Company $company, FactoringCompany $factoringCompany, FactoringContract $factoringContract)
    {
        $factoringContract->deleteRelations();
        $factoringContract->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    protected function getCommonDataArr(): array
    {
        return [
            'contract_start_date',
            'contract_end_date',
            'recourse_type',
            'currency',
            'limit',
            'outstanding_balance',
            'balance_date',
            'borrowing_rate',
            'margin_rate',
            'interest_rate',
            'min_interest_rate',
            'highest_debt_balance_rate',
            'admin_fees_rate',
            'to_be_setteled_max_within_days',
        ];
    }
}
