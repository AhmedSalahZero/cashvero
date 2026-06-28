<?php
namespace App\Traits;

use App\Models\FinancialInstitution;
use App\Models\LendingInformation;
use Carbon\Carbon;





trait IsOverdraft
{
	/**
	 * * هو تاريخ بداية التعاقد مع البنك علي هذا التسهيل (القرض)
	 */
	public function getContractStartDate()
	{
		return $this->contract_start_date;
	}
	public function getContractStartDateFormatted()
	{
		$contractStartDate = $this->contract_start_date ;
		return $contractStartDate ? Carbon::make($contractStartDate)->format('d-m-Y'):null ;
	}
	/**
	 * * هو تاريخ نهاية التعاقد مع البنك علي هذا التسهيل (القرض)
	 * * ولكن عند نهاية هذا التاريخ يظل 
	 */
	public function getContractEndDate()
	{
		return $this->contract_end_date;
	}
	public function getContractEndDateFormatted()
	{
		$contractEndDate = $this->getContractEndDate() ;
		return $contractEndDate ? Carbon::make($contractEndDate)->format('d-m-Y'):null ;
	}
	public function getAccountNumber()
	{
		return $this->account_number ;
	}
	/**
	 * * هو الحد الاقصى للسحب (اللي هو قيمة التسهيل) وبالتالي ما تقدرتش عموما تتخطى هذا الرقم
	 */
	public function getLimit()
	{
		return $this->limit ?: 0 ;
	}
	public function getLimitFormatted()
	{
		return number_format($this->getLimit());
	}
	/**
	 * * هو قيمة المسحوب من هذا التسهيل لحظه فتح الحساب علي السيستم (كاش فيرو)
	 * * وهو بالتالي يعتبر ال
	 * * beginning balance 
	 * * اللي بتبدا بيه
	 */
	public function getOutstandingBalance()
	{
		return $this->outstanding_balance ?: 0 ;
	}
	// public function getMinInterestRate()
	// {
	// 	return $this->min_interest_rate;
	// }
	// public function getMinInterestRateFormatted()
	// {
	// 	return number_format($this->getMinInterestRate(),2);
	// }

	public static function findByFinancialInstitutionIds(array $overdraftIds):array
	{
		return self::whereIn('financial_institution_id',$overdraftIds)->pluck('id')->toArray();
	}
	/**
	 * * هو عدد الايام اللي اجباري تسدد السحبات فيها 
	 * * وليكن مثلا لو سحبت النهاردا الف جنية فا مفروض اسددها بعد كام يوم 
	 */
	public function getMaxSettlementDays()
	{
		return $this->to_be_setteled_max_within_days?:0;
	}
	
	public function getCurrency()
	{
		return $this->currency ;
	}
	public function financialInstitution()
	{
		return $this->belongsTo(FinancialInstitution::class , 'financial_institution_id','id');
	}
	public function lendingInformation()
	{
		return $this->hasMany(LendingInformation::class , 'overdraft_against_commercial_paper_id','id');
	}
	public static function getAllAccountNumberForCurrency($companyId , $currencyName,$financialInstitutionId,$keyName='account_number'):array
	{
		return self::where('company_id',$companyId)->where('currency',$currencyName)
		->where('financial_institution_id',$financialInstitutionId)
		->pluck('account_number',$keyName)->toArray();		
	}
	
	public static function getAllAccountIdForCurrency($companyId , $currencyName,$financialInstitutionId):array
	{
		return self::where('company_id',$companyId)->where('currency',$currencyName)
		->where('financial_institution_id',$financialInstitutionId)
		->pluck('account_number','id')->toArray();		
	}
	public static function findByAccountNumber($accountNumber,int $companyId,int $financialInstitutionId)
	{
		return self::where('company_id',$companyId)
		->where('account_number',$accountNumber)
		->where('financial_institution_id',$financialInstitutionId)
		->first();
	}

	
	/**
	 * * هي فايدة بيحددها البنك بالبنك المركزي
	 */
	public function getLatestRate()
	{
		/** @phpstan-ignore-next-line */
		if(is_null($this->rates)){
			return 0;
		}
		
		return $this->rates->where('date','<=',now()->format('Y-m-d'))->sortByDesc('date')->first();
	}
	public function getBorrowingRate()
	{
		$latestRate = $this->getLatestRate();
		return $latestRate ? $latestRate->getBorrowingRate() : 0 ; 
		// return $this->borrowing_rate ?: 0;
	}
	public function getBorrowingRateFormatted()
	{
		return number_format($this->getBorrowingRate(),2);
	}
		/**
	 * * هي فايدة خاصة بالبنك بناء علي العميل (طبقا للقدرة المالية زي امتلاكك للمصانع)
	 */
	public function getMarginRate()
	{
		$latestRate = $this->getLatestRate();
		return $latestRate ? $latestRate->getMarginRate() : 0 ; 
		// return $this->bank_margin_rate ?: 0 ;
	}
	public function getMarginRateFormatted()
	{
		return number_format($this->getMarginRate(),2);
	}
	public function getInterestRate()
	{
		$latestRate = $this->getLatestRate();
		return $latestRate ? $latestRate->getInterestRate() : 0 ; 
		// return $this->interest_rate?:0;
	}
	public function getInterestRateFormatted()
	{
		return number_format($this->getInterestRate(),2);
	}
	public function storeRate($date , $minInterestRate , $marginRate , $borrowingRate , $interestRate,$companyId)
	{
		return $this->rates()->create([
			'date'=>$date,
			'min_interest_rate'=>$minInterestRate,
			'margin_rate'=>$marginRate, // or bank_margin_rate 
			'borrowing_rate'=>$borrowingRate,
			'interest_rate'=>$interestRate,
			'company_id'=>$companyId
		]);
	}	
	

	
	
}
