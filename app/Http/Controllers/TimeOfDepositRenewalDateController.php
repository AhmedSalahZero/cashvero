<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreTdRenewalDateRequest;
use App\Models\Company;
use App\Models\TdRenewalDateHistory;
use App\Models\TimeOfDeposit;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

class TimeOfDepositRenewalDateController
{
    use GeneralFunctions;
	public function index(Company $company,Request $request,TimeOfDeposit $timeOfDeposit)
	{
		$renewalDateHistories = $timeOfDeposit->renewalDateHistories;
        return view('reports.time-of-deposit.renewal-date.index', [
			'company'=>$company,
			'timeOfDeposit'=>$timeOfDeposit,
			'renewalDateHistories'=>$renewalDateHistories,
		]);
    }
	public function store(StoreTdRenewalDateRequest $request, Company $company, TimeOfDeposit $timeOfDeposit){
	
		$date = $request->get('renewal_date') ;
		$newInterestRate = $request->get('interest_rate');
		// $expiryDate = $timeOfDeposit->getRenewalDate();
		
		$expiryDate = $request->get('expiry_date');
	
		
		
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		// $financialInstitution = $timeOfDeposit->financialInstitution;
		// $lgType = $timeOfDeposit->getLgType();
		// $transactionName = $timeOfDeposit->getTransactionName();
		// $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber,$company->id , $financialInstitution->id);
		
		if(!$timeOfDeposit->renewalDateHistories->count()){
			/**
			 * * في حالة اول مرة هنضيف تاريخ التجديد الاصلي اكنة تاريخ علشان نحتفظ بيه علشان ما يضيعش
			 */
			TdRenewalDateHistory::create([
				'company_id'=>$company->id ,
				// 'fees_amount'=>0,
				'renewal_date'=>$expiryDate,
				'interest_rate'=>$timeOfDeposit->getInterestRate(),
				'expiry_date'=>$timeOfDeposit->getStartDate(),
				'time_of_deposit_id'=>$timeOfDeposit->id,
			]);
		}
		$tdRenewalDateHistory = TdRenewalDateHistory::create([
			'company_id'=>$company->id ,
			// 'fees_amount'=>$renewalFeesAmount,
			'renewal_date'=>$renewalDate,
			'interest_rate'=>$newInterestRate,
			'expiry_date'=>$expiryDate,
			'time_of_deposit_id'=>$timeOfDeposit->id
		]);
		
		// $this->storeCommissionToCreditCurrentAccountBankStatement($tdRenewalDateHistory,$timeOfDeposit,$company,$expiryDate,$renewalDate,$transactionName,$lgType);
		// $financialInstitutionAccountOpeningBalance = $financialInstitutionAccount->getOpeningBalanceDate();
		// if(Carbon::make($expiryDate)->greaterThanOrEqualTo(Carbon::make($financialInstitutionAccountOpeningBalance))){
		// 	$timeOfDeposit->storeCurrentAccountCreditBankStatement($expiryDate,$renewalFeesAmount , $financialInstitutionAccount->id,0,1,__('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'transactionName'=>$transactionName],'en') , __('Renewal Fees [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'transactionName'=>$transactionName],'ar'),true);
		// }
		$commentEn = __('Renewal For Time Deposit',[],'en');
		$commentAr = __('Renewal For Time Deposit',[],'ar');
		$interestAmount = $timeOfDeposit->storeRenewalDebitCurrentAccount($expiryDate,$renewalDate,$newInterestRate,$commentEn,$commentAr);
		$timeOfDeposit->storeRenewal($expiryDate,$newInterestRate);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate,
			'start_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
		]);
		
		
		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
	}
	
	public function edit(Request $request , Company $company ,  TimeOfDeposit $timeOfDeposit , TdRenewalDateHistory $TdRenewalDateHistory){
		$renewalDateHistories = $timeOfDeposit->renewalDateHistories;
        return view('reports.time-of-deposit.renewal-date.index', [
			'company'=>$company,
			'timeOfDeposit'=>$timeOfDeposit,
			'renewalDateHistories'=>$renewalDateHistories,
			'model'=>$TdRenewalDateHistory
		]);
	}
	public function update(StoreTdRenewalDateRequest $request , Company $company ,  TimeOfDeposit $timeOfDeposit  , TdRenewalDateHistory $TdRenewalDateHistory){
		$date = $request->get('renewal_date') ;
		$newInterestRate  = $request->get('interest_rate');
		// $renewalFeesAmount = $request->get('fees_amount');
		$date = explode('/',$date);
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$renewalDate = $year.'-'.$month.'-'.$day ;
		$expiryDate = $request->get('expiry_date');
		$interestAmount = number_unformat($request->get('interest_amount'));

		$renewalFeesCurrentAccountBankStatement = $timeOfDeposit->renewalDebitCurrentAccount($expiryDate) ;
		// $interestAmount = $interestAmount ? $interestAmount 
		// : $timeOfDeposit->calculateInterestAmount($expiryDate,$renewalDate,$newInterestRate)
		// ;
		$renewalFeesCurrentAccountBankStatement->handleFullDateAfterDateEdit($expiryDate,$interestAmount,0);
	
		
		$TdRenewalDateHistory->update([
			'renewal_date'=>$renewalDate ,
			'expiry_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount
		]);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate,
			'start_date'=>$expiryDate,
			'interest_rate'=>$newInterestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
		]);
		

		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
		
	}
	public function destroy(Request $request , Company $company ,  TimeOfDeposit $timeOfDeposit , TdRenewalDateHistory $TdRenewalDateHistory)
	{
		
		$TdRenewalDateHistory->delete();
		$timeOfDeposit = $timeOfDeposit->refresh();
		$lastHistory = $timeOfDeposit->renewalDateHistories->last();
		$expiryDate = $lastHistory->expiry_date ;
		$renewalDate = $lastHistory->renewal_date ;
		$interestRate = $lastHistory->interest_rate ;
		$interestAmount = $lastHistory->interest_amount ;
		
		// $interestAmount = $timeOfDeposit->calculateInterestAmount($expiryDate,$renewalDate,$interestRate);
		$timeOfDeposit->update([
			'end_date'=>$renewalDate ,
			'start_date'=>$expiryDate,
			'interest_rate'=>$interestRate,
			'interest_amount'=>$interestAmount,
			'actual_interest_amount'=>$interestAmount
			]) ; 
			/**
			 * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
			 * * دي هنحذفه معتش ليه لزمة
			 */
			if($timeOfDeposit->renewalDateHistories->count() == 1){
				$lastHistory->delete();
			}
		return redirect()->route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$timeOfDeposit->id]);
	}
	
}
