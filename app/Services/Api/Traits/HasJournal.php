<?php 
namespace App\Services\Api\Traits;


trait HasJournal 
{

	public function getJournalId($moneyModel):int 
	{
		$isChequeOrChequePayment = $moneyModel->isChequeOrChequePayment();
		if($isChequeOrChequePayment){
			return $moneyModel->getChequeJournalId();
		}
		$isCashInSafeOrCashPayment = $moneyModel->isCash();
		return $isCashInSafeOrCashPayment  ? $moneyModel->getCashBranchJournalId() : $moneyModel->getBankAccountJournalId();
	}
	
	public function getChartOfAccountId($moneyModel):int 
	{
		$isChequeOrChequePayment = $moneyModel->isChequeOrChequePayment();
		if($isChequeOrChequePayment){
			return $moneyModel->getChequeOdooId() ;
		}
		$isCashInSafeOrCashPayment = $moneyModel->isCash();
		return $isCashInSafeOrCashPayment  ? $moneyModel->getCashBranchOdooId() : $moneyModel->getBankAccountOdooId();
	}
	
}
