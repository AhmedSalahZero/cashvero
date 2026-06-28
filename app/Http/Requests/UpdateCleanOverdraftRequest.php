<?php

namespace App\Http\Requests;


class UpdateCleanOverdraftRequest extends StoreCleanOverdraftRequest
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
		$excludeAccountNumbers = (array)Request()->route('cleanOverdraft')->getAccountNumber();
        return array_merge(
			parent::rules($excludeAccountNumbers),
			[]
		);
    }
}
