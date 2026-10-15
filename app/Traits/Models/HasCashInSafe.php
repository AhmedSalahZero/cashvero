<?php 
namespace App\Traits\Models;



trait HasCashInSafe 
{
	public function storeCashInSafeDebitStatement(string $date , $debit , string $currencyName,int $branchId,$exchangeRate)
	{
		return $this->cashInSafeDebitStatement()->create([
			'branch_id'=>$branchId,
			'currency'=>$currencyName ,
			'exchange_rate'=>$exchangeRate,
			'company_id'=>$this->company_id ,
			'debit'=>$debit,
			'credit'=>0,
			'date'=>$date,
		]);
	}	
}
