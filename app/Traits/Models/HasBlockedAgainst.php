<?php
namespace App\Traits\Models;



trait HasBlockedAgainst
{
	public function getBlockedAgainstFormatted()
	{
		if($this->fullySecuredCleanOverdraft){
			return __('Blocked Against Overdraft');
		}
		if($this->letterOfGuaranteeIssuance){
			return __('Blocked Against LG');
		}
		return __('Free To Use');
	}
}
