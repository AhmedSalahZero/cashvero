<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreLgRenewalDateRequest;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LgRenewalDateHistory;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LetterOfGuaranteeIssuanceRenewalDateController
{
    use GeneralFunctions;
	public function index(Company $company,Request $request,LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance)
	{
		$renewalDateHistories = $letterOfGuaranteeIssuance->renewalDateHistories;
        return view('reports.LetterOfGuaranteeIssuance.renewal-date.index', [
			'company'=>$company,
			'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance,
			'renewalDateHistories'=>$renewalDateHistories,
		]);
    }
	public function store(StoreLgRenewalDateRequest $request, Company $company, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance){

		$date = $request->get('renewal_date') ;
		$renewalFeesAmount = number_unformat($request->get('fees_amount',0));
		$expiryDate = $letterOfGuaranteeIssuance->getRenewalDate();
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$financialInstitution = $letterOfGuaranteeIssuance->financialInstitutionBank;
		$lgType = $letterOfGuaranteeIssuance->getLgType();
		$transactionName = $letterOfGuaranteeIssuance->getTransactionName();
		$financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->lg_fees_and_commission_account_id);
	
		if(!$letterOfGuaranteeIssuance->renewalDateHistories->count()){
			/**
			 * * في حالة اول مرة هنضيف تاريخ التجديد الاصلي اكنة تاريخ علشان نحتفظ بيه علشان ما يضيعش
			 */
			LgRenewalDateHistory::create([
				'company_id'=>$company->id ,
				'fees_amount'=>0,
				'renewal_date'=>$letterOfGuaranteeIssuance->getRenewalDate(),
				'letter_of_guarantee_issuance_id'=>$letterOfGuaranteeIssuance->id,
			]);
		}
		$lgRenewalDateHistory = LgRenewalDateHistory::create([
			'company_id'=>$company->id ,
			'fees_amount'=>$renewalFeesAmount,
			'renewal_date'=>$renewalDate,
			'letter_of_guarantee_issuance_id'=>$letterOfGuaranteeIssuance->id
		]);
		
		$lgRenewalDateHistory->handleRenewalFeesForOdoo($renewalFeesAmount,$expiryDate);
		
		$this->storeCommissionToCreditCurrentAccountBankStatement($lgRenewalDateHistory,$letterOfGuaranteeIssuance,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		$financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
			$letterOfGuaranteeIssuance->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
		}
		
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$renewalDate
		]);
		
		
		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
	}
	protected function storeCommissionToCreditCurrentAccountBankStatement(LgRenewalDateHistory $lgRenewalDateHistory , LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance,Company $company,string $expiryDate , string $renewalDate,string $transactionName, string $lgType )
	{
		$lgRenewalDateHistoryId = $lgRenewalDateHistory->id;
		$lgCommissionInterval = $letterOfGuaranteeIssuance->getLgCommissionInterval();
		$lgDurationMonths = Carbon::make($expiryDate)->diffInMonths(Carbon::make($renewalDate));
	
		$numberOfIterationsForQuarter = ceil($lgDurationMonths / 3); 
		$issuanceDate = $expiryDate;
		$minLgCommissionAmount = $letterOfGuaranteeIssuance->getMinLgCommissionFees();
		$lgCommissionAmount = $letterOfGuaranteeIssuance->getLgCommissionAmount();
		$maxLgCommissionAmount = max($minLgCommissionAmount ,$lgCommissionAmount );
		$financialInstitutionId = $letterOfGuaranteeIssuance->getFinancialInstitutionBankId();
		$financialInstitutionAccountForFeesAndCommission = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getFeesAndCommissionAccountId());
		$financialInstitutionAccountIdForFeesAndCommission = $financialInstitutionAccountForFeesAndCommission->id;
		$openingBalanceDateOfCurrentAccount = $financialInstitutionAccountForFeesAndCommission->getOpeningBalanceDate();
		$isOpeningBalance = $letterOfGuaranteeIssuance->isOpeningBalance();
		$letterOfGuaranteeIssuance->storeCommissionAmountCreditBankStatement( $lgCommissionInterval ,  $numberOfIterationsForQuarter ,  $issuanceDate, $openingBalanceDateOfCurrentAccount,$maxLgCommissionAmount, $financialInstitutionAccountIdForFeesAndCommission, $transactionName, $lgType, $isOpeningBalance,$lgRenewalDateHistoryId);
		
	}
	public function edit(Request $request , Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance , LgRenewalDateHistory $LgRenewalDateHistory){
		$renewalDateHistories = $letterOfGuaranteeIssuance->renewalDateHistories;
        return view('reports.LetterOfGuaranteeIssuance.renewal-date.index', [
			'company'=>$company,
			'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance,
			'renewalDateHistories'=>$renewalDateHistories,
			'model'=>$LgRenewalDateHistory
		]);
	}
	public function update(StoreLgRenewalDateRequest $request , Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance  , LgRenewalDateHistory $LgRenewalDateHistory){
		$date = $request->get('renewal_date') ;
		$renewalFeesAmount = number_unformat($request->get('fees_amount',0));
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$expiryDate = $request->get('expiry_date');

		$renewalFeesCurrentAccountBankStatement = $letterOfGuaranteeIssuance->renewalFeesCurrentAccountBankStatement($expiryDate) ;
		$financialInstitution = $letterOfGuaranteeIssuance->financialInstitutionBank;
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($LgRenewalDateHistory->commissionCurrentBankStatements()->withoutGlobalScope('only_active')->get());
		$transactionName = $letterOfGuaranteeIssuance->getTransactionName();
		$lgType = $letterOfGuaranteeIssuance->getLgType();
		$financialInstitutionAccount = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->lg_fees_and_commission_account_id);
		$financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		$this->storeCommissionToCreditCurrentAccountBankStatement($LgRenewalDateHistory,$letterOfGuaranteeIssuance,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		if($renewalFeesCurrentAccountBankStatement){
			$renewalFeesCurrentAccountBankStatement->handleFullDateAfterDateEdit($expiryDate,0,$renewalFeesAmount);
		}
		else{
			if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
				$letterOfGuaranteeIssuance->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
			}
		}
		$LgRenewalDateHistory->update([
			'renewal_date'=>$renewalDate ,
			'fees_amount'=>$renewalFeesAmount
		]);
		$LgRenewalDateHistory->unlinkRenewalFeesForOddo();
		$LgRenewalDateHistory->handleRenewalFeesForOdoo($renewalFeesAmount,$expiryDate);
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$renewalDate
		]);
		

		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
		
	}
	public function destroy( Company $company ,  LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance , LgRenewalDateHistory $LgRenewalDateHistory)
	{
		
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($LgRenewalDateHistory->commissionCurrentBankStatements()->withoutGlobalScope('only_active')->get());
		$oldRenewalDate = $letterOfGuaranteeIssuance->getRenewalDate();
		$expiryDate = $letterOfGuaranteeIssuance->getRenewalDateBefore($oldRenewalDate);
		$renewalFeesCurrentAccountBankStatement = $letterOfGuaranteeIssuance->renewalFeesCurrentAccountBankStatement($expiryDate) ;
		if($renewalFeesCurrentAccountBankStatement){
			$renewalFeesCurrentAccountBankStatement->delete();
		}
		$LgRenewalDateHistory->unlinkRenewalFeesForOddo();
		$LgRenewalDateHistory->delete();
		$letterOfGuaranteeIssuance = $letterOfGuaranteeIssuance->refresh();
		$lastHistory = $letterOfGuaranteeIssuance->renewalDateHistories->last();
		$letterOfGuaranteeIssuance->update([
			'renewal_date'=>$lastHistory->renewal_date 
			]) ; 
			/**
			 * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
			 * * دي هنحذفه معتش ليه لزمة
			 */
			if($letterOfGuaranteeIssuance->renewalDateHistories->count() == 1){
				$lastHistory->delete();
			}
		return redirect()->route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$letterOfGuaranteeIssuance->id]);
	}
	
}
