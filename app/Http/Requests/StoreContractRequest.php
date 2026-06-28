<?php

namespace App\Http\Requests;

use App\Models\Traits\Requests\HasFormattedAmount;
use App\Rules\TwoNumericsAreEqual;
use App\Rules\UniqueArrayRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
	use HasFormattedAmount;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
	public function prepareForValidation():array 
	{
		$modelType=$this->route('type');
		$columnName = 'salesOrders';
		if($modelType == 'Supplier'){
			$columnName = 'purchasesOrders';
		}
		$this->merge([
			'amount'=>number_unformat($this->amount),
			$columnName=>$this->unformatNumericKeysFromArray($this->{$columnName},['amount'])
		]);
		return [];
	}

    public function rules()
    {
		$modelType=$this->route('type');
		$message = __('Total amounts of Sales Orders must be equal to Contract Amount') ;
		$columnName = 'salesOrders';
		if($modelType == 'Supplier'){
			$message = __('Total amounts of Purchase Orders must be equal to Contract Amount') ;
			$columnName = 'purchasesOrders';
		}
        return [
			'amount'=>['required',new TwoNumericsAreEqual(collect($this->input($columnName.'.*'))->sum('amount'),$this->get('amount'),$message)],
			$columnName.'.*.so_number'=>[new UniqueArrayRule($this->input($columnName.'.*.so_number',[]),__('Sales Order Number Can Not Be Repeated'))]
        ];
    }
}
