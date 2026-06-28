<?php
namespace App\Traits;

use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use Carbon\Carbon;


trait HasBankStatement
{
	public function updateBankStatementsFromDate(string $date)
	{
		$isOverdraft  = $this->isOverdraft(); 
		$orderBy = $isOverdraft ? 'date asc , priority asc , id asc' : 'date asc , id asc';
		$firstBankStatementToBeUpdated = (self::getBankStatementTableClassName())::where(self::generateForeignKeyFormModelName(),$this->id)
		
		->where('date','>=',$date)
		->orderByRaw($orderBy)
		->first();	
		
		if($firstBankStatementToBeUpdated){
			$firstBankStatementToBeUpdated->update([
				'updated_at'=>now()
			]);
		}
	}
	

	
}
