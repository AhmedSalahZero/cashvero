<?php 
namespace App\Services\Api\Traits;

trait CommonHelper 
{
	public function getJournalIdFromChartOfAccountId(?int $chartOfAccountId):?int
	{
		return $this->fetchData('account.journal',[],[[['default_account_id','=',$chartOfAccountId]]])[0]['id']??null;
	}
	
}
