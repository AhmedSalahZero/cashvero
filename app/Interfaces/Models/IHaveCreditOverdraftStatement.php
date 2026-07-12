<?php 
namespace App\Interfaces\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

interface IHaveCreditOverdraftStatement
{
	public function cleanOverdraftCreditBankStatement():HasOne;
	public function fullySecuredOverdraftCreditBankStatement():HasOne;	
	public function overdraftAgainstCommercialPaperCreditBankStatement():HasOne;
	public function overdraftAgainstAssignmentOfContractCreditBankStatement():HasOne;
	public function cashInSafeCreditStatement():HasOne;
	public function isCashPayment():bool;
}
