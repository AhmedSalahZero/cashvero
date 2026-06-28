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
use App\Models\PurchaseOrder;
use App\Models\TimeOfDeposit;
use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LetterOfGuaranteeIssuanceController
{
    use GeneralFunctions;
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
    public function index(Company $company, Request $request)
    {
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
        // $company->load('letterOfGuaranteeIssuances');
        // $company->load('letterOfGuaranteeIssuances.financialInstitutionBank', 'letterOfGuaranteeIssuances.advancedPaymentHistories', 'letterOfGuaranteeIssuances.beneficiary');
        $numberOfMonthsBetweenEndDateAndStartDate = 60 ;
        $activeLgType = $request->get('active', LgTypes::BID_BOND) ;
        $filterDates = [];
        $searchFields = [];
        $models = [];
        foreach (getLgTypes() as $type=>$typeNameFormatted) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
            $filterDates[$type] = [
                'startDate'=>$startDate,
                'endDate'=>$endDate
            ];
            $models[$type]   = $company->letterOfGuaranteeIssuances()
			->whereBetween('issuance_date', [$startDate, $endDate])
			->where('lg_type', $type)->with('financialInstitutionBank', 'advancedPaymentHistories', 'beneficiary') ;
            if ($type == $activeLgType) {
				$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$models[$type] = $models[$type]->when($searchFieldName == 'issuance_date', function ($query) use ( $from, $to) {
					$query->whereBetween('issuance_date', [$from, $to]);
				})
				;
				$models[$type] = $models[$type]->when($searchFieldName == 'transaction_name', function ($query) use ( $value) {
					$query->where('transaction_name', 'like', '%'.$value.'%');
				})
				->when($searchFieldName == 'lg_code', function ($query) use ( $value) {
					$query->where('lg_code', 'like', '%'.$value.'%');
				})
				->when($searchFieldName == 'purchase_order_date', function ($query) use ( $from, $to) {
					$query->whereBetween('purchase_order_date', [$from, $to]);
				});
                // $models[$type]   = $this->applyFilter($request, $models[$type], $filterDates[$type]['startDate'], $filterDates[$type]['endDate']) ;
            }
			$models[$type] = $models[$type]->paginate($paginationPerPage,['*'],'bankToLcSettlementInternalMoneyTransfersPage');
            $searchFields[$type] =  [
                'transaction_name'=>__('Transaction Name'),
                'lg_code'=>__('LG Code'),
                'purchase_order_date'=>__('Purchase Order Date'),
                'issuance_date'=>__('Issuance Date')
            ];

        }

        return view('reports.LetterOfGuaranteeIssuance.index', [
            'company'=>$company,
            'searchFields'=>$searchFields,
            'models'=>$models,
            'filterDates'=>$filterDates,
            'currentActiveTab'=>$activeLgType
        ]);
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
            'purchaseOrders'=>PurchaseOrder::onlyForCompany($company->id)->get(),
            'accountTypes'=> AccountType::onlyCurrentAccount()->get(),
            'cashCoverAccountTypes'=>AccountType::onlyCashCoverAccounts()->get(),
            'source'=>$source,
            'cdOrTdAccountTypes'=>$cdOrTdAccountTypes,
            'errorMessage'=>$errorMessage
        ];

    }
    public function create(Company $company, string $source)
    {
        $formName = $source.'-form';
        $commonVars = $this->commonViewVars($company, $source) ;
        if (!count($commonVars['financialInstitutionBanks']) && isset($commonVars['errorMessage'])) {
            return redirect()->back()->with('fail', $commonVars['errorMessage']);
        }
        return view('reports.LetterOfGuaranteeIssuance.'.$formName, $this->commonViewVars($company, $source));
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
		
        $partner = Partner::find($request->get('partner_id'));
        $customerName = $partner->getName() ;
        $lgCode = $request->get('lg_code');
        $isOpeningBalance = $request->get('category_name') == LetterOfGuaranteeIssuance::OPENING_BALANCE;
        $financialInstitutionId = $request->get('financial_institution_id') ;
        $letterOfGuaranteeFacilityId =  $request->get('lg_facility_id') ;
        
        $letterOfGuaranteeFacility = $source == LetterOfGuaranteeIssuance::LG_FACILITY  ? LetterOfGuaranteeFacility::find($letterOfGuaranteeFacilityId) : null;
        if ($source == LetterOfGuaranteeIssuance::LG_FACILITY && is_null($letterOfGuaranteeFacility)) {
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
        
		return response()->json(['redirectTo'=>route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])]);
		
        // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])->with('success', __('Data Store Successfully'));

    }

    public function edit(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        $formName = $source.'-form';
        $commonVars = array_merge(
            $this->commonViewVars($company, $source),
            [
                'model'=>$letterOfGuaranteeIssuance
            ]
        ) ;
        if (!count($commonVars['financialInstitutionBanks']) && isset($commonVars['errorMessage'])) {
            return redirect()->back()->with('fail', $commonVars['errorMessage']);
        }
        return view('reports.LetterOfGuaranteeIssuance.'.$formName, $commonVars);

    }

    public function update(Company $company, UpdateLetterOfGuaranteeIssuanceRequest $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
        if ($letterOfGuaranteeIssuance->renewalDateHistories->count()  > 1) {
			return response()->json(['redirectTo'=>route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])]);
            // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('success', __('Data Store Successfully'));
        }
        /**
         * * لو هو
         * * opening
         * * يبقي هنحذف اللي عملناه في اودو
         */
        
        $letterOfGuaranteeIssuance->deleteAllRelations();
        $letterOfGuaranteeIssuance->delete();
        return $this->store($company, $request, $source);
    
        // return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type')])->with('success', __('Data Store Successfully'));
    }

    /**
     * * هنرجعه تاني لل
     * * running
     * * اكنه كان عامله انه اتلغى بالغلط
     */
    public function backToRunningStatus(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
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
                $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
                $odooLetterOfGuaranteeIssuance->unlink($currentJournalEntryId);
            }
        }
		
		}
		 if ($company->hasOdooIntegrationCredentials() && !$isCdOrTd && $company->withinIntegrationDate($issuanceDate)) {
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
            
        }
		
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$request->get('lg_type', $letterOfGuaranteeIssuance->getLgType())])->with('success', __('Data Store Successfully'));
    }
    
    
    /**
     * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال
     * * letter of guarantee statements
     */
    public function cancel(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
    {
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
    public function applyAmountToBeDecreased(Company $company, Request $request, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $source)
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
    
    public function editAmountToBeDecreased(Company $company, Request $request, LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory, string $source)
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
            
        return response()->json([
            'status'=>true ,
            'reloadCurrentPage'=>true
        ]);
        // return redirect()->route('view.letter.of.guarantee.issuance',['company'=>$company->id,'active'=>$letterOfGuaranteeIssuance->getLgType()])->with('success',__('Data Store Successfully'));
    }
    
    /**
     * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال
     * * letter of guarantee statements
     */
    public function deleteAdvancedPayment(Company $company, Request $request, LetterOfGuaranteeIssuanceAdvancedPaymentHistory $lgAdvancedPaymentHistory)
    {
    
        
        $lgAdvancedPaymentHistory->deleteAllRelations();
        $lgAdvancedPaymentHistory->delete();
        return redirect()->route('view.letter.of.guarantee.issuance', ['company'=>$company->id,'active'=>$lgAdvancedPaymentHistory->letterOfGuaranteeIssuance->getLgType()])->with('success',__('Data Store Successfully'));
    
        
    }
    


    public function destroy(Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance)
    {
        
        $letterOfGuaranteeIssuance->deleteAllRelations();
        $lgType = $letterOfGuaranteeIssuance->getLgType();
        $letterOfGuaranteeIssuance->delete();
        return redirect()->route('view.letter.of.guarantee.issuance',['company'=>$company->id,'active'=>$lgType]);
    }
    
    public function getBeneficiaryNameByCurrency(Request $request , Company $company)
    {
        $currencyName = $request->get('currencyName');
        $beneficiaries = $company->letterOfGuaranteeIssuances->where('lg_currency',$currencyName)->load('beneficiary')->pluck('beneficiary.name','beneficiary.id')->toArray() ;
        return response()->json([
            'beneficiaries'=>$beneficiaries
        ]);
    }

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
