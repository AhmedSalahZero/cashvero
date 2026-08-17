<?php
namespace App\Http\Controllers;

use App\Enums\LgTypes;
use App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest;
use App\Http\Requests\UpdateLetterOfGuaranteeIssuanceRequest;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LetterOfGuaranteeCashCoverStatement;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory;
use App\Models\LetterOfGuaranteeStatement;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\TimeOfDeposit;
use App\Services\Api\LetterOfGuaranteeService;
use App\Services\Api\OdooSync;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// (Left exactly as it was — a pre-existing, fully commented-out
// applyFilter() method. Not something introduced or removed here.)
    // protected function applyFilter(Request $request, Collection $collection, ?string $filterStartDate = null, ?string $filterEndDate = null):Collection
    // {
    //     if (!count($collection)) {
    //         return $collection;
    //     }
    //     $searchFieldName = $request->get('field');
    //     $dateFieldName =  'issuance_date' ; // change it
    //     $from = $request->get('from');
    //     $to = $request->get('to');
    //     $value = $request->query('value');
    //     $collection = $collection
    //     ->when($request->has('value'), function ($collection) use ( $value, $searchFieldName) {
    //         return $collection->filter(function ($letterOfGuaranteeIssuance) use ($value, $searchFieldName) {
    //             $currentValue = $letterOfGuaranteeIssuance->{$searchFieldName} ;
    //             return false !== stristr($currentValue, $value);
    //         });
    //     })
    //     ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
    //         return $collection->where($dateFieldName, '>=', $from);
    //     })
    //     ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
    //         return $collection->where($dateFieldName, '<=', $to);
    //     })
    //     ->when($filterStartDate, function ($collection) use ($filterStartDate, $filterEndDate) {
    //         return $collection->filterByIssuanceDate($filterStartDate, $filterEndDate);
    //     });
    //     // ->sortBy('renewal_date')
    //     // ->values();

    //     return $collection;
    // }
/**
 * LetterOfGuaranteeIssuanceController
 * ------------------------------------------------------------------
 * Manages individual Letters Of Guarantee issued against one of 4
 * funding sources (LG Facility, Against CD, Against TD, 100% Cash
 * Cover) — each with its OWN Vue form (not one form with
 * conditionals). Tabs are by LG TYPE (Bid Bond, Final LG, Advanced
 * Payment LG, Performance LG — see App\Enums\LgTypes), not by source.
 *
 * ⚠️ update() does NOT actually update in place — it deletes the
 * issuance and all its relations, then calls store() fresh. This is
 * confirmed, deliberate original behavior, not a bug: editing an LG
 * Issuance re-runs the entire creation pipeline (bank statement
 * postings, LG/cash-cover statement entries, Odoo journal entries)
 * from scratch.
 *
 * Frontend: index / create / edit are fully Vue + Inertia
 * (resources/js/Pages/LetterOfGuaranteeIssuance/*). Blade views for
 * this module have been removed.
 */
class LetterOfGuaranteeIssuanceController
{
    use GeneralFunctions;
	/**
	 * The main "LG Issuance" list — 4 tabs by LG type (not by funding
	 * source). Each tab is genuinely paginated (matches the original
	 * exactly — all 4 tabs are queried and paginated on every request,
	 * even the ones not currently shown).
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/LetterOfGuaranteeIssuance/Index.vue.
	 */
    /**
     * ✅ PERFORMANCE FIX (requested by the project owner after a
     * review of index() with large data volumes in mind): the
     * original — and my first migrated version — queried and
     * paginated ALL 4 LG-type tabs on every single page load, even
     * though only one is ever shown at a time. That's roughly 4x more
     * database queries than necessary per visit. Now only the active
     * tab is actually queried on load; the other 3 are returned as
     * lightweight `loaded: false` placeholders, and Vue fetches a
     * tab's real data via tabData() below only the first time the
     * user actually clicks into it — not before.
     */
    private function buildTabRows($paginator, Company $company): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'loaded' => true,
            'rows' => collect($paginator->items())->map(function (LetterOfGuaranteeIssuance $lg) use ($company) {
                $source = $lg->getSource();
                return [
                    'id' => $lg->id,
                    'transaction_name' => $lg->getTransactionName(),
                    'beneficiary_name' => $lg->getBeneficiaryName(),
                    'source_formatted' => $lg->getSourceFormatted(),
                    'status' => $lg->getStatus(),
                    'status_formatted' => $lg->getStatusFormatted(),
                    'bank_name' => $lg->getFinancialInstitutionBankName(),
                    'lg_code' => $lg->getLgCode(),
                    'transaction_reference' => $lg->getTransactionReference(),
                    'lg_amount_formatted' => $lg->getLgAmountFormatted(),
                    'lg_current_amount_formatted' => $lg->getLgCurrentAmountFormatted(),
                    'lg_currency' => $lg->getLgCurrency(),
                    'purchase_order_date_formatted' => $lg->getPurchaseOrderDateFormatted(),
                    'issuance_date_formatted' => $lg->getIssuanceDateFormatted(),
                    'renewal_date_formatted' => $lg->getRenewalDateFormatted(),
                    'is_running' => $lg->isRunning(),
                    'is_expired' => $lg->isExpired(),
                    'is_cancelled' => $lg->isCancelled(),
                    'is_advanced_payment' => $lg->isAdvancedPayment(),
                    'has_comment' => $lg->hasComment(),
                    'user_comment' => $lg->getUserComment(),
                    'fully_integrated_with_odoo' => (bool) $lg->fullyIntegratedWithOdoo(),
                    'odoo_reference_names' => $lg->getOdooReferenceNames(),
                    'has_odoo_error' => (bool) $lg->hasOdooError(),
                    'odoo_error' => $lg->getOdooError(),
                    'edit_url' => route('edit.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id, 'source' => $source]),
                    'delete_url' => route('delete.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id, 'source' => $source]),
                    'cancel_url' => route('cancel.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id, 'source' => $source]),
                    'back_to_running_url' => route('back.to.running.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id, 'source' => $source]),
                    'renewal_date_url' => route('letter.of.issuance.renewal.date', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id]),
                    'apply_advanced_payment_url' => route('advanced.lg.payment.apply.amount.to.be.decreased', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $lg->id, 'source' => $source]),
                    'renewal_date' => $lg->getRenewalDate(),
                    'lg_amount' => $lg->getLgAmount(),
                    'advanced_payment_histories' => $lg->advancedPaymentHistories->map(fn ($h) => [
                        'id' => $h->id,
                        'date_formatted' => $h->getDateFormatted(),
                        'date' => $h->getDate(),
                        'amount' => $h->getAmount(),
                        'amount_formatted' => $h->getAmountFormatted(),
                        'edit_url' => route('advanced.lg.payment.edit.amount.to.be.decreased', ['company' => $company->id, 'lgAdvancedPaymentHistory' => $h->id, 'source' => $source]),
                        'delete_url' => route('delete.lg.advanced.payment', ['company' => $company->id, 'lgAdvancedPaymentHistory' => $h->id]),
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function queryTab(Company $company, string $type, Request $request, string $startDate, string $endDate)
    {
        $query = $company->letterOfGuaranteeIssuances()
            ->whereBetween('issuance_date', [$startDate, $endDate])
            ->where('lg_type', $type)->with('financialInstitutionBank', 'advancedPaymentHistories', 'beneficiary');

        $searchFieldName = $request->get('field');
        $value = $request->get('value');
        $from = $request->get('from');
        $to = $request->get('to');
        $query = $query->when($searchFieldName == 'issuance_date', function ($q) use ($from, $to) {
            $q->whereBetween('issuance_date', [$from, $to]);
        })
        ->when($searchFieldName == 'transaction_name', function ($q) use ($value) {
            $q->where('transaction_name', 'like', '%'.$value.'%');
        })
        ->when($searchFieldName == 'lg_code', function ($q) use ($value) {
            $q->where('lg_code', 'like', '%'.$value.'%');
        })
        ->when($searchFieldName == 'purchase_order_date', function ($q) use ($from, $to) {
            $q->whereBetween('purchase_order_date', [$from, $to]);
        });

        $paginationPerPage = GeneralFunctions::getPaginationLimit();

        return $query->paginate($paginationPerPage);
    }

    public function index(Company $company, Request $request)
    {
        $numberOfMonthsBetweenEndDateAndStartDate = 60;
        $activeLgType = $request->get('active', LgTypes::BID_BOND);
        $filterDates = [];
        $tabs = [];
        foreach (getLgTypes() as $type => $typeNameFormatted) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
            $filterDates[$type] = ['startDate' => $startDate, 'endDate' => $endDate];

            if ($type === $activeLgType) {
                $paginator = $this->queryTab($company, $type, $request, $startDate, $endDate);
                $tabs[$type] = $this->buildTabRows($paginator, $company);
            } else {
                // Not queried at all yet — Vue fetches this via
                // tabData() the first time the user actually clicks it.
                $tabs[$type] = ['current_page' => 1, 'last_page' => 1, 'per_page' => GeneralFunctions::getPaginationLimit(), 'total' => null, 'loaded' => false, 'rows' => []];
            }
        }

		return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/Index', [
			'company' => ['id' => $company->id],
			'activeLgType' => $activeLgType,
			'filterDates' => $filterDates,
			'lgTypes' => LgTypes::getAll(),
			'createUrls' => [
				LetterOfGuaranteeIssuance::LG_FACILITY => route('create.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::LG_FACILITY]),
				LetterOfGuaranteeIssuance::AGAINST_CD => route('create.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_CD]),
				LetterOfGuaranteeIssuance::AGAINST_TD => route('create.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_TD]),
				LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER => route('create.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]),
			],
			'tabDataUrl' => route('letter.of.guarantee.issuance.tab.data', ['company' => $company->id]),
			/**
			 * This screen had NO permission flags at all before the
			 * Roles & Permissions rollout, even though create/update/
			 * delete permissions for LG issuance already existed and
			 * were never checked anywhere (2026-08 audit, F-07).
			 */
			'permissions' => [
				'canCreate' => hasAuthFor('lg_issuance.create'),
				'canUpdate' => hasAuthFor('lg_issuance.update'),
				'canDelete' => hasAuthFor('lg_issuance.delete'),
				'canCancel' => hasAuthFor('lg_issuance.cancel'),
				'canRenew'  => hasAuthFor('lg_issuance.renew'),
				'canImport' => hasAuthFor('lg_issuance.import'),
			],
			'tabs' => $tabs,
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
     * ✅ NEW — plain JSON endpoint (not an Inertia page visit) powering
     * on-demand tab loading and per-tab pagination/search on
     * Index.vue. Reuses the exact same query + row-shaping logic as
     * index() via queryTab()/buildTabRows(), so behavior is identical
     * either way — just computed only when actually needed.
     */
    public function tabData(Company $company, Request $request)
    {
        $type = $request->get('type', LgTypes::BID_BOND);
        $numberOfMonthsBetweenEndDateAndStartDate = 60;
        $startDate = $request->get('startDate', now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d'));
        $endDate = $request->get('endDate', now()->format('Y-m-d'));

        $paginator = $this->queryTab($company, $type, $request, $startDate, $endDate);

        return response()->json($this->buildTabRows($paginator, $company));
    }
    public function commonViewVars(Company $company, string $source):array
    {
        $cdOrTdAccountTypes = [];

        $financialInstitutionBanks = FinancialInstitution::with('letterOfGuaranteeFacilities')->onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->onlyHasLgFacility()->get();
        $errorMessage = __('Please Create / Renew Existing Banking Facilities LGs Contracts');
        // $financialInstitutionBanks = FinancialInstitution::with('letterOfGuaranteeFacilities')->onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->onlyHasLgFacility()->get();
        if ($source == LetterOfGuaranteeIssuance::AGAINST_CD) {
            $financialInstitutionBanks = FinancialInstitution::with('letterOfGuaranteeFacilities')->onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->get();
            $cdOrTdAccountTypes = AccountType::onlyCdAccounts()->get();
            $errorMessage = __('Please Create / Renew Existing at least one CD');
        } elseif ($source == LetterOfGuaranteeIssuance::AGAINST_TD) {
            $financialInstitutionBanks = FinancialInstitution::with('letterOfGuaranteeFacilities')->onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->get();
            $cdOrTdAccountTypes = AccountType::onlyTdAccounts()->get();
            $errorMessage = __('Please Create / Renew Existing at least one TD');
        }
        
        
        if ($source == LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            $financialInstitutionBanks = FinancialInstitution::with('letterOfGuaranteeFacilities')->onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->get();
            $errorMessage = null;
        }
        
        
        return [
            'financialInstitutionBanks'=>$financialInstitutionBanks  ,
            'beneficiaries'=>[],
            // 'beneficiaries'=>Partner::onlyCustomers()->onlyForCompany($company->id)->get(),
            'contracts'=>Contract::onlyForCompany($company->id)->get(),
            // NOTE: the LG's "purchase_order_id" column actually links to
            // SalesOrder (see LetterOfGuaranteeIssuance::purchaseOrder()) —
            // that's the app's own naming, not a typo introduced here.
            'purchaseOrders'=>SalesOrder::onlyForCompany($company->id)->get(),
            'accountTypes'=> AccountType::onlyCurrentAccount()->get(),
            'cashCoverAccountTypes'=>AccountType::onlyCashCoverAccounts()->get(),
            'source'=>$source,
            'cdOrTdAccountTypes'=>$cdOrTdAccountTypes,
            'errorMessage'=>$errorMessage
        ];

    }
    /**
     * Builds every prop LgFacilityForm.vue needs, on top of
     * commonViewVars(). Financial institution accounts (for the cash
     * cover / fees-and-commission dropdowns) are fetched up front and
     * filtered by currency+type client-side — same proven pattern
     * already used for CD/TD account selection on Fully Secured
     * Overdraft. Dynamic limit/outstanding-balance/commission-rate
     * lookups still call updateOutstandingBalanceAndLimits() live.
     */
    protected function lgFacilityFormVars(Company $company, array $commonVars, ?LetterOfGuaranteeIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');

        /**
         * ⚠️ Real gap found and fixed here (confirmed with the project
         * owner before building): the original "Cash Cover From
         * Account Type" selector lets the user pick Current Account,
         * TD, or CD as the cash-cover source — not just current
         * accounts. Every current account AND every running TD/CD for
         * these financial institutions is fetched up front, each
         * tagged with its real account_type_id, so the Vue form can
         * filter the account-number dropdown by type + bank +
         * currency client-side — same proven pattern already used for
         * CD/TD selection on Fully Secured Overdraft, since the
         * original relied on client-side AJAX endpoints with no
         * traceable server route in this codebase.
         */
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $latestBalances = \App\Models\CurrentAccountBankStatement::whereIn('financial_institution_account_id', FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)->where('company_id', $company->id)->pluck('id'))
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->groupBy('financial_institution_account_id')
            ->map(fn ($rows) => $rows->first()->getEndBalance());
        $accounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'account_type_id' => $currentAccountType?->id,
                'financial_institution_id' => $a->financial_institution_id,
                'account_number' => $a->getAccountNumber(),
                'currency' => $a->getCurrency(),
                'amount' => $latestBalances->get($a->id, 0),
            ])->values();

        foreach (AccountType::onlyCashCoverAccounts()->get() as $accountType) {
            if ($accountType->id === $currentAccountType?->id) {
                continue; // already covered above
            }
            $modelClass = '\\App\\Models\\'.$accountType->getModelName();
            $records = $modelClass::where('company_id', $company->id)
                ->whereIn('financial_institution_id', $financialInstitutionIds)
                ->where('status', $modelClass::RUNNING)
                ->get();
            foreach ($records as $record) {
                $accounts[] = [
                    'id' => $record->id,
                    'account_type_id' => $accountType->id,
                    'financial_institution_id' => $record->financial_institution_id,
                    'account_number' => $record->getAccountNumber(),
                    'currency' => $record->getCurrency(),
                    'amount' => $record->getAmount(),
                ];
            }
        }
        $accounts = collect($accounts)->values();

        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id],
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'currencies' => getCurrencies(),
            'lgTypes' => getLgTypes(),
            'lgCategories' => LetterOfGuaranteeIssuance::getCategories(),
            'commissionIntervals' => getCommissionInterval(),
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => [
                'id' => $fi->id,
                'name' => $fi->getName(),
                'lg_facilities' => $fi->letterOfGuaranteeFacilities->map(fn ($f) => ['id' => $f->id, 'name' => $f->getName()])->values(),
            ])->values(),
            'accounts' => $accounts,
            'cashCoverAccountTypes' => AccountType::onlyCashCoverAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'feesAccountTypes' => AccountType::onlyCurrentAccount()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'contracts' => Contract::onlyForCompany($company->id)->get()->map(fn ($c) => [
                'id' => $c->id,
                'partner_id' => $c->partner_id,
                'name' => $c->getName(),
            ])->values(),
            // NOTE: despite the "purchaseOrders" / "po_number" naming (kept
            // to match the LG's own purchase_order_id/purchase_order_date
            // columns), these are actually Sales Orders — the LG's
            // purchaseOrder() relation belongs to SalesOrder, not
            // PurchaseOrder. so_date is that SO's own start_date_1, used
            // to auto-fill the LG's date field once a specific SO is picked.
            'purchaseOrders' => SalesOrder::onlyForCompany($company->id)->get()->map(fn ($so) => [
                'id' => $so->id,
                'contract_id' => $so->contract_id,
                'po_number' => $so->so_number,
                'so_date' => $so->start_date_1,
            ])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->financial_institution_id,
                'lg_facility_id' => $model->getLgFacilityId(),
                'lg_type' => $model->getLgType(),
                'lg_code' => $model->getLgCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractId(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->transaction_date,
                'issuance_date' => $model->getIssuanceDate(),
                'lg_duration_months' => $model->lg_duration_months,
                'renewal_date' => $model->getRenewalDate(),
                'lg_amount' => $model->getLgAmount(),
                'cash_cover_rate' => $model->getCashCoverRate(),
                'cash_cover_amount' => $model->getCashCoverAmount(),
                'lg_commission_rate' => $model->getLgCommissionRate(),
                'lg_commission_amount' => $model->lg_commission_amount,
                'min_lg_commission_fees' => $model->min_lg_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'lg_commission_interval' => $model->getLgCommissionInterval(),
                'cash_cover_deducted_from_account_type' => $model->getCashCoverDeductedFromAccountTypeId(),
                'cash_cover_deducted_from_account_id' => $model->getCashCoverDeductedFromAccountId(),
                'lg_fees_and_commission_account_type' => $model->getFeesAndCommissionAccountTypeId(),
                'lg_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.guarantee.outstanding.balance.and.limit', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $model->id, 'source' => LetterOfGuaranteeIssuance::LG_FACILITY])
                : route('store.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::LG_FACILITY]),
            'backUrl' => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ];
    }

    /**
     * Builds every prop AgainstTdForm.vue needs. Structurally simpler
     * than lgFacilityFormVars(): confirmed by tracing store() that for
     * this source the TD itself IS the collateral — there is no
     * separate "Cash Cover Account" at all ($model->isCdOrTd() skips
     * the cash-cover bank-statement posting entirely). No Limit/Total
     * Outstanding/Total Room fields either (those are LG-Facility-only
     * concepts) — only "LG Type Outstanding Balance", tracked per the
     * specific TD selected, not per facility.
     */
    protected function againstTdFormVars(Company $company, array $commonVars, ?LetterOfGuaranteeIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');
        $tdAccountType = collect($commonVars['cdOrTdAccountTypes'])->first();

        $tdAccounts = TimeOfDeposit::where('company_id', $company->id)
            ->whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('status', TimeOfDeposit::RUNNING)
            ->get()
            ->map(fn ($td) => [
                'id' => $td->id,
                'financial_institution_id' => $td->financial_institution_id,
                'account_number' => $td->getAccountNumber(),
                'currency' => $td->getCurrency(),
                'amount' => $td->getAmount(),
            ])->values();

        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($a) => ['id' => $a->id, 'financial_institution_id' => $a->financial_institution_id, 'account_number' => $a->getAccountNumber(), 'currency' => $a->getCurrency()])
            ->values();

        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id],
            'source' => LetterOfGuaranteeIssuance::AGAINST_TD,
            'currencies' => getCurrencies(),
            'lgTypes' => getLgTypes(),
            'lgCategories' => LetterOfGuaranteeIssuance::getCategories(),
            'commissionIntervals' => getCommissionInterval(),
            'tdAccountTypeId' => $tdAccountType?->id,
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => ['id' => $fi->id, 'name' => $fi->getName()])->values(),
            'tdAccounts' => $tdAccounts,
            'feesAccounts' => $currentAccounts,
            'contracts' => Contract::onlyForCompany($company->id)->get()->map(fn ($c) => ['id' => $c->id, 'partner_id' => $c->partner_id, 'name' => $c->getName()])->values(),
            'purchaseOrders' => SalesOrder::onlyForCompany($company->id)->get()->map(fn ($so) => ['id' => $so->id, 'contract_id' => $so->contract_id, 'po_number' => $so->so_number, 'so_date' => $so->start_date_1])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->financial_institution_id,
                'cd_or_td_id' => $model->getCdOrTdId(),
                'lg_type' => $model->getLgType(),
                'lg_code' => $model->getLgCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractId(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->transaction_date,
                'issuance_date' => $model->getIssuanceDate(),
                'lg_duration_months' => $model->lg_duration_months,
                'renewal_date' => $model->getRenewalDate(),
                'lg_amount' => $model->getLgAmount(),
                'lg_commission_rate' => $model->getLgCommissionRate(),
                'lg_commission_amount' => $model->lg_commission_amount,
                'min_lg_commission_fees' => $model->min_lg_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'lg_commission_interval' => $model->getLgCommissionInterval(),
                'lg_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.guarantee.outstanding.balance.and.limit', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $model->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_TD])
                : route('store.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_TD]),
            'backUrl' => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ];
    }

    /**
     * Builds every prop AgainstCdForm.vue needs. Confirmed via diff
     * against the original blade: structurally identical to Against
     * TD in every way except the underlying model (CertificatesOfDeposit
     * instead of TimeOfDeposit) — same "no separate cash cover
     * account" rule, same LG Outstanding Balance / Against Cash Cover
     * / LG Type Outstanding Balance fields.
     */
    protected function againstCdFormVars(Company $company, array $commonVars, ?LetterOfGuaranteeIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');
        $cdAccountType = collect($commonVars['cdOrTdAccountTypes'])->first();

        $cdAccounts = CertificatesOfDeposit::where('company_id', $company->id)
            ->whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('status', CertificatesOfDeposit::RUNNING)
            ->get()
            ->map(fn ($cd) => [
                'id' => $cd->id,
                'financial_institution_id' => $cd->financial_institution_id,
                'account_number' => $cd->getAccountNumber(),
                'currency' => $cd->getCurrency(),
                'amount' => $cd->getAmount(),
            ])->values();

        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($a) => ['id' => $a->id, 'financial_institution_id' => $a->financial_institution_id, 'account_number' => $a->getAccountNumber(), 'currency' => $a->getCurrency()])
            ->values();

        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id],
            'source' => LetterOfGuaranteeIssuance::AGAINST_CD,
            'currencies' => getCurrencies(),
            'lgTypes' => getLgTypes(),
            'lgCategories' => LetterOfGuaranteeIssuance::getCategories(),
            'commissionIntervals' => getCommissionInterval(),
            'cdAccountTypeId' => $cdAccountType?->id,
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => ['id' => $fi->id, 'name' => $fi->getName()])->values(),
            'cdAccounts' => $cdAccounts,
            'feesAccounts' => $currentAccounts,
            'contracts' => Contract::onlyForCompany($company->id)->get()->map(fn ($c) => ['id' => $c->id, 'partner_id' => $c->partner_id, 'name' => $c->getName()])->values(),
            'purchaseOrders' => SalesOrder::onlyForCompany($company->id)->get()->map(fn ($so) => ['id' => $so->id, 'contract_id' => $so->contract_id, 'po_number' => $so->so_number, 'so_date' => $so->start_date_1])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->financial_institution_id,
                'cd_or_td_id' => $model->getCdOrTdId(),
                'lg_type' => $model->getLgType(),
                'lg_code' => $model->getLgCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractId(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->transaction_date,
                'issuance_date' => $model->getIssuanceDate(),
                'lg_duration_months' => $model->lg_duration_months,
                'renewal_date' => $model->getRenewalDate(),
                'lg_amount' => $model->getLgAmount(),
                'lg_commission_rate' => $model->getLgCommissionRate(),
                'lg_commission_amount' => $model->lg_commission_amount,
                'min_lg_commission_fees' => $model->min_lg_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'lg_commission_interval' => $model->getLgCommissionInterval(),
                'lg_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.guarantee.outstanding.balance.and.limit', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $model->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_CD])
                : route('store.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::AGAINST_CD]),
            'backUrl' => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ];
    }

    /**
     * Builds every prop HundredPercentageCashCoverForm.vue needs.
     * Structurally close to Against TD's, minus any TD/CD selection —
     * this source is a direct 100% cash deposit, not backed by an
     * existing deposit. One real difference confirmed by tracing
     * store(): there is no separate Cash Cover account field at all —
     * $cashCoverDeductedFromAccountId falls back to the Fees &
     * Commission account when none is submitted, so the SAME account
     * is used for both. Cash Cover Rate defaults to 100.
     */
    protected function hundredPercentageCashCoverFormVars(Company $company, array $commonVars, ?LetterOfGuaranteeIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($a) => ['id' => $a->id, 'financial_institution_id' => $a->financial_institution_id, 'account_number' => $a->getAccountNumber(), 'currency' => $a->getCurrency()])
            ->values();

        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id],
            'source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER,
            'currencies' => getCurrencies(),
            'lgTypes' => getLgTypes(),
            'lgCategories' => LetterOfGuaranteeIssuance::getCategories(),
            'commissionIntervals' => getCommissionInterval(),
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => ['id' => $fi->id, 'name' => $fi->getName()])->values(),
            'feesAccounts' => $currentAccounts,
            'contracts' => Contract::onlyForCompany($company->id)->get()->map(fn ($c) => ['id' => $c->id, 'partner_id' => $c->partner_id, 'name' => $c->getName()])->values(),
            'purchaseOrders' => SalesOrder::onlyForCompany($company->id)->get()->map(fn ($so) => ['id' => $so->id, 'contract_id' => $so->contract_id, 'po_number' => $so->so_number, 'so_date' => $so->start_date_1])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->financial_institution_id,
                'lg_currency' => $model->getLgCurrency(),
                'lg_type' => $model->getLgType(),
                'lg_code' => $model->getLgCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractId(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->transaction_date,
                'issuance_date' => $model->getIssuanceDate(),
                'lg_duration_months' => $model->lg_duration_months,
                'renewal_date' => $model->getRenewalDate(),
                'lg_amount' => $model->getLgAmount(),
                'cash_cover_rate' => $model->getCashCoverRate(),
                'cash_cover_amount' => $model->getCashCoverAmount(),
                'lg_commission_rate' => $model->getLgCommissionRate(),
                'lg_commission_amount' => $model->lg_commission_amount,
                'min_lg_commission_fees' => $model->min_lg_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'lg_commission_interval' => $model->getLgCommissionInterval(),
                'lg_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.guarantee.outstanding.balance.and.limit', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.guarantee.issuance', ['company' => $company->id, 'letterOfGuaranteeIssuance' => $model->id, 'source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER])
                : route('store.letter.of.guarantee.issuance', ['company' => $company->id, 'source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]),
            'backUrl' => route('view.letter.of.guarantee.issuance', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ];
    }
    /**
     * Shows the "Add LG Issuance" form (Vue + Inertia for all sources).
     */
    public function create(Company $company, string $source)
    {
        $commonVars = $this->commonViewVars($company, $source) ;
        if (!count($commonVars['financialInstitutionBanks']) && isset($commonVars['errorMessage'])) {
            return redirect()->back()->with('fail', $commonVars['errorMessage']);
        }
        if ($source === LetterOfGuaranteeIssuance::LG_FACILITY) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/LgFacilityForm', $this->lgFacilityFormVars($company, $commonVars, null));
        }
        if ($source === LetterOfGuaranteeIssuance::AGAINST_TD) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/AgainstTdForm', $this->againstTdFormVars($company, $commonVars, null));
        }
        if ($source === LetterOfGuaranteeIssuance::AGAINST_CD) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/AgainstCdForm', $this->againstCdFormVars($company, $commonVars, null));
        }
        if ($source === LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/HundredPercentageCashCoverForm', $this->hundredPercentageCashCoverFormVars($company, $commonVars, null));
        }

        abort(404);
    }
    public function getCommonDataArr():array
    {
        return ['contract_start_date','contract_end_date','currency','limit'];
    }
    public function store(
        Company $company,
        StoreLetterOfGuaranteeIssuanceRequest $request,
        string $source
    ) {
        /**
         * * الحفظ كله جوه ترانزاكشن واحدة
         * * وأي اتصال بأودو بيتنفذ بعد ما الترانزاكشن تكومِت (شوف OdooSync)
         */
        return OdooSync::transaction(function () use ($company, $request, $source) {
            return $this->storeWithinTransaction($company, $request, $source);
        });
    }

    protected function storeWithinTransaction(
        Company $company,
        StoreLetterOfGuaranteeIssuanceRequest $request,
        string $source
    ) {

        $partner = Partner::find($request->get('partner_id'));
        $customerName = $partner->getName() ;
        $lgCode = $request->get('lg_code');
        $isOpeningBalance = $request->get('category_name') == LetterOfGuaranteeIssuance::OPENING_BALANCE;
        $financialInstitutionId = $request->get('financial_institution_id') ;
        $letterOfGuaranteeFacilityId =  $request->get('lg_facility_id') ;
        
        $letterOfGuaranteeFacility = $source == LetterOfGuaranteeIssuance::LG_FACILITY  ? LetterOfGuaranteeFacility::find($letterOfGuaranteeFacilityId) : null;
        if ($source == LetterOfGuaranteeIssuance::LG_FACILITY && is_null($letterOfGuaranteeFacility)) {
			/**
			 * ⚠️ Response made Inertia-aware: the 3 still-Blade sources
			 * (against-cd, against-td, hundred-percentage-cash-cover)
			 * rely on this exact JSON shape for their own AJAX form
			 * handling — UNCHANGED for them. The new lg-facility Vue
			 * form needs a real redirect + flash error instead, since
			 * Inertia doesn't consume arbitrary JSON bodies.
			 */
			if ($request->header('X-Inertia')) {
				return redirect()->back()->with('error', __('No Available Letter Of Guarantee Facility Found !'));
			}
			return response()->json(['status'=>false,'message'=>__('No Available Letter Of Guarantee Facility Found !')]);
            // return redirect()->back()->with('fail', __('No Available Letter Of Guarantee Facility Found !'));
        }
        if ($letterOfGuaranteeFacility instanceof LetterOfGuaranteeFacility) {
            $letterOfGuaranteeFacilityId = $letterOfGuaranteeFacility->id ;
        }
        $model = new LetterOfGuaranteeIssuance();
        $lgCommissionAmount = $request->get('lg_commission_amount', 0);
        $minLgCommissionAmount = $request->get('min_lg_commission_fees', 0);
        $issuanceDate = Carbon::make($request->get('issuance_date'))->format('Y-m-d');
        
        /**
         * ⚠️ REAL BUG FIXED HERE (found and confirmed with the project
         * owner — issuing an LG against TD crashed with "Call to a
         * member function hasOdooIntegrationCredentials() on null").
         * The original Blade forms all include hidden
         * <input name="company_id">, <input name="source">, and
         * <input name="created_by"> fields that storeBasicForm() picks
         * up automatically via its dynamic Schema::hasColumn() check —
         * the new Vue forms (LgFacilityForm.vue, AgainstTdForm.vue)
         * never sent any of the three, since none were ever visible
         * fields, so all three stayed unset both in memory and in the
         * database. Missing 'source' specifically also explains two
         * further symptoms the project owner reported: the Index
         * page's Source column showing the wrong value, and Edit
         * opening the wrong funding-source form entirely (index()
         * builds each row's edit_url from the SAVED source column).
         * Fixed by setting all three explicitly here — BEFORE
         * storeBasicForm(), since that method calls $this->save()
         * internally; setting them after would only fix the in-memory
         * object for this one request, leaving the persisted row still
         * wrong. This makes every form (Vue or still-Blade) behave
         * identically, not a new behavior for any of them.
         */
        $model->company_id = $company->id;
        $model->source = $source;
        $model->created_by = auth()->user()->id;
        /**
         * ⚠️ Bug fix (confirmed with project owner): Bid Bond LGs are
         * never linked to a contract — the Contract/SO fields are
         * hidden on the form for this type. But the frontend only
         * clears them when someone actively switches the LG Type
         * dropdown TO Bid Bond; a record that already loaded as Bid
         * Bond (e.g. one that had a contract attached before being
         * changed to Bid Bond, or edited before this fix existed) kept
         * whatever stale contract_id sat in the hidden field's
         * underlying value, and re-saving it silently preserved that
         * stale link — which is exactly why a cancelled Bid Bond was
         * showing up in a specific contract's Cash Flow report despite
         * Bid Bonds supposedly never being linked to one. Enforced here
         * server-side, unconditionally, regardless of what was
         * submitted or which form/flow it came through.
         */
        if ($request->get('lg_type') === LgTypes::BID_BOND) {
            $request->merge([
                'contract_id' => 'null',
                'purchase_order_id' => 'null',
                'purchase_order_date' => 'null',
            ]);
        }
        $model->storeBasicForm($request);
        $transactionName = $request->get('transaction_name');
        $lgType = $request->get('lg_type');
        $lgAmount = $request->get('lg_amount', 0);
        $currency = $request->get('lg_currency', 0);
        $cdOrTdId = $request->get('cd_or_td_id', 0);
        $cdOrTdAccountTypeId = $request->get('cd_or_td_account_type_id');
    
        $accountType = AccountType::find($cdOrTdAccountTypeId);
    
        if ($accountType && $accountType->isCertificateOfDeposit()) {
            $cdOrTdId = CertificatesOfDeposit::find($cdOrTdId)->id;
        } elseif ($accountType && $accountType->isTimeOfDeposit()) {
            $cdOrTdId = TimeOfDeposit::find($cdOrTdId)->id;
        }
        $cashCoverAmount = $request->get('cash_cover_amount', 0);
        $issuanceFees = $request->get('issuance_fees', 0);
        
        $maxLgCommissionAmount = max($minLgCommissionAmount, $lgCommissionAmount);
        $lgFeesAndCommissionAccountId = $request->get('lg_fees_and_commission_account_id') ;
        $financialInstitutionAccountForFeesAndCommission = FinancialInstitutionAccount::find($lgFeesAndCommissionAccountId);
        $cashCoverDeductedFromAccountId = $request->get('cash_cover_deducted_from_account_id', $lgFeesAndCommissionAccountId);
        
        $financialInstitutionAccountForCashCover = FinancialInstitutionAccount::find($cashCoverDeductedFromAccountId);
        
        $financialInstitutionAccountIdForFeesAndCommission = $financialInstitutionAccountForFeesAndCommission->id;
        $isCdOrTdCashCoverAccount = $model->isCdOrTd();
        $model->handleLgIssuanceCashCoverForOdoo();
        $model->handleIssuanceAndCommissionFeesForOdoo();
            

        $openingBalanceDateOfCurrentAccount = $financialInstitutionAccountForFeesAndCommission->getOpeningBalanceDate();
        
        $financialInstitutionAccountIdForCashCover = $financialInstitutionAccountForCashCover->id ?? 0;
        
        
        
        $customerName = $model->getBeneficiaryName();
        if (!$isOpeningBalance && !$isCdOrTdCashCoverAccount) {
            $model->storeCurrentAccountCreditBankStatement($issuanceDate, $cashCoverAmount, $financialInstitutionAccountIdForCashCover, 0, 1, __('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'en'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'en'), __('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'ar'));
        }
        if (!$isOpeningBalance) {
            $model->storeCurrentAccountCreditBankStatement($issuanceDate, $issuanceFees, $financialInstitutionAccountIdForFeesAndCommission, 0, 1, __('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'en'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'en'), __('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'ar'), false, false, null, 1);
        }
        
        $letterOfGuaranteeStatementCommentEn = LetterOfGuaranteeStatement::generateIssuanceComment('en', $customerName, $transactionName, $lgCode);
        ;
        $letterOfGuaranteeStatementCommentAr = LetterOfGuaranteeStatement::generateIssuanceComment('ar', $customerName, $transactionName, $lgCode);
        ;
        $model->handleLetterOfGuaranteeStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $issuanceDate, 0, 0, $lgAmount, $currency, 0, $cdOrTdId, 'credit-lg-amount', $letterOfGuaranteeStatementCommentEn, $letterOfGuaranteeStatementCommentAr);
        if (!$isOpeningBalance && !$isCdOrTdCashCoverAccount) {
            $model->handleLetterOfGuaranteeCashCoverStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $issuanceDate, 0, $cashCoverAmount, 0, $currency, 0, 'debit-lg-amount');
        }
        
        $lgDurationMonths = $request->get('lg_duration_months', 1);
        $numberOfIterationsForQuarter = ceil($lgDurationMonths / 3);
        $lgCommissionInterval = $request->get('lg_commission_interval');
        
        $model->storeCommissionAmountCreditBankStatement($lgCommissionInterval, $numberOfIterationsForQuarter, $issuanceDate, $openingBalanceDateOfCurrentAccount, $maxLgCommissionAmount, $financialInstitutionAccountIdForFeesAndCommission, $transactionName, $lgType, $isOpeningBalance);
        
		/**
		 * ⚠️ Response made Inertia-aware, same reasoning as the
		 * "no facility found" branch above — the 3 still-Blade sources
		 * need the exact original JSON shape UNCHANGED; the new
		 * lg-facility Vue form needs a real redirect.
		 */
		if ($request->header('X-Inertia')) {
			return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])->with('success', __('Data Store Successfully'));
		}
		return response()->json(['redirectTo'=>route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])]);
		
        // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])->with('success', __('Data Store Successfully'));

    }

    /**
     * Shows the "Edit LG Issuance" form (Vue + Inertia for all sources).
     */
    public function edit(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        // Cancelled LGs can't be edited until they're set back to Running.
        if ($letterOfGuaranteeIssuance->isCancelled()) {
            return redirect()->route('view.letter.of.guarantee.issuance', ['company' => $company->id, 'active' => $letterOfGuaranteeIssuance->getLgType()])
                ->with('fail', __('This LG is cancelled and can no longer be edited. Set it back to Running first.'));
        }
        $commonVars = $this->commonViewVars($company, $source) ;
        if (!count($commonVars['financialInstitutionBanks']) && isset($commonVars['errorMessage'])) {
            return redirect()->back()->with('fail', $commonVars['errorMessage']);
        }
        if ($source === LetterOfGuaranteeIssuance::LG_FACILITY) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/LgFacilityForm', $this->lgFacilityFormVars($company, $commonVars, $letterOfGuaranteeIssuance));
        }
        if ($source === LetterOfGuaranteeIssuance::AGAINST_TD) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/AgainstTdForm', $this->againstTdFormVars($company, $commonVars, $letterOfGuaranteeIssuance));
        }
        if ($source === LetterOfGuaranteeIssuance::AGAINST_CD) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/AgainstCdForm', $this->againstCdFormVars($company, $commonVars, $letterOfGuaranteeIssuance));
        }
        if ($source === LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            return \Inertia\Inertia::render('LetterOfGuaranteeIssuance/HundredPercentageCashCoverForm', $this->hundredPercentageCashCoverFormVars($company, $commonVars, $letterOfGuaranteeIssuance));
        }

        abort(404);
    }

    /**
     * Updates an LG Issuance — but see the class docblock: this
     * doesn't update in place, it deletes the record and everything
     * tied to it, then calls store() fresh. UNCHANGED, deliberately.
     * One guard clause: editing is blocked entirely once an LG has
     * more than 1 renewal-date history entry (silently redirects back
     * without applying anything) — that's original, existing
     * behavior, not something introduced here.
     */
    public function update(Company $company, UpdateLetterOfGuaranteeIssuanceRequest $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        // Cancelled LGs can't be edited until they're set back to Running.
        if ($letterOfGuaranteeIssuance->isCancelled()) {
            $redirectUrl = route('view.letter.of.guarantee.issuance', ['company' => $company->id, 'active' => $request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())]);
            if ($request->header('X-Inertia')) {
                return redirect($redirectUrl)->with('fail', __('This LG is cancelled and can no longer be edited. Set it back to Running first.'));
            }
            return response()->json(['redirectTo' => $redirectUrl]);
        }
        if ($letterOfGuaranteeIssuance->renewalDateHistories->count()  > 1) {
			if ($request->header('X-Inertia')) {
				return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('fail', __('This item can no longer be edited.'));
			}
			return response()->json(['redirectTo'=>route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])]);
            // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('success', __('Data Store Successfully'));
        }
        /**
         * * لو هو
         * * opening
         * * يبقي هنحذف اللي عملناه في اودو
         */
        
        /**
         * * التعديل معمول كـ حذف ثم إنشاء
         * * فلازم يكون كله في ترانزاكشن واحدة
         * * قبل كده لو أي حاجة ضربت في النص كان الخطاب القديم بيروح والجديد بيتعمل ناقص
         */
        /**
         * Wrapped so the delete+create below records as one edit and
         * this issuance's history follows it onto the new row.
         * See App\Support\Activity\ActivityLogger::asUpdate().
         */
        return \App\Support\Activity\ActivityLogger::asUpdate($letterOfGuaranteeIssuance, function () use ($company, $request, $letterOfGuaranteeIssuance, $source) {
            return OdooSync::transaction(function () use ($company, $request, $letterOfGuaranteeIssuance, $source) {
                $letterOfGuaranteeIssuance->deleteAllRelations();
                $letterOfGuaranteeIssuance->delete();

                return $this->storeWithinTransaction($company, $request, $source);
            });
        });

        // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])->with('success', __('Data Store Successfully'));
    }

    /**
     * * هنرجعه تاني لل
     * * running
     * * اكنه كان عامله انه اتلغى بالغلط
     */
    /**
     * Reverses a cancellation — sends the LG back to Running, undoes
     * the cancellation statement entries, and unlinks/recreates the
     * Odoo entry as needed. UNCHANGED, deliberately.
     */
    public function backToRunningStatus(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        return OdooSync::transaction(function () use ($company, $request, $letterOfGuaranteeIssuance, $source) {
            return $this->backToRunningStatusWithinTransaction($company, $request, $letterOfGuaranteeIssuance, $source);
        });
    }

    protected function backToRunningStatusWithinTransaction(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {

        $lgType = $letterOfGuaranteeIssuance->getLgType();
        $currency = $letterOfGuaranteeIssuance->getLgCurrency();
        $issuanceDate = $letterOfGuaranteeIssuance->getIssuanceDate();
        $isCdOrTd = $letterOfGuaranteeIssuance->isCdOrTd();
        $financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCashCoverDeductedFromAccountId());
       
        
        $letterOfGuaranteeIssuanceStatus = LetterOfGuaranteeIssuance::RUNNING ;
        /**
         * * هنشيل قيم ال
         * * letter of guarantee statement
         */
        $financialInstitutionId = $letterOfGuaranteeIssuance->getFinancialInstitutionId() ;

        $letterOfGuaranteeIssuance->update([
           'status' => $letterOfGuaranteeIssuanceStatus,
           'cancellation_date'=>null
        ]);
    
        LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeIssuance->letterOfGuaranteeStatements->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION));
        
        LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeIssuance->letterOfGuaranteeCashCoverStatements->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION));
		
	//	$isAdvancedPayment = $letterOfGuaranteeIssuance->isAdvancedPayment();
		LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeIssuance->letterOfGuaranteeCashCoverStatements->where('type', 'debit-lg-amount'));
		
		$cashCovertToBeRemovedRow = $letterOfGuaranteeIssuance->currentAccountBankStatements->where('lg_advanced_payment_history_id',0)->where('is_debit', 1)->first() ;
		$cashCoverAmount  = $cashCovertToBeRemovedRow ? $cashCovertToBeRemovedRow->debit : 0;

        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeIssuance->currentAccountBankStatements->where('lg_advanced_payment_history_id',0)->where('is_debit', 1));
        
        $letterOfGuaranteeFacility = $letterOfGuaranteeIssuance->letterOfGuaranteeFacility;
    
		
        
        $letterOfGuaranteeFacilityId = $letterOfGuaranteeFacility ? $letterOfGuaranteeFacility->id : null ;

        $letterOfGuaranteeIssuance->handleLetterOfGuaranteeCashCoverStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $issuanceDate, 0, $cashCoverAmount, 0, $currency, 0, 'debit-lg-amount');
		if( $company->hasOdooIntegrationCredentials()){
			foreach (['cancel_journal_entry_id'] as $journalColumnName) {
            $currentJournalEntryId = $letterOfGuaranteeIssuance->{$journalColumnName};
            if ($currentJournalEntryId) {
                OdooSync::defer(function () use ($company, $currentJournalEntryId) {
                    (new LetterOfGuaranteeService($company))->unlink($currentJournalEntryId);
                }, null, 'Unlink Odoo journal entry #'.$currentJournalEntryId);
            }
        }

		}
		 if ($company->hasOdooIntegrationCredentials() && !$isCdOrTd && $company->withinIntegrationDate($issuanceDate)) {
            /**
             * * الاتصال بأودو بيتأجل لبعد ما الترانزاكشن تكومِت
             */
            OdooSync::defer(function () use ($company, $letterOfGuaranteeIssuance, $financialInstitutionAccount, $issuanceDate, $cashCoverAmount, $lgType) {
                $currency = $financialInstitutionAccount->getCurrency();
                $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
                $fromAccountNumber = $financialInstitutionAccount->getAccountNumber();
                $journalId = $financialInstitutionAccount->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
                $accountOdooId = $financialInstitutionAccount->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
                $odooCurrencyId = Currency::getOdooId($currency);
                $lgOdooAccountId = FinancialInstitutionAccount::getLetterOfGuaranteeOdooIdFromType($lgType, $company->id);
                $ref = $letterOfGuaranteeIssuance->generateIssuanceRef();
                $message = $letterOfGuaranteeIssuance->generateIssuanceMessage();
                $analytic_distribution = $letterOfGuaranteeIssuance->formatAnalysisDistribution() ;
                $result = $odooLetterOfGuaranteeIssuance->createLgIssuanceCashCover($issuanceDate, $cashCoverAmount, $journalId, $odooCurrencyId, $lgOdooAccountId, $accountOdooId, $letterOfGuaranteeIssuance->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
                $letterOfGuaranteeIssuance->account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
                $letterOfGuaranteeIssuance->journal_entry_id=$result['journal_entry_id'];
                $letterOfGuaranteeIssuance->save();
            }, $letterOfGuaranteeIssuance, 'Create Odoo LG cash cover');
        }
		
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('success', __('Data Store Successfully'));
    }
    
    
    /**
     * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال
     * * letter of guarantee statements
     */
    /**
     * Cancels a running/expired LG — posts cancellation entries to the
     * LG/cash-cover statements, refunds cash cover to the bank account,
     * and reverses the Odoo entry. UNCHANGED, deliberately.
     */
    public function cancel(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        /**
         * ⚠️ WRAPPED IN A TRANSACTION (2026-07-25, confirmed with project
         * owner): previously the `status => cancelled` update below was its
         * own committed query, separate from everything after it (the Odoo
         * call, the for-cancellation statement row the reports rely on, the
         * cash-cover refund). If anything after the update threw — e.g. the
         * "Missing company Odoo DB URL/Name" bug already fixed elsewhere in
         * this file — the LG was left stuck with status = 'cancelled' but
         * none of the follow-up records, which is exactly what silently
         * dropped some Bid Bonds out of the LG-by-name reports (their
         * cancellation_statement join had nothing to match). Wrapping the
         * whole method in DB::transaction() means any failure now rolls
         * back the status change too, instead of leaving a half-cancelled
         * LG behind.
         */
        return OdooSync::transaction(function () use ($company, $request, $letterOfGuaranteeIssuance, $source) {
            /**
             * @var LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance
             */
            $letterOfGuaranteeIssuanceStatus = LetterOfGuaranteeIssuance::CANCELLED ;

            /**
             * * هنشيل قيم ال
             * * letter of guarantee statement
             */
            $financialInstitutionId = $letterOfGuaranteeIssuance->financial_institution_id ;
        //    $financialInstitution = FinancialInstitution::find($financialInstitutionId);

            $cancellationDate = Carbon::make($request->get('cancellation_date', now()->format('Y-m-d')))->format('Y-m-d') ;

            $letterOfGuaranteeIssuance->update([
               'status' => $letterOfGuaranteeIssuanceStatus,
               'cancellation_date'=>$cancellationDate
            ]);
            $letterOfGuaranteeFacility = $letterOfGuaranteeIssuance->letterOfGuaranteeFacility;
            $lgType = $letterOfGuaranteeIssuance->getLgType();
            // $isAdvancedPayment =  $letterOfGuaranteeIssuance->isAdvancedPayment() ;
            //	$cashCoverRate = $letterOfGuaranteeIssuance->getCashCoverRate() / 100;
            $amount = $letterOfGuaranteeIssuance->getCancellationAmount();
            $cashCoverAmount = $letterOfGuaranteeIssuance->getCashCoverCancellationAmount();

            $letterOfGuaranteeFacilityId = $letterOfGuaranteeFacility ? $letterOfGuaranteeFacility->id : null ;
            $partnerName = $letterOfGuaranteeIssuance->getBeneficiaryName();
            $transactionName = $letterOfGuaranteeIssuance->getTransactionName();
            $lgCode = $letterOfGuaranteeIssuance->getLgCode();
            // $isOpeningBalance = $letterOfGuaranteeIssuance->isOpeningBalance();

            $financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCashCoverDeductedFromAccountId());
            $ref = $letterOfGuaranteeIssuance->generateCancelRef();
            $message = $letterOfGuaranteeIssuance->generateCancelMessage();
            $cashCoverAmount = $letterOfGuaranteeIssuance->getCashCoverCancellationAmount();
    		//////////////////////ddddddddd
            $letterOfGuaranteeIssuance->cancelOdooLg($cancellationDate, $cashCoverAmount, $ref, $message,null,'cancel_journal_entry_id');
            $commentEn = LetterOfGuaranteeStatement::generateCancelComment('en', $lgType, $partnerName, $transactionName, $lgCode);
            $commentAr = LetterOfGuaranteeStatement::generateCancelComment('ar', $lgType, $partnerName, $transactionName, $lgCode);
            $letterOfGuaranteeIssuance->handleLetterOfGuaranteeStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $cancellationDate, 0, $amount, 0, $letterOfGuaranteeIssuance->getLgCurrency(), 0, $letterOfGuaranteeIssuance->getCdOrTdId(), LetterOfGuaranteeIssuance::FOR_CANCELLATION, $commentEn, $commentAr);
            $letterOfGuaranteeIssuance->handleLetterOfGuaranteeCashCoverStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $cancellationDate, 0, 0, $cashCoverAmount, $letterOfGuaranteeIssuance->getLgCurrency(), 0, LetterOfGuaranteeIssuance::FOR_CANCELLATION);
            if ($financialInstitutionAccount) {
                $financialInstitutionAccountId = $financialInstitutionAccount->id;
                $debitCommentEn = CurrentAccountBankStatement::generateRefundLgCashCoverComment('en', $partnerName, $transactionName, $lgCode);
                ;
                $debitCommentAr = CurrentAccountBankStatement::generateRefundLgCashCoverComment('ar', $partnerName, $transactionName, $lgCode);
                ;
                $letterOfGuaranteeIssuance->storeCurrentAccountDebitBankStatement($cancellationDate, $cashCoverAmount, $financialInstitutionAccountId, 0, $letterOfGuaranteeIssuance->id, $debitCommentEn, $debitCommentAr);
            }
            return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('success', __('Data Store Successfully'));
        });
    }
    
    
    /**
     * * دلوقت دا خطاب ضمان .. فا اليوزر بيدخول يقول انا سددت جزء فلاني من قيمة ال
     * * lg amount
     * * وبالتالي بنقص القيمة دي من اللي الفلوس من قيمة ال
     * * lg amount
     * * بس في نفس الوقت بنحتفظ بقيمة ال
     * * lg amount
     * * الاصليه علشان التقارير
     * * letter of guarantee statements
     */
    /**
     * Records a new advanced-payment (amount-to-be-decreased) entry —
     * posts the LG/cash-cover statement reduction, refunds cash cover
     * to the bank account if applicable, and posts the Odoo entry.
     * UNCHANGED, deliberately.
     */
    public function applyAmountToBeDecreased(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        return OdooSync::transaction(function () use ($company, $request, $letterOfGuaranteeIssuance, $source) {
            return $this->applyAmountToBeDecreasedWithinTransaction($company, $request, $letterOfGuaranteeIssuance, $source);
        });
    }

    protected function applyAmountToBeDecreasedWithinTransaction(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {

        $financialInstitutionId = $letterOfGuaranteeIssuance->financial_institution_id ;
        /**
         * @var LetterOfGuaranteeIssuanceAdvancedPaymentHistory $letterOfGuaranteeIssuanceAdvancedPaymentHistory
         */
        $letterOfGuaranteeIssuanceAdvancedPaymentHistory = new LetterOfGuaranteeIssuanceAdvancedPaymentHistory();
        $decreaseDate = $request->get('date', now()->format('Y-m-d')) ;
        $decreaseDate = Carbon::make($decreaseDate)->format('Y-m-d');
        $decreaseAmount = $request->get('amount', 0);
        $customerName  = $letterOfGuaranteeIssuance->getBeneficiaryName();
        $cashCoverAmount = $letterOfGuaranteeIssuance->getCashCoverRate() /100  * $decreaseAmount ;
        $letterOfGuaranteeFacility = $source == LetterOfGuaranteeIssuance::LG_FACILITY  ? $letterOfGuaranteeIssuance->letterOfGuaranteeFacility : null;
        $letterOfGuaranteeFacilityId =  null ;
        
        $lgType =$letterOfGuaranteeIssuance->getLgType();
        $currency = $letterOfGuaranteeIssuance->getLgCurrency();
        $cdOrTdId = $letterOfGuaranteeIssuance->getCdOrTdId() ;
        $financialInstitutionAccountId = null ;
        if ($letterOfGuaranteeIssuance->cash_cover_deducted_from_account_type == 27) {
            $financialInstitutionAccountId = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCashCoverDeductedFromAccountId())->id;
        }
        
        if ($source == LetterOfGuaranteeIssuance::LG_FACILITY && is_null($letterOfGuaranteeFacility)) {
            return redirect()->back()->with('fail', __('No Available Letter Of Guarantee Facility Found !'));
        }
        if ($letterOfGuaranteeFacility instanceof LetterOfGuaranteeFacility) {
            $letterOfGuaranteeFacilityId = $letterOfGuaranteeFacility->id ;
        }
        /**
		 * @var LetterOfGuaranteeIssuanceAdvancedPaymentHistory $letterOfGuaranteeIssuanceAdvancedPaymentHistory
		 */
        $letterOfGuaranteeIssuanceAdvancedPaymentHistory = $letterOfGuaranteeIssuance->advancedPaymentHistories()->create([
            'date'=>$decreaseDate,
            'amount'=>$decreaseAmount,
            'company_id'=>$company->id
        ]);
        $partnerName = $letterOfGuaranteeIssuance->getBeneficiaryName();
        $transactionName = $letterOfGuaranteeIssuance->getTransactionName();
        $lgCode = $letterOfGuaranteeIssuance->getLgCode();
        $commentEn = LetterOfGuaranteeStatement::generateAdvancedPaymentLgComment('en', $partnerName, $transactionName, $lgCode);
        $commentAr = LetterOfGuaranteeStatement::generateAdvancedPaymentLgComment('ar', $partnerName, $transactionName, $lgCode);
        $letterOfGuaranteeIssuanceAdvancedPaymentHistory->handleLetterOfGuaranteeStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $decreaseDate, 0, $decreaseAmount, 0, $currency, $letterOfGuaranteeIssuanceAdvancedPaymentHistory->id, $cdOrTdId, LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED, $commentEn, $commentAr);
        if ($financialInstitutionAccountId) {
            $letterOfGuaranteeIssuanceAdvancedPaymentHistory->handleLetterOfGuaranteeCashCoverStatement($financialInstitutionId, $source, $letterOfGuaranteeFacilityId, $lgType, $company->id, $decreaseDate, 0, 0, $cashCoverAmount, $currency, $letterOfGuaranteeIssuanceAdvancedPaymentHistory->id, LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED);
            $commentEn = __('Refund Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'en'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'en') ;
            $commentAr = __('Refund Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType'=>__($lgType, [], 'en'),'customerName'=>$customerName,'transactionName'=>$transactionName], 'ar') ;
            $letterOfGuaranteeIssuanceAdvancedPaymentHistory->storeCurrentAccountDebitBankStatement($decreaseDate, $cashCoverAmount, $financialInstitutionAccountId, $letterOfGuaranteeIssuanceAdvancedPaymentHistory->id, $letterOfGuaranteeIssuance->id, $commentEn, $commentAr);
            $ref = $letterOfGuaranteeIssuance->generateDecreasedRef();
            $message = $letterOfGuaranteeIssuance->generateDecreasedMessage();
            $letterOfGuaranteeIssuance->cancelOdooLg($decreaseDate, $cashCoverAmount, $ref, $message,$letterOfGuaranteeIssuanceAdvancedPaymentHistory);
        }
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$letterOfGuaranteeIssuance->getLgType()])->with('success', __('Data Store Successfully'));
    }
    
    /**
     * Edits an existing advanced-payment (amount-to-be-decreased)
     * entry — adjusts the linked LG/cash-cover statements, the bank
     * statement debit, and the Odoo entry to match. UNCHANGED
     * financial logic, deliberately. The response was changed from a
     * raw JSON body to a redirect, for Inertia compatibility — same
     * fix already applied to editRate()/editLendingInformation()
     * elsewhere.
     */
    public function editAmountToBeDecreased(Company $company, Request $request, LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory, string $source)
    {
        return OdooSync::transaction(function () use ($company, $request, $lgAdvancedPaymentHistory, $source) {
            return $this->editAmountToBeDecreasedWithinTransaction($company, $request, $lgAdvancedPaymentHistory, $source);
        });
    }

    protected function editAmountToBeDecreasedWithinTransaction(Company $company, Request $request, LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory, string $source)
    {

        $decreaseDate = Carbon::make($request->get('decrease_date', now()->format('Y-m-d')))->format('Y-m-d');
        $decreaseAmount = $request->get('amount_to_be_decreased', 0);
        $lgAdvancedPaymentHistory->update([
            'amount'=>$decreaseAmount ,
            'date'=>$decreaseDate
        ]);
        $letterOfGuaranteeIssuance = $lgAdvancedPaymentHistory->letterOfGuaranteeIssuance;
        $financialInstitutionId = $letterOfGuaranteeIssuance->financial_institution_id ;
        /**
         * @var LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory
         */

        $cashCoverAmount = $letterOfGuaranteeIssuance->getCashCoverRate() /100  * $decreaseAmount ;
    
        $letterOfGuaranteeFacility = $source == LetterOfGuaranteeIssuance::LG_FACILITY  ? $letterOfGuaranteeIssuance->letterOfGuaranteeFacility : null;

        if ($source == LetterOfGuaranteeIssuance::LG_FACILITY && is_null($letterOfGuaranteeFacility)) {
            return redirect()->back()->with('fail', __('No Available Letter Of Guarantee Facility Found !'));
        }
        $letterOfGuaranteeStatement = $lgAdvancedPaymentHistory->letterOfGuaranteeStatements->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->first();

        $letterOfGuaranteeStatement->handleFullDateAfterDateEdit($decreaseDate, $decreaseAmount, 0);
    
        $letterOfGuaranteeCashCoverStatement =  $lgAdvancedPaymentHistory->letterOfGuaranteeCashCoverStatements->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->first();
        $letterOfGuaranteeCashCoverStatement ? $letterOfGuaranteeCashCoverStatement->handleFullDateAfterDateEdit($decreaseDate, 0, $cashCoverAmount) : null;
        
        $currentAccountDebitBankStatement = $lgAdvancedPaymentHistory->currentAccountDebitBankStatement;
        $currentAccountDebitBankStatement ? $currentAccountDebitBankStatement->handleFullDateAfterDateEdit($decreaseDate, $cashCoverAmount, 0):null;
    
        $ref = $letterOfGuaranteeIssuance->generateDecreasedRef();
        $message = $letterOfGuaranteeIssuance->generateDecreasedMessage();
        $letterOfGuaranteeIssuance->cancelOdooLg($decreaseDate, $cashCoverAmount, $ref, $message,$lgAdvancedPaymentHistory);
            
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$letterOfGuaranteeIssuance->getLgType()])->with('success', __('Data Store Successfully'));
        // return redirect()->route('view.letter.of.guarantee.issuance',['company'=>$company->id,'active'=>$letterOfGuaranteeIssuance->getLgType()])->with('success',__('Data Store Successfully'));
    }
    
    /**
     * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال
     * * letter of guarantee statements
     *
     * Deletes an advanced-payment entry and reverses everything it
     * posted (statements, bank statement debit, Odoo entry).
     * UNCHANGED, deliberately.
     */
    public function deleteAdvancedPayment(Company $company, Request $request, LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory)
    {
        OdooSync::transaction(function () use ($lgAdvancedPaymentHistory) {
            $lgAdvancedPaymentHistory->deleteAllRelations();
            $lgAdvancedPaymentHistory->delete();
        });
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$lgAdvancedPaymentHistory->letterOfGuaranteeIssuance->getLgType()])->with('success',__('Data Store Successfully'));
    
        
    }
    


    /**
     * Deletes an LG Issuance and all its related statements/Odoo
     * entries. UNCHANGED, deliberately.
     */
    public function destroy(Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance)
    {
        // Cancelled LGs can't be deleted until they're set back to Running.
        if ($letterOfGuaranteeIssuance->isCancelled()) {
            return redirect()->route('view.letter.of.guarantee.issuance', ['company' => $company->id, 'active' => $letterOfGuaranteeIssuance->getLgType()])
                ->with('fail', __('This LG is cancelled and can no longer be deleted. Set it back to Running first.'));
        }
        $lgType = $letterOfGuaranteeIssuance->getLgType();
        OdooSync::transaction(function () use ($letterOfGuaranteeIssuance) {
            $letterOfGuaranteeIssuance->deleteAllRelations();
            $letterOfGuaranteeIssuance->delete();
        });
        return redirect()->route('view.letter.of.guarantee.issuance',['company'=>$company->id,'active'=>$lgType]);
    }
    
    /**
     * Pure AJAX data endpoint consumed by the (not-yet-migrated)
     * create/edit forms. UNCHANGED, deliberately.
     */
    public function getBeneficiaryNameByCurrency(Request $request , Company $company)
    {
        $currencyName = $request->get('currencyName');
        $beneficiaries = $company->letterOfGuaranteeIssuances->where('lg_currency',$currencyName)->load('beneficiary')->pluck('beneficiary.name','beneficiary.id')->toArray() ;
        return response()->json([
            'beneficiaries'=>$beneficiaries
        ]);
    }

    /**
     * Pure AJAX data endpoint consumed by the (not-yet-migrated)
     * create/edit forms. UNCHANGED, deliberately.
     */
    public function getBankNameByCurrency(Request $request , Company $company)
    {
        $currencyName = $request->get('currencyName');
        $banks = $company->letterOfGuaranteeIssuances->where('lg_currency',$currencyName)->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray() ;
        return response()->json([
            /**
             * * ال كي دا مستخدم هنا
             * * CustomerInvoiceDashboardController
             */
            'banks'=>$banks
        ]);
    }
}
