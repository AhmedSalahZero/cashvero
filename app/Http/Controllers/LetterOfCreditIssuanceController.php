<?php
namespace App\Http\Controllers;

use App\Enums\LcTypes;
use App\Http\Requests\StoreLetterOfCreditIssuanceRequest;
use App\Http\Requests\StoreNewSettlementWithLcIssuanceRequest;
use App\Http\Requests\UpdateLetterOfCreditIssuanceRequest;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LcIssuanceExpense;
use App\Models\LcOverdraftBankStatement;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditCashCoverStatement;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfCreditStatement;
use App\Models\Partner;
use App\Models\PaymentSettlement;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\TimeOfDeposit;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
/**
 * ! No Odoo Service Yet
 */
class LetterOfCreditIssuanceController
{
    use GeneralFunctions ;
    protected function applyFilter(Request $request,Collection $collection,?string $filterStartDate = null, ?string $filterEndDate = null ):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'issuance_date' ; // change it
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');
		$collection = $collection
		->when($request->has('value'),function($collection) use ($value,$searchFieldName){
			return $collection->filter(function($letterOfCreditIssuance) use ($value,$searchFieldName){
				$currentValue = $letterOfCreditIssuance->{$searchFieldName} ;
				return false !== stristr($currentValue , $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->when($filterStartDate , function($collection) use ($filterStartDate,$filterEndDate){
			return $collection->filterByIssuanceDate($filterStartDate,$filterEndDate);
		})
		->sortByDesc('id')->values();

		return $collection;
	}
	public function index(Company $company,Request $request)
	{
		$company->load('letterOfCreditIssuances.financialInstitutionBank','letterOfCreditIssuances.beneficiary');
		
		$clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveContracts()->get();

		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$activeLcType = $request->get('active',LcTypes::SIGHT_LC) ;
		$filterDates = [];
		$searchFields = [];
		$models = [];
		foreach(getLcTypes() as $type=>$typeNameFormatted){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
			$models[$type]   = $company->letterOfCreditIssuances->where('lc_type',$type) ;

			if($type == $activeLcType ){
				$models[$type]   = $this->applyFilter($request,$models[$type],$filterDates[$type]['startDate'] , $filterDates[$type]['endDate']) ;
			}
			$searchFields[$type] =  [
				'transaction_name'=>__('Transaction Name'),
				'lc_code'=>__('LC Code'),
				'purchase_order_date'=>__('Purchase Order Date'),
				'issuance_date'=>__('Issuance Date')
			];

		}


        return view('reports.LetterOfCreditIssuance.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'currentActiveTab'=>$activeLcType,
			'clientsWithContracts'=>$clientsWithContracts,
			'currentAccounts'=> AccountType::onlyCurrentAccount()->get(),
		]);
    }
	public function commonViewVars(Company $company,string $source,?LetterOfCreditIssuance $letterOfCreditIssuance = null):array
	{
		
		$cdOrTdAccountTypes = [];
		$tdOrCdCurrencyName = null ;
		if($source == LetterOfCreditIssuance::AGAINST_CD){
			$cdOrTdAccountTypes = AccountType::onlyCdAccounts()->get();
			if($letterOfCreditIssuance){
				$currentCertificateOfDeposit = CertificatesOfDeposit::find($letterOfCreditIssuance->cd_or_td_id);
				$tdOrCdCurrencyName = $currentCertificateOfDeposit->getCurrency();
			}
		}
		elseif($source == LetterOfCreditIssuance::AGAINST_TD){
			$cdOrTdAccountTypes = AccountType::onlyTdAccounts()->get();
			if($letterOfCreditIssuance){
				$currentTimeOfDeposit = TimeOfDeposit::find($letterOfCreditIssuance->cd_or_td_id);
				$tdOrCdCurrencyName = $currentTimeOfDeposit->getCurrency();
			}
		}
		return [
			'financialInstitutionBanks'=> FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->get(),
			'beneficiaries'=>Partner::onlySuppliers()->onlyForCompany($company->id)->get(),
			'contracts'=>Contract::onlyForCompany($company->id)->get(),
			'purchaseOrders'=>PurchaseOrder::onlyForCompany($company->id)->get(),
			'cashCoverAccountTypes'=>AccountType::onlyCashCoverAccounts()->get(),
			'accountTypes'=> AccountType::onlyCurrentAccount()->get(),
			'source'=>$source,
			'cdOrTdAccountTypes'=>$cdOrTdAccountTypes,
			'tdOrCdCurrencyName'=>$tdOrCdCurrencyName,
		];

	}
	public function create(Company $company,string $source)

	{
		$formName = $source.'-form';
        return view('reports.LetterOfCreditIssuance.'.$formName,array_merge(
			$this->commonViewVars($company,$source) ,
			[

			]
		));
    }

	public function store(Company $company  , StoreLetterOfCreditIssuanceRequest $request , string $source){
		$financialInstitutionId = $request->get('financial_institution_id') ;
		$letterOfCreditFacilityId =  $request->get('lc_facility_id') ; 
		$letterOfCreditFacility = $source == LetterOfCreditIssuance::LC_FACILITY  ? LetterOfCreditFacility::find($letterOfCreditFacilityId) : null;
		$letterOfCreditFacilityId =  0 ; 
		$contractId = $request->get('contract_id');
		$purchaseOrderId = $request->get('purchase_order_id');
		$lcCashCoverCurrency = $request->get('lc_cash_cover_currency');
		$contractType  = null ;
		$newPurchaseOrderNumber = $request->get('new_purchase_order_number') ;
	
		if($contractId == -1){
			$contractId = null ;
			$contractType = 'no-po';
			$existingPo = PurchaseOrder::where([
				'po_number'=>$newPurchaseOrderNumber,
				'company_id'=>$company->id,
			])->first(); 
		
			if($newPurchaseOrderNumber && !$existingPo){
				$po  = PurchaseOrder::create([
					'contract_id'=>null ,
					'po_number'=>$newPurchaseOrderNumber,
					'company_id'=>$company->id,
					'created_by'=>auth()->user()->id
				]);
				$purchaseOrderId = $po->id ;
			}
			
		}
		elseif($contractId == -2){
			$contractType = 'existing-po';
			$contractId = null ;
		}

		$request->merge([
			'contract_type'=>$contractType ,
			'contract_id'=>$contractId,
			'purchase_order_id'=>$purchaseOrderId
		]);
		
		if($source == LetterOfCreditIssuance::LC_FACILITY && is_null($letterOfCreditFacility)){
			return redirect()->back()->with('fail',__('No Available Letter Of Credit Facility Found !'));
		}
		if($letterOfCreditFacility instanceof LetterOfCreditFacility){
			$letterOfCreditFacilityId = $letterOfCreditFacility->id ;
		}
		$model = new LetterOfCreditIssuance();
		$lcCommissionAmount = $request->get('lc_commission_amount',0);
		$minLcCommissionAmount = $request->get('min_lc_commission_fees',0);
		$model->storeBasicForm($request);
		$transactionName = $request->get('transaction_name');
		$lcType = $request->get('lc_type');
		$issuanceDate = $request->get('issuance_date');
		$lcAmount = $request->get('lc_amount',0);
		$currency = $request->get('lc_currency',0);
		$cdOrTdId = $request->get('cd_or_td_id');
		
		
		
		$cdOrTdAccountTypeId = $request->get('cd_or_td_account_type_id');
		$accountType = AccountType::find($cdOrTdAccountTypeId);
		$cdOrTdAccount = null ;
		if($accountType && $accountType->isCertificateOfDeposit()){
			$cdOrTdAccount = CertificatesOfDeposit::find($cdOrTdId ) ;
			$cdOrTdId = $cdOrTdAccount->id;
		}
		elseif($accountType && $accountType->isTimeOfDeposit()){
			$cdOrTdAccount = TimeOfDeposit::find($cdOrTdId ) ;
			$cdOrTdId = $cdOrTdAccount->id;
		}
		$lcCashCoverOrCdOrTdCurrency = $model->getLcCashCoverCurrency() ?: $cdOrTdAccount->getCurrency();
		$isOpeningBalance = $request->get('category_name') == LetterOfCreditIssuance::OPENING_BALANCE;
		$cashCoverAmount = $request->get('cash_cover_amount',0);
		$issuanceFees = $request->get('issuance_fees',0);
		$lcAmountInMainCurrency = $model->getLcAmountInMainCurrency();
		$maxLcCommissionAmount = max($minLcCommissionAmount ,$lcCommissionAmount );
		$lcFeesAndCommissionAccountId = $request->get('lc_fees_and_commission_account_id') ;
	
		$financialInstitutionAccountForFeesAndCommission = FinancialInstitutionAccount::find($lcFeesAndCommissionAccountId);
		$financialInstitutionAccountForCashCover = FinancialInstitutionAccount::find($request->get('cash_cover_deducted_from_account_id',$lcFeesAndCommissionAccountId));
	
		$financialInstitutionAccountIdForFeesAndCommission = $financialInstitutionAccountForFeesAndCommission->id;
		$openingBalanceDateOfCurrentAccount = $financialInstitutionAccountForFeesAndCommission->getOpeningBalanceDate();
		
		$financialInstitutionAccountIdForCashCover = $financialInstitutionAccountForCashCover->id ?? 0;
		
		$isCdOrTdCashCoverAccount = in_array($request->get('cash_cover_deducted_from_account_id',[]),[28,29]);
		$customerName = $model->getBeneficiaryName();
		if(!$isOpeningBalance && !$isCdOrTdCashCoverAccount ){
			$model->storeCurrentAccountCreditBankStatement($issuanceDate,$cashCoverAmount , $financialInstitutionAccountIdForCashCover,0,1,__('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lcType,[],'en'),'customerName'=>$customerName,'transactionName'=>$transactionName],'en') , __('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lcType,[],'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName],'ar') );
		}
		if(!$isOpeningBalance){
			$model->storeCurrentAccountCreditBankStatement($issuanceDate,$issuanceFees , $financialInstitutionAccountIdForFeesAndCommission,0,1,__('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lcType,[],'en'),'customerName'=>$customerName,'transactionName'=>$transactionName],'en') , __('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lcType,[],'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName],'ar'),false,false,null,true);
		}
		$commentEn = __('LC Issuance [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'en');
		$commentAr = __('LC Issuance [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
		$model->handleLetterOfCreditStatement($financialInstitutionId,$source,$letterOfCreditFacilityId , $lcType,$company->id , $issuanceDate ,0 ,0,$lcAmountInMainCurrency,$lcCashCoverOrCdOrTdCurrency,0,$cdOrTdId,'credit-lc-amount',$commentEn,$commentAr);
		$commentEn = __('LC Issuance Cash Cover [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'en');
		$commentAr = __('LC Issuance Cash Cover [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
		$model->handleLetterOfCreditStatement($financialInstitutionId,$source,$letterOfCreditFacilityId , $lcType,$company->id , $issuanceDate ,0 ,$cashCoverAmount,0,$lcCashCoverOrCdOrTdCurrency,0,$cdOrTdId,'credit-lc-amount',$commentEn,$commentAr);
		$model->handleLetterOfCreditCashCoverStatement($financialInstitutionId,$source,$letterOfCreditFacilityId , $lcType,$company->id , $issuanceDate ,0 ,$cashCoverAmount,0,$lcCashCoverCurrency,0,'credit-lc-amount');
		
		// $lcDurationDays = $request->get('lc_duration_days',1);
	//	$numberOfIterationsForQuarter = ceil($lcDurationDays / 3); 
		$numberOfIterationsForQuarter = 1 ;
		$lcCommissionInterval = 'monthly';
		// $lcCommissionInterval = $request->get('lc_commission_interval','monthly');
		$model->storeCommissionAmountCreditBankStatement( $lcCommissionInterval ,  $numberOfIterationsForQuarter ,  $issuanceDate, $openingBalanceDateOfCurrentAccount,$maxLcCommissionAmount, $financialInstitutionAccountIdForFeesAndCommission, $transactionName, $lcType, $isOpeningBalance);
		
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id,'active'=>$request->get('lc_type')])->with('success',__('Data Store Successfully'));

	}

	public function edit(Company $company , Request $request , LetterOfCreditIssuance $letterOfCreditIssuance,string $source){
		$formName = $source.'-form';

        return view('reports.LetterOfCreditIssuance.'.$formName,array_merge(
			$this->commonViewVars($company,$source,$letterOfCreditIssuance) ,
			[
				'model'=>$letterOfCreditIssuance
			]
		));

	}

	public function update(Company $company , UpdateLetterOfCreditIssuanceRequest $request , LetterOfCreditIssuance $letterOfCreditIssuance,string $source){
		if($letterOfCreditIssuance->getContractType() == 'no-po' && $request->get('contract-id') != -1){
			$letterOfCreditIssuance->purchaseOrder ? $letterOfCreditIssuance->purchaseOrder->delete() : null;
		}
		if($letterOfCreditIssuance->getContractType() == 'no-po' && $request->get('contract-id') == -1){
			if($letterOfCreditIssuance->purchaseOrder){
				$letterOfCreditIssuance->purchaseOrder->update([
					'po_number'=>$request->get('new_purchase_order_number')
				]);
			}
			
		}
		
		$letterOfCreditIssuance->deleteAllRelations();
		$letterOfCreditIssuance->delete();
		$this->store($company,$request,$source);
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id,'active'=>$request->get('lc_type')])->with('success',__('Data Store Successfully'));
	}

	


		/**
		 * * هنرجعه تاني لل
		 * * running
		 * * اكنه كان عامله انه اتلغى بالغلط
	 */
	public function backToRunningStatus(Company $company,Request $request,LetterOfCreditIssuance $letterOfCreditIssuance,string $source)
	{
		$letterOfCreditIssuanceStatus = LetterOfCreditIssuance::RUNNING ;
		/**
		 * * هنشيل قيم ال
		 * * letter of credit statement
		 */

		 $letterOfCreditIssuance->update([
			'status' => $letterOfCreditIssuanceStatus,
			'payment_date'=>null,
			'supplier_invoice_id'=>null,
			'payment_currency'=>null,
			'payment_account_type_id'=>null,
			'payment_account_number_id'=>null,
		]);
	
		PaymentSettlement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->settlements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountPaymentCreditBankStatements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountLcInterestCreditBankStatements);
		LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditStatements->where('type',LetterOfCreditIssuance::FOR_PAID));
		LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditCashCoverStatements->where('type',LetterOfCreditIssuance::FOR_PAID));
		LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditCashCoverStatements->where('type','credit-lc-amount'));
		LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->lcOverdraftBankStatements->where('source',$source));
		
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id,'active'=>$request->get('lc_type')])->with('success',__('Data Store Successfully'));
	}
	
	
		/**
	* * هنا هو بيحدد ان الخطاب الاعتماد دا انتهى وبالتالي هنبعت للبائع مثلا او للموريد اللي في امريكا مثلا الفلوس علي حسابة
	 * * letter of credit statements
	 */
	public function markAsPaid(Company $company,StoreNewSettlementWithLcIssuanceRequest $request,LetterOfCreditIssuance $letterOfCreditIssuance,string $source)
	{

		/**
		 * @var LetterOfCreditIssuance $letterOfCreditIssuance
		 */
		$supplierInvoiceId = $request->get('supplier_invoice_id');
		$supplierInvoice = SupplierInvoice::find($supplierInvoiceId);
		$letterOfCreditIssuanceStatus = LetterOfCreditIssuance::PAID ;
		$lcType = $request->get('lc_type') ;
		$financedByBank = $letterOfCreditIssuance->isFinancedByBank();
		$financedBySelf = $letterOfCreditIssuance->isFinancedBySelf();
		$request->merge([
			'payment_currency'=>$financedBySelf ? $request->get('payment_currency'):null,
			'payment_account_type_id'=>$financedBySelf ? $request->get('payment_account_type_id'):null,
			'payment_account_number_id'=>$financedBySelf ? $request->get('payment_account_number_id'):null,
			'lc_remaining_amount' =>number_unformat($request->get('lc_remaining_amount',0)),
		]);
		$paymentCurrency = $request->get('payment_currency');
		$paymentAccountTypeId = $request->get('payment_account_type_id');
		$paymentAccountNumberId = $request->get('payment_account_number_id');
		$lcRemainingAmount = $request->get('lc_remaining_amount');
		$interestAmount = number_unformat($request->get('interest_amount',0));
		$interestCurrency = $request->get('interest_currency');
		/**
		 * * هنشيل قيم ال
		 * * letter of credit statement
		 */
		$financialInstitutionId = $letterOfCreditIssuance->financial_institution_id ;
		$financialDuration = $letterOfCreditIssuance->getFinancialDuration();
		$supplierName = $letterOfCreditIssuance->getSupplierName();
		$transactionName = $letterOfCreditIssuance->getTransactionName();
		$lcFacilityLimit = $letterOfCreditIssuance->letterOfCreditFacility ? $letterOfCreditIssuance->letterOfCreditFacility->getLimit():0 ;
		$paymentDate = Carbon::make($request->get('payment_date',now()->format('Y-m-d')) )->format('Y-m-d');
		 $letterOfCreditIssuance->update([
			'status' => $letterOfCreditIssuanceStatus,
			'payment_date'=>$paymentDate,
			'supplier_invoice_id'=>$supplierInvoiceId,
			'payment_currency'=>$paymentCurrency,
			'payment_account_type_id'=>$paymentAccountTypeId,
			'payment_account_number_id'=>$paymentAccountNumberId,
			'interest_amount'=>$interestAmount,
			'interest_currency'=>$interestCurrency
		]);
		
		$letterOfCreditFacility = $letterOfCreditIssuance->letterOfCreditFacility;
		$lcType = $letterOfCreditIssuance->getLcType();
		$lcAmount = $letterOfCreditIssuance->getLcAmount();
		$lcAmountInMainCurrency = $letterOfCreditIssuance->getLcAmountInMainCurrency();
	
		$cashCoverAmount = $letterOfCreditIssuance->getCashCoverAmount();
		$diffBetweenLcAmountAndCashCover = ($lcAmountInMainCurrency - $cashCoverAmount);
	
		LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditStatements->where('type',LetterOfCreditIssuance::FOR_PAID));
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountPaymentCreditBankStatements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountLcInterestCreditBankStatements);
		LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditCashCoverStatements->where('type',LetterOfCreditIssuance::FOR_PAID));
		LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->lcOverdraftBankStatements->where('source',$source)->where('is_credit',1));
		
		
		
		
		$letterOfCreditFacilityId = $letterOfCreditFacility ? $letterOfCreditFacility->id : 0 ;
		$letterOfCreditCurrency = $source == LetterOfCreditIssuance::AGAINST_TD || $source == LetterOfCreditIssuance::AGAINST_CD ? $letterOfCreditIssuance->getTdOrCdCurrency($source,$company->id) : $letterOfCreditIssuance->getLcCashCoverCurrency() ;
		$commentEn = __('LC Payment [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'en');
		$commentAr = __('LC Payment [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
		$letterOfCreditIssuance->handleLetterOfCreditStatement($financialInstitutionId,$source,$letterOfCreditFacilityId,$lcType,$company->id,$paymentDate,0,$lcRemainingAmount , 0,$letterOfCreditCurrency,0,$letterOfCreditIssuance->getCdOrTdId(),LetterOfCreditIssuance::FOR_PAID,$commentEn,$commentAr);
		$commentEn = __('LC Cash Cover Payment [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'en');
		$commentAr = __('LC Cash Cover Payment [:lcType] [:transactionName]',['lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
		$letterOfCreditIssuance->handleLetterOfCreditCashCoverStatement($financialInstitutionId,$source,$letterOfCreditFacilityId,$lcType,$company->id,$paymentDate,0,0 , $cashCoverAmount ,$letterOfCreditIssuance->getLcCashCoverCurrency(),0,LetterOfCreditIssuance::FOR_PAID
		// ,$commentEn,$commentAr
	);
		if($interestAmount > 0 ){
			$letterOfCreditIssuance->storeCurrentAccountLcInterestPaymentCreditBankStatement($paymentDate,$interestAmount , $paymentAccountNumberId,0,1,__('LC Interest Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]'  ,['lcType'=>__($lcType,[],'en'),'supplierName'=>$supplierName,'transactionName'=>$transactionName],'en') , __('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]'  ,['lcType'=>__($lcType,[],'ar'),'supplierName'=>$supplierName,'transactionName'=>$transactionName],'ar') );
		}
		if($source != LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER){
			$commentEn = __('Post Finance [ :noDays ] Days [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]',['noDays'=>$financialDuration,'supplierName'=>$supplierName,'lcType'=>$lcType,'transactionName'=>$transactionName],'en');
			$commentAr = __('Post Finance [ :noDays ] Days [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]',['noDays'=>$financialDuration,'supplierName'=>$supplierName,'lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
			if($financedByBank){
				$letterOfCreditIssuance->handleLcCreditBankStatement($letterOfCreditFacilityId,'credit',$lcFacilityLimit,$paymentDate,$diffBetweenLcAmountAndCashCover,$source,$commentEn , $commentAr);
			}
			else{
				$letterOfCreditIssuance->storeCurrentAccountPaymentCreditBankStatement($paymentDate,$lcRemainingAmount , $paymentAccountNumberId,0,1,__('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]'  ,['lcType'=>__($lcType,[],'en'),'supplierName'=>$supplierName,'transactionName'=>$transactionName],'en') , __('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]'  ,['lcType'=>__($lcType,[],'ar'),'supplierName'=>$supplierName,'transactionName'=>$transactionName],'ar') );
				// هينزل الحركة الكردت في 
				// bank statement
			}
		}
		// lc_overdraft 
		// credit 
		// وهنزود الحساب دا في ال
		// internal money transfer 
		
		// Money Payment 
		// $supplierId = $request->get('supplier_id');
		if($supplierInvoice){
			$letterOfCreditIssuance->storeNewSettlementAfterDeleteOldOne($supplierInvoice,$company);
			$letterOfCreditIssuance->storeNewAllocationAfterDeleteOldOne($request->get('allocations',[]));
		}
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id,'active'=>$lcType])->with('success',__('Data Store Successfully'));
	}
	
	public function destroy(Company $company ,  LetterOfCreditIssuance $letterOfCreditIssuance)
	{
		
		
		$letterOfCreditIssuance->deleteAllRelations();
		
		$lcType = $letterOfCreditIssuance->getLcType();
		$letterOfCreditIssuance->delete();
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id,'active'=>$lcType]);
	}
	public function getLcIssuanceExpenseData(Request $request,Company $company,$type):array
	{
		/**
		 * *  $type create or update
		 */
		return 
		[
			'expense_name'=>$request->input('expense_name.'.$type),
			'date'=>Carbon::make($request->input('date.'.$type))->format('Y-m-d'),
			'amount'=>$request->input('amount.'.$type),
			'exchange_rate'=>$request->input('exchange_rate.'.$type),
			'currency'=>$request->input('currency.'.$type),
			'amount_in_main_currency'=>$request->input('amount_in_main_currency.'.$type),
			'company_id'=>$company->id 
		];
	}
	public function applyExpense(Company $company,Request $request,LetterOfCreditIssuance $letterOfCreditIssuance , $type='create')
	{
	
		$date = Carbon::make($request->input('date.'.$type))->format('Y-m-d') ;
		$amount = $request->input('amount.'.$type,0);
	
		$accountId = $letterOfCreditIssuance->getCashCoverDeductedFromAccountId();
		// $financialInstitutionId = $letterOfCreditIssuance->getFinancialInstitutionId() ;
		$financialInstitutionAccount = FinancialInstitutionAccount::find($accountId);
		$financialInstitutionAccountId = $financialInstitutionAccount->id ; 
		$expenseData = $this->getLcIssuanceExpenseData($request,$company,$type) ;
		$expenseName = $expenseData['expense_name'] ?? null ;
		$amount = $expenseData['amount'] ?? 0 ;
		if(is_null($expenseName) ){
			return redirect()->back()->with(['fail'=>__('Please Enter Expense Name')]);
		}if( $amount == 0 ){
			return redirect()->back()->with(['fail'=>__('Please Enter Expense Amount')]);
		}
		/**
		 * @var LcIssuanceExpense $lcIssuanceExpense
		 */
		$lcIssuanceExpense = $letterOfCreditIssuance->expenses()->create($expenseData);
		$supplierName = $letterOfCreditIssuance->getSupplierName();
		$expenseName = $lcIssuanceExpense->getName();
		$lcType = $letterOfCreditIssuance->getLcType();
		$transactionName = $letterOfCreditIssuance->getTransactionName();
		
		$expenseCommentEn = __('Expense [ :expenseName ] [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]',['expenseName'=>$expenseName ,'supplierName'=>$supplierName , 'lcType'=>$lcType,'transactionName'=>$transactionName],'en');
		$expenseCommentAr = __('Expense [ :expenseName ] [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]',['expenseName'=>$expenseName ,'supplierName'=>$supplierName , 'lcType'=>$lcType,'transactionName'=>$transactionName],'ar');
		$lcIssuanceExpense->storeCurrentAccountCreditBankStatement($date,$amount , $financialInstitutionAccountId,0,1,$expenseCommentEn,$expenseCommentAr);
		return redirect()->route('view.letter.of.credit.issuance',['company'=>$company->id])->with('success',__('Expense Credit Successfully'));
		// return redirect()->back()->with('success',__('Expense Credit Successfully'));
	}
	public function updateExpense(Company $company,Request $request,LcIssuanceExpense $expense)
	{
		$expense->delete();
		$letterOfCreditIssuance = $expense->letterOfCreditIssuance ;
		$this->applyExpense($company,$request,$letterOfCreditIssuance,'update');
		return response()->json([
			'reloadCurrentPage'=>true
		]);
	}
	public function deleteExpense(Company $company,Request $request,LcIssuanceExpense $expense)
	{

		
		// protected static function boot() 
		// $expense->deleteAllRelations();
		$expense->delete();
		return redirect()->back()->with('success',__('Expense Deleted Successfully'));
	}
	public function getRemainingBalance(Company $company , Request $request){
		$letterOfCreditIssuance = LetterOfCreditIssuance::find($request->get('letterOfCreditIssuanceId'));
		$lcSettlementInternalTransfer = LcSettlementInternalMoneyTransfer::find($request->get('internalMoneyTransferId'));
		$currentLcAmountInEditMode = $lcSettlementInternalTransfer->getAmount();
		$remainingBalance = $letterOfCreditIssuance ? $letterOfCreditIssuance->getRemainingBalance($currentLcAmountInEditMode) : 0;
		return response()->json([
			'status'=>true ,
			'remaining_balance'=> $remainingBalance
		]);
	}

}
