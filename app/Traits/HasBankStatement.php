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
			/**
			 * * مش بنعمل update لـ updated_at . لو القيمة ما اتغيرتش
			 * * (نفس الثانية) Eloquent بيعتبر الصف مش dirty ومش بيطلق
			 * * updated ، فالـ cascade ما بيبدأش والصفوف اللي بعد
			 * * رصيد أول المدة بتفضل على الرصيد القديم.
			 */
			$firstBankStatementToBeUpdated::updateNextRows($firstBankStatementToBeUpdated);
		}
	}
	

	
}
