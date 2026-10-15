<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;




trait HasLastStatementAmount
{
	public function getFinancialInstitutionId():int 
	{
		return $this->financial_institution_id;
	}
	public static function getLastAmountFormatted(int $companyId , string $currencyName , int $fullySecuredOverdraftId,$accountNumber ) 
	{
		$currentTableName = (new self)->getTable();
		$row = 	DB::table(self::getBankStatementTableName())
				->join($currentTableName,$currentTableName.'.id' ,'=',self::getBankStatementTableName().'.'.self::generateForeignKeyFormModelName())
                ->where(self::getBankStatementTableName().'.company_id', $companyId)
                ->where('currency', $currencyName)
				->where('account_number',$accountNumber)
				->where(self::generateForeignKeyFormModelName(),$fullySecuredOverdraftId)
                ->orderByRaw(self::getBankStatementTableName().'.date desc ,'. self::getBankStatementTableName().'.id desc')
                ->limit(1)
                ->first();
	
		return $row ? number_format($row->end_balance) : 0;
	}	
	
}
