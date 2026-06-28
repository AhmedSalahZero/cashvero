<?php

namespace App\Http\Requests;


class UpdateLetterOfCreditIssuanceRequest extends StoreLetterOfCreditIssuanceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

 
    public function rules()
    {
        return array_merge(
			Parent::rules(),
			[]
		);
    }
}
