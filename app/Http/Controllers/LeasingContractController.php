<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LeasingContractController
 * ------------------------------------------------------------------
 * Manages Leasing Contract — same shape as MediumTermLoanController's
 * core CRUD (a single self-contained entity with an attached
 * amortization schedule), just keyed to a LeasingCompany instead of a
 * FinancialInstitution, and with no Account Number field (leasing
 * contracts don't have one — confirmed from the schema/model, not an
 * oversight).
 *
 * The "Upload Contract Schedule" action links to the same generic,
 * already-migrated upload/import system used by Medium Term Loan
 * (SalesGatheringController + SalesGatheringTestController →
 * resources/js/Pages/InvoiceUpload/*.vue), already wired for
 * ContractLoanSchedule. Nothing new needed there.
 *
 * The Contract Schedule Settlement screen is handled by a separate,
 * unchanged ContractLoanScheduleController — migrated in its own
 * pass (see LeasingContractSettlement/Index.vue).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/LeasingContract/Index.vue and
 *      .../Form.vue.
 *   ✅ store() / update() / destroy() → UNCHANGED, deliberately. All
 *      already redirect correctly.
 */
class LeasingContractController
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
            })
            ->sortByDesc('id')
            ->values();
    }

    /**
     * The main "Leasing Contract" list for one leasing company — one
     * flat list (only one status, "Running", exists — no tabs
     * needed, same as Medium Term Loan).
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/LeasingContract/Index.vue.
     */
    public function index(Company $company, Request $request, LeasingCompany $leasingCompany)
    {
        $currentType = $request->get('active', LeasingContract::RUNNING);
        $runningEndDate = $request->has('endDate') ? $request->input('endDate.'.$currentType) : now()->format('Y-m-d');

        $contracts = $company->leasingContracts->where('leasing_company_id', $leasingCompany->id);
        $contracts = $contracts->filterByLoanEndDate($runningEndDate);
        $contracts = $this->applyFilter($request, $contracts);

        return \Inertia\Inertia::render('LeasingContract/Index', [
            'company' => ['id' => $company->id],
            'leasingCompany' => ['id' => $leasingCompany->id, 'name' => $leasingCompany->getName()],
            // ⚠️ Confirmed bug fix: the original reuses the Medium
            // Term Loan permissions here (no dedicated "leasing
            // contract" permission exists in the system) — confirmed
            // from back-to-leasing-header-btn.blade.php and the
            // original index's @if(hasAuthFor(...)) guards. Using
            // 'create/update/delete leasing contract' (which don't
            // exist) made these buttons invisible to everyone.
            'canCreate' => hasAuthFor('create medium term loan'),
            'createUrl' => route('leasing.contracts.create', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]),
            'rows' => $contracts->map(function (LeasingContract $contract) use ($company, $leasingCompany) {
                return [
                    'id' => $contract->id,
                    'name' => $contract->getName(),
                    'start_date_formatted' => $contract->getStartDateFormatted(),
                    'end_date_formatted' => $contract->getEndDateFormatted(),
                    'currency_formatted' => $contract->getCurrencyFormatted(),
                    'limit_formatted' => $contract->getLimitFormatted(),
                    'borrowing_rate_formatted' => $contract->getBorrowingRateFormatted(),
                    'margin_rate_formatted' => $contract->getMarginRateFormatted(),
                    'duration_formatted' => $contract->getDurationFormatted(),
                    'installment_interval_formatted' => $contract->getPaymentInstallmentIntervalFormatted(),
                    'upload_schedule_url' => route('view.uploading', ['company' => $company->id, 'model' => 'ContractLoanSchedule', 'loanId' => $contract->id]),
                    'edit_url' => route('leasing.contracts.edit', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $contract->id]),
                    'delete_url' => route('leasing.contracts.destroy', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $contract->id]),
                ];
            })->values(),
            'canUpload' => hasAuthFor('create medium term loan'),
            'canUpdate' => hasAuthFor('update medium term loan'),
            'canDelete' => hasAuthFor('delete medium term loan'),
            'backUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies']),
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
     * Shows the "Add Leasing Contract" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/LeasingContract/Form.vue),
     * distinguished by the `mode: 'create'` prop.
     */
    public function create(Company $company, LeasingCompany $leasingCompany)
    {
        return \Inertia\Inertia::render('LeasingContract/Form', [
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'leasingCompany' => ['id' => $leasingCompany->id, 'name' => $leasingCompany->getName()],
            'currencies' => getCurrencies(),
            'installmentIntervals' => \App\Helpers\HVero::getDurationIntervalTypesForSelect(),
            'isLocked' => false,
            'model' => null,
            'submitUrl' => route('leasing.contracts.store', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]),
            'backUrl' => route('leasing.contracts.index', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]),
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
     * Stores a new Leasing Contract. UNCHANGED, deliberately.
     */
    public function store(Company $company, Request $request, LeasingCompany $leasingCompany)
    {
        $contract = new LeasingContract;
        $contract->status = LeasingContract::RUNNING;
        $contract->storeBasicForm($request);

        return redirect()
            ->route('leasing.contracts.index', [
                'company' => $company->id,
                'leasingCompany' => $leasingCompany->id,
                'active' => LeasingContract::RUNNING,
            ])
            ->with('success', __('Data Store Successfully'));
    }

    /**
     * Shows the "Edit Leasing Contract" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/LeasingContract/Form.vue),
     * distinguished by the `mode: 'edit'` prop.
     */
    public function edit(Company $company, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        // Same lock, same reason, as MediumTermLoanController::edit() —
        // see that method's comment for the full rationale.
        $isLocked = $leasingContract->contractLoanSchedules()->exists();

        return \Inertia\Inertia::render('LeasingContract/Form', [
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'leasingCompany' => ['id' => $leasingCompany->id, 'name' => $leasingCompany->getName()],
            'currencies' => getCurrencies(),
            'installmentIntervals' => \App\Helpers\HVero::getDurationIntervalTypesForSelect(),
            'isLocked' => $isLocked,
            'model' => [
                'id' => $leasingContract->id,
                'name' => $leasingContract->getName(),
                'start_date' => $leasingContract->getStartDate(),
                'end_date' => $leasingContract->getEndDate(),
                'currency' => $leasingContract->getCurrency(),
                'limit' => $leasingContract->getLimit(),
                'borrowing_rate' => $leasingContract->getBorrowingRate(),
                'margin_rate' => $leasingContract->getMarginRate(),
                'duration' => $leasingContract->getDuration(),
                'installment_payment_interval' => $leasingContract->getPaymentInstallmentInterval(),
                'already_paid_amount' => (float) $leasingContract->already_paid_amount,
                'first_installment_date' => $leasingContract->first_installment_date,
                'remaining_installment_count' => $leasingContract->remaining_installment_count,
            ],
            'submitUrl' => route('leasing.contracts.update', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $leasingContract->id]),
            'deleteScheduleUrl' => route('leasing.contracts.schedule.destroy', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id, 'leasingContract' => $leasingContract->id]),
            'backUrl' => route('leasing.contracts.index', ['company' => $company->id, 'leasingCompany' => $leasingCompany->id]),
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
     * Deletes then calls store() fresh, in-place — same delete-then-
     * recreate pattern as before. UPDATED: now blocked once a schedule
     * exists (see destroySchedule() below for why).
     */
    public function update(Company $company, Request $request, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        if ($leasingContract->contractLoanSchedules()->exists()) {
            return redirect()
                ->back()
                ->with('fail', __('This leasing contract has an uploaded schedule and can\'t be edited. Delete the schedule first if you need to make changes.'));
        }

        $leasingContract->deleteRelations();
        $leasingContract->delete();
        $this->store($company, $request, $leasingCompany);

        return redirect()
            ->route('leasing.contracts.index', [
                'company' => $company->id,
                'leasingCompany' => $leasingCompany->id,
                'active' => LeasingContract::RUNNING,
            ])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    /**
     * Deletes a Leasing Contract and its schedule. UNCHANGED.
     */
    public function destroy(Company $company, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        $leasingContract->deleteRelations();
        $leasingContract->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * Deletes every installment on this contract's schedule, and for
     * each one, every payment settlement recorded against it —
     * reversing each settlement's effect on the bank statement / loan
     * statement exactly as a normal single-settlement delete already
     * does (reuses ContractLoanScheduleSettlement::deleteAllRelations()
     * + delete(), applied to every row). Unlocks the contract for
     * editing again once complete.
     */
    public function destroySchedule(Company $company, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        DB::transaction(function () use ($leasingContract) {
            foreach ($leasingContract->contractLoanSchedules as $schedule) {
                foreach ($schedule->settlements as $settlement) {
                    $settlement->deleteAllRelations();
                    $settlement->delete();
                }
                $schedule->delete();
            }
        });

        return redirect()
            ->back()
            ->with('success', __('Schedule deleted. You can now edit this contract or upload a corrected schedule.'));
    }
}
