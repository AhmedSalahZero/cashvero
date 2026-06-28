<?php

namespace App\Http\Requests;

use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\DateMustBeLessThanOrEqualDate;
use App\Rules\DeductionAmountRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceDeductionRequest extends FormRequest
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

   
    public function rules()
    {
	
		$InvoiceId = Request()->route('modelId');
		$invoiceModelName = Request()->route('modelType');
		$invoice = ('App\Models\\'.$invoiceModelName)::find($InvoiceId);
		$netBalance=$invoice->getNetBalance(); 
        return [
			'deductions.*.deduction_id'=>'required|numeric',
			'deductions.*.date'=>['required','date',
			new DateMustBeGreaterThanOrEqualDate(null,$invoice->getInvoiceDate() , __('Date Must Be Greater Than Or Equal Invoice Date And Less Than Or Equal Today'),true),
			new DateMustBeLessThanOrEqualDate(null,now() , __('Date Must Be Greater Than Or Equal Invoice Date And Less Than Or Equal Today'),true),
		],
			
			'deductions.*.amount'=>['required','numeric','gt:0' , new DeductionAmountRule($netBalance)],
			
        ];
    }
	public function messages()
	{
		return [
			'deductions.*.deduction_id.required'=>__('Please Select Deduction Name'),
			// 'deductions.*.amount.lte'=>__('Deduction Amount Must Be Less Than Or Equal Net Balance'),
			'deductions.*.amount.gte'=>__('Deduction Amount Must Be Greater Than Zero'),
		];
	}
}
