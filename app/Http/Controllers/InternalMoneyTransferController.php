<?php
namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\InternalMoneyTransfer;
use App\Http\Requests\StoreInternalMoneyTransferRequest;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * InternalMoneyTransferController
 * ------------------------------------------------------------------
 * Very close sibling of BuyOrSellCurrenciesController — same four
 * transfer types (Bank→Bank, Bank→Safe, Safe→Bank, Safe→Safe), same
 * Bank-vs-Branch field-group pattern. The real difference: there's no
 * currency conversion here (one `currency` + one `amount`, not a
 * sell/buy pair with an exchange rate), and — unlike BuyOrSellCurrency,
 * where `type` was a single shared form field — here each type has its
 * own route (`/internal-money-transfers/{type}/...`) and, in the old
 * app, its own dedicated Blade form file.
 *
 * ── Two real, pre-existing bugs found while reading the old code ────
 *   1. InternalMoneyTransfer::getAllTypes() (model, UNTOUCHED here)
 *      only returns 3 of the 4 types — Safe→Safe is missing. The old
 *      index() built its date-range defaults by looping that list, so
 *      Safe→Safe never got a default date range and its query ran as
 *      `WHERE transfer_date BETWEEN NULL AND NULL` — meaning the Safe
 *      To Safe tab has been silently returning zero rows in
 *      production, regardless of actual data. This rewrite's index()
 *      doesn't call getAllTypes() at all — it uses its own literal
 *      list of the four types that actually have routes/forms (same
 *      four the old Blade's tab markup already hardcoded), which
 *      fixes the symptom without changing the model method itself.
 *   2. The old safe-to-safe-form.blade.php had a copy-paste bug: its
 *      hidden `type` input said `value="bank-to-safe"` instead of
 *      `"safe-to-safe"`. Since storeBasicForm() (untouched) writes
 *      any request field matching a real column — including `type` —
 *      AFTER store() sets it correctly from the route, that wrong
 *      hidden value was overwriting the correct one. Every "Safe To
 *      Safe" transfer saved through that form has actually been
 *      recorded with type = 'bank-to-safe'. This rewrite's Form.vue
 *      always sends the correct `type` (from the route, via a prop —
 *      never a hand-typed per-form hidden value), which avoids the
 *      bug rather than reproducing it. Flagging both here since
 *      they affect real stored data, not just the UI.
 *
 * store()/update() post real money movements the same way
 * BuyOrSellCurrenciesController's do — NONE of that touched here.
 *
 * ── Frontend migration status ───────────────────────────────────────
 *   ✅ index() → MIGRATED to Vue + Inertia
 *      (resources/js/Pages/InternalMoneyTransfer/Index.vue).
 *   ✅ create()/edit() → MIGRATED to Vue + Inertia. One shared page,
 *      resources/js/Pages/InternalMoneyTransfer/Form.vue, for all
 *      four types and both modes — replacing what used to be four
 *      separate nearly-identical Blade files.
 */
class InternalMoneyTransferController
{
    use GeneralFunctions;

    /** The four types that actually have routes/forms — see bug #1 above. */
    protected function allTypes(): array
    {
        return [
            InternalMoneyTransfer::BANK_TO_BANK => __('Bank To Bank'),
            InternalMoneyTransfer::SAFE_TO_BANK => __('Safe To Bank'),
            InternalMoneyTransfer::BANK_TO_SAFE => __('Bank To Safe'),
            InternalMoneyTransfer::SAFE_TO_SAFE => __('Safe To Safe'),
        ];
    }

    protected function applyTypeFilters($query, Request $request, string $type, string $currentType, array $searchableColumns)
    {
        if ($type !== $currentType) {
            return $query;
        }
        $searchValue = $request->get('value');
        return $query->when($searchValue, function ($query) use ($searchValue, $searchableColumns) {
            return $query->where(function ($query) use ($searchValue, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $searchValue . '%');
                }
            });
        });
    }

    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $currentType = $request->get('active', InternalMoneyTransfer::BANK_TO_BANK);

        $filterDates = [];
        foreach ($this->allTypes() as $type => $label) {
            $startDate = $request->has('startDate') ? $request->input('startDate.' . $type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.' . $type) : now()->format('Y-m-d');
            $filterDates[$type] = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        }

        $bankToBankStartDate = $filterDates[InternalMoneyTransfer::BANK_TO_BANK]['startDate'];
        $bankToBankEndDate = $filterDates[InternalMoneyTransfer::BANK_TO_BANK]['endDate'];
        $bankToBankInternalMoneyTransfers = $company->bankToBankInternalMoneyTransfers()
            ->whereBetween('transfer_date', [$bankToBankStartDate, $bankToBankEndDate]);
        $bankToBankInternalMoneyTransfers = $this->applyTypeFilters($bankToBankInternalMoneyTransfers, $request, InternalMoneyTransfer::BANK_TO_BANK, $currentType, ['from_account_number', 'to_account_number']);
        $bankToBankInternalMoneyTransfers = $bankToBankInternalMoneyTransfers
            ->orderByDesc('transfer_date')
            ->paginate($paginationPerPage, ['*'], 'bankToBankInternalMoneyTransfersPage')
            ->withQueryString();

        $safeToBankStartDate = $filterDates[InternalMoneyTransfer::SAFE_TO_BANK]['startDate'];
        $safeToBankEndDate = $filterDates[InternalMoneyTransfer::SAFE_TO_BANK]['endDate'];
        $safeToBankInternalMoneyTransfers = $company->safeToBankInternalMoneyTransfers()
            ->whereBetween('transfer_date', [$safeToBankStartDate, $safeToBankEndDate]);
        $safeToBankInternalMoneyTransfers = $this->applyTypeFilters($safeToBankInternalMoneyTransfers, $request, InternalMoneyTransfer::SAFE_TO_BANK, $currentType, ['to_account_number']);
        $safeToBankInternalMoneyTransfers = $safeToBankInternalMoneyTransfers
            ->orderByDesc('transfer_date')
            ->paginate($paginationPerPage, ['*'], 'safeToBankInternalMoneyTransfersPage')
            ->withQueryString();

        $bankToSafeStartDate = $filterDates[InternalMoneyTransfer::BANK_TO_SAFE]['startDate'];
        $bankToSafeEndDate = $filterDates[InternalMoneyTransfer::BANK_TO_SAFE]['endDate'];
        $bankToSafeInternalMoneyTransfers = $company->bankToSafeInternalMoneyTransfers()
            ->whereBetween('transfer_date', [$bankToSafeStartDate, $bankToSafeEndDate]);
        $bankToSafeInternalMoneyTransfers = $this->applyTypeFilters($bankToSafeInternalMoneyTransfers, $request, InternalMoneyTransfer::BANK_TO_SAFE, $currentType, ['from_account_number']);
        $bankToSafeInternalMoneyTransfers = $bankToSafeInternalMoneyTransfers
            ->orderByDesc('transfer_date')
            ->paginate($paginationPerPage, ['*'], 'bankToSafeInternalMoneyTransfersPage')
            ->withQueryString();

        $safeToSafeStartDate = $filterDates[InternalMoneyTransfer::SAFE_TO_SAFE]['startDate'];
        $safeToSafeEndDate = $filterDates[InternalMoneyTransfer::SAFE_TO_SAFE]['endDate'];
        $safeToSafeInternalMoneyTransfers = $company->safeToSafeInternalMoneyTransfers()
            ->whereBetween('transfer_date', [$safeToSafeStartDate, $safeToSafeEndDate]);
        $safeToSafeInternalMoneyTransfers = $this->applyTypeFilters($safeToSafeInternalMoneyTransfers, $request, InternalMoneyTransfer::SAFE_TO_SAFE, $currentType, []);
        $safeToSafeInternalMoneyTransfers = $safeToSafeInternalMoneyTransfers
            ->orderByDesc('transfer_date')
            ->paginate($paginationPerPage, ['*'], 'safeToSafeInternalMoneyTransfersPage')
            ->withQueryString();

        $models = [
            InternalMoneyTransfer::BANK_TO_BANK => $bankToBankInternalMoneyTransfers,
            InternalMoneyTransfer::SAFE_TO_BANK => $safeToBankInternalMoneyTransfers,
            InternalMoneyTransfer::BANK_TO_SAFE => $bankToSafeInternalMoneyTransfers,
            InternalMoneyTransfer::SAFE_TO_SAFE => $safeToSafeInternalMoneyTransfers,
        ];

        $mapRow = function (InternalMoneyTransfer $model) use ($company) {
            return [
                'id' => $model->id,
                'transfer_date_formatted' => $model->getTransferDateFormatted(),
                'transfer_days' => $model->getTransferDays(),
                'amount_formatted' => $model->getAmountFormatted(),
                'currency' => $model->getCurrencyFormatted(),
                'cheque_number' => $model->getChequeNumber(),
                'from_bank_name_en' => optional(optional($model->fromBank)->bank)->name_en,
                'from_bank_name_ar' => optional(optional($model->fromBank)->bank)->name_ar,
                'from_account_type_name' => $model->getFromAccountTypeName(),
                'from_account_number' => $model->getFromAccountNumber(),
                'to_bank_name_en' => optional(optional($model->toBank)->bank)->name_en,
                'to_bank_name_ar' => optional(optional($model->toBank)->bank)->name_ar,
                'to_account_type_name' => $model->getToAccountTypeName(),
                'to_account_number' => $model->getToAccountNumber(),
                'from_branch_name' => $model->getFromBranchName(),
                'to_branch_name' => $model->getToBranchName(),
                'user_comment' => $model->hasComment() ? $model->getUserComment() : null,
                'is_fully_integrated_with_odoo' => $company->hasOdooIntegrationCredentials() && $model->fullyIntegratedWithOdoo(),
                'odoo_reference_names' => $model->getOdooReferenceNames(),
                'edit_url' => route('internal-money-transfers.edit', ['company' => $company->id, 'type' => $model->getType(), 'internal_money_transfer' => $model->id]),
                'delete_url' => route('internal-money-transfers.destroy', ['company' => $company->id, 'type' => $model->getType(), 'internal_money_transfer' => $model->id]),
            ];
        };

        $tabs = [];
        foreach ($models as $type => $paginator) {
            $tabs[$type] = [
                'label' => $this->allTypes()[$type],
                'rows' => $paginator->through($mapRow),
                'startDate' => $filterDates[$type]['startDate'],
                'endDate' => $filterDates[$type]['endDate'],
            ];
        }

        return \Inertia\Inertia::render('InternalMoneyTransfer/Index', [
            'company' => ['id' => $company->id],
            'activeTab' => $currentType,
            'allTypes' => $this->allTypes(),
            'tabs' => $tabs,
            'searchValue' => $request->get('value'),
            'canCreate' => hasAuthFor('create internal money transfer'),
            'canUpdate' => hasAuthFor('update internal money transfer'),
            'canDelete' => hasAuthFor('delete internal money transfer'),
            'indexUrl' => route('internal-money-transfers.index', ['company' => $company->id]),
            'createUrls' => collect($this->allTypes())->mapWithKeys(fn ($label, $type) => [
                $type => route('internal-money-transfers.create', ['company' => $company->id, 'type' => $type]),
            ]),
        ]);
    }

	public function create(Company $company,$type)
	{
		return \Inertia\Inertia::render('InternalMoneyTransfer/Form', $this->buildFormProps($company, $type, null));
    }

	/**
	 * Turns getCommonViewVars()'s existing output plus (in edit mode)
	 * the model's own data into the flat, pre-formatted prop shape
	 * Inertia needs. getCommonViewVars() and every getter called on
	 * $model below are pre-existing and UNCHANGED.
	 */
	protected function buildFormProps(Company $company, string $type, ?InternalMoneyTransfer $model): array
	{
		$commonVars = $this->getCommonViewVars($company, $type, $model);

		return [
			'company' => ['id' => $company->id],
			'type' => $type,
			'mode' => $model ? 'edit' : 'create',
			'locale' => app()->getLocale(),
			'allTypes' => $this->allTypes(),
			'currencies' => getCurrencies(),
			'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
			'accountTypes' => collect($commonVars['accountTypes'])->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
			'branches' => collect($commonVars['selectedBranches'])->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
			'model' => $model ? [
				'id' => $model->id,
				'transfer_date' => $model->getTransferDate(),
				'transfer_days' => $model->getTransferDays(),
				'amount' => $model->getAmount(),
				'currency' => $model->getCurrency(),
				'cheque_number' => $model->getChequeNumber(),
				'from_bank_id' => $model->getFromBankId(),
				'from_account_type_id' => $model->getFromAccountTypeId(),
				'from_account_number' => $model->getFromAccountNumber(),
				'to_bank_id' => $model->getToBankId(),
				'to_account_type_id' => $model->getToAccountTypeId(),
				'to_account_number' => $model->getToAccountNumber(),
				'from_branch_id' => $model->getFromBranchId(),
				'to_branch_id' => $model->getToBranchId(),
				'user_comment' => $model->getUserComment(),
			] : null,
			'submitUrl' => $model
				? route('internal-money-transfers.update', ['company' => $company->id, 'type' => $type, 'internal_money_transfer' => $model->id])
				: route('internal-money-transfers.store', ['company' => $company->id, 'type' => $type]),
			'backUrl' => route('internal-money-transfers.index', ['company' => $company->id]),
			'getBranchesForCurrencyUrl' => route('get.branch.based.on.currency', ['company' => $company->id]),
			'getBankBalanceUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
			'getCashSafeBalanceUrl' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
		];
	}
	public function getCommonViewVars(Company $company,string $type,$model = null)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();
		return [
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitutionBanks'=>$financialInstitutionBanks,
			'accountTypes'=>$accountTypes,
			'model'=>$model,
			'type'=>$type
		];
	}
	
	public function store(Company $company , string $type  , StoreInternalMoneyTransferRequest $request){
		$internalMoneyTransfer = new InternalMoneyTransfer ;
		$internalMoneyTransfer->type = $type ;
		$transferDate = Carbon::make($request->get('transfer_date'))->format('Y-m-d') ;
		$receivingDate = Carbon::make($transferDate)->addDays($request->get('transfer_days',0))->format('Y-m-d');
		$transferAmount = $request->get('amount') ;
		$internalMoneyTransfer->storeBasicForm($request);
		$fromFinancialInstitutionId = $request->get('from_bank_id');
		// $fromFinancialInstitution = FinancialInstitution::find($fromFinancialInstitutionId);
		$toFinancialInstitutionId = $request->get('to_bank_id');
		// $toFinancialInstitution = FinancialInstitution::find($request->get('to_bank_id'));
		$fromAccountTypeId = $request->get('from_account_type_id');
		$toAccountTypeId = $request->get('to_account_type_id');
		$fromAccountNumber = $request->get('from_account_number');
		$toAccountNumber = $request->get('to_account_number');
		$toBranchId = $request->get('to_branch_id');
		$fromBranchId = $request->get('from_branch_id');
		$currencyName = $request->get('currency');	
		$fromAccountType = AccountType::find($fromAccountTypeId);
		$toAccountType = AccountType::find($toAccountTypeId);
		
		// $fromJournalId = null;
		// $toJournalId =  null ;
		
		if($type === InternalMoneyTransfer::BANK_TO_BANK){
			
			$internalMoneyTransfer->handleBankToBankTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId , $toAccountType ,  $toAccountNumber,$toFinancialInstitutionId,$transferDate,$receivingDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::BANK_TO_SAFE ){
			
			$internalMoneyTransfer->handleBankToSafeTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId ,$toBranchId , $currencyName , $transferDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::SAFE_TO_BANK ){
			$internalMoneyTransfer->handleSafeToBankTransfer($company->id , $toAccountType , $toAccountNumber  , $toFinancialInstitutionId ,$fromBranchId , $currencyName , $transferDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::SAFE_TO_SAFE ){
			$internalMoneyTransfer->handleSafeToSafeTransfer($company->id ,$toBranchId ,$fromBranchId , $currencyName , $transferDate,$transferAmount);
		}

		$internalMoneyTransfer->handleOdooTransfer();
		
		$activeTab = $type ; 
		
	
		return redirect()->route('internal-money-transfers.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}

	public function edit(Company $company,string $type,InternalMoneyTransfer $internalMoneyTransfer)
	{
		return \Inertia\Inertia::render('InternalMoneyTransfer/Form', $this->buildFormProps($company, $type, $internalMoneyTransfer));
    }
	
	public function update(Company $company , string $type , StoreInternalMoneyTransferRequest $request , InternalMoneyTransfer $internalMoneyTransfer){

		$internalMoneyTransfer->deleteRelations();
		$internalMoneyTransfer->delete();
		$this->store($company,$type,$request);
		$activeTab = $type ;
		return redirect()->route('internal-money-transfers.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company , string $type, InternalMoneyTransfer $internalMoneyTransfer)
	{
	
		$internalMoneyTransfer->deleteRelations();
		$internalMoneyTransfer->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
}
