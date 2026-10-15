<?php
namespace App\Traits\Models;

use Carbon\Carbon;



trait HasCommissionStatements
{
	public function storeCommissionAmountCreditBankStatement(string $lgCommissionInterval , int $numberOfIterationsForQuarter , string $issuanceDate,string $openingBalanceDateOfCurrentAccount,$maxLgCommissionAmount,int $financialInstitutionAccountIdForFeesAndCommission,string $transactionName,string $lgType,bool $isOpeningBalance , ?int $lgRenewalDateHistoryId = null ):void
	{
		$customerName =  $this->getBeneficiaryName();
		if($lgCommissionInterval == 'quarterly'){
			for($i = 0 ; $i< (int)$numberOfIterationsForQuarter ; $i++ ){
				$currentDate = Carbon::make($issuanceDate)->addMonths($i * 3)->format('Y-m-d');
				$isActive = now()->greaterThanOrEqualTo($currentDate);
				if(!$isOpeningBalance ||  Carbon::make($currentDate)->greaterThanOrEqualTo($openingBalanceDateOfCurrentAccount) ){
					$this->storeCurrentAccountCreditBankStatement($currentDate,$maxLgCommissionAmount , $financialInstitutionAccountIdForFeesAndCommission,0,$isActive,__('Commission Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'customerName'=>$customerName,'transactionName'=>$transactionName],'en'),__('Commission Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName],'ar'),false,true,$lgRenewalDateHistoryId,1);
				}
			}
		}else{
			$currentDate = Carbon::make($issuanceDate)->format('Y-m-d');
			if(!$isOpeningBalance ||  Carbon::make($currentDate)->greaterThanOrEqualTo($openingBalanceDateOfCurrentAccount) ){
				$this->storeCurrentAccountCreditBankStatement($issuanceDate,$maxLgCommissionAmount , $financialInstitutionAccountIdForFeesAndCommission,0,1, __('Commission Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'en'),'customerName'=>$customerName,'transactionName'=>$transactionName],'en'),__('Commission Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'  ,['lgType'=>__($lgType,[],'ar'),'customerName'=>$customerName,'transactionName'=>$transactionName],'ar'),false,true,$lgRenewalDateHistoryId,1);
			}
		}
	}

}
