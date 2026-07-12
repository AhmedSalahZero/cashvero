<?php 
namespace App\Interfaces\Models;

interface IHasDebitCurrentAccountStatement
{
	public function storeCurrentAccountDebitBankStatement(string $date , $debit , int $financialInstitutionAccountId , bool $isTdRenewal = false  , ?string $commentEn = null , ?string $commentAr = null,$isPeriodCdOrTdInterest = false  , $isBreakInterest =false );
}
