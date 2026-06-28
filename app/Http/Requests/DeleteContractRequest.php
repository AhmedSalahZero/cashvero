<?php

namespace App\Http\Requests;

use App\Models\Contract;
use App\Rules\CanDeleteContractRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteContractRequest extends FormRequest
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
		$contract = Contract::find($this->route('contract'));
        return [
			'can_delete_contract'=>[new CanDeleteContractRule($contract)]
        ];
    }
}
