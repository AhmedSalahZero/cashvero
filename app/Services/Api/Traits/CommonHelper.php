<?php 
namespace App\Services\Api\Traits;

trait CommonHelper 
{
	/**
	 * * لو $chartOfAccountId جه null بنرجع null علي طول من غير ما نسأل اودو
	 * * لان الاستعلام بـ default_account_id = null بيرجع اليوميات اللي مالهاش
	 * * حساب افتراضي وياخد اول واحدة فيهم , يعني يومية غلط خالص بتتخزن
	 * * علي الحساب او الخزنة وكل الحركات بعد كدا بتروح لمكان تاني في اودو
	 */
	public function getJournalIdFromChartOfAccountId(?int $chartOfAccountId):?int
	{
		if(is_null($chartOfAccountId)){
			return null ;
		}
		return $this->fetchData('account.journal',[],[[['default_account_id','=',$chartOfAccountId]]])[0]['id']??null;
	}
	
}
