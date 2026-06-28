<?php

namespace App\Http\Requests;

use App\Models\OverdraftAgainstCommercialPaper;


class UpdateOverdraftAgainstCommercialPaperRequest extends StoreOverdraftAgainstCommercialPaperRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true ;
    }

 
    public function rules(array $excludeAccountNumbers = [])
    {
		$overdraftAgainstCommercialPaper = Request()->route('overdraftAgainstCommercialPaper') ;
		/**
		 * @var OverdraftAgainstCommercialPaper  $overdraftAgainstCommercialPaper ;
		 */
		$excludeAccountNumbers = (array)$overdraftAgainstCommercialPaper->getAccountNumber();
        return array_merge(
			parent::rules($excludeAccountNumbers),
			[]
		);
    }
}
