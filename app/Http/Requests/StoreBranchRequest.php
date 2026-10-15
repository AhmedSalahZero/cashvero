<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
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
		// foreach(Request()->get('safe') as $safeArr){
			
		// }
        return [
            //'safe.name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('CashVeroBranch','name',$this->id,[],__('This Branch Already Exist'))]
        ];
    }
}
