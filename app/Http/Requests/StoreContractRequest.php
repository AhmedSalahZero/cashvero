<?php

namespace App\Http\Requests;

use App\Models\Traits\Requests\HasFormattedAmount;
use App\Rules\TwoNumericsAreEqual;
use App\Rules\UniqueArrayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
			/**
			 * * كود العقد لازم يكون فريد جوه الشركة الواحدة، لأن الفواتير
			 * * بتترابط بالكود مش بالـ id:
			 * *   customer_invoices.contract_code -> contracts.code
			 * *   supplier_invoices.contract_code -> contracts.code
			 * * لو اتكرر، الفاتورة بتتعلّق بكذا عقد وأي JOIN بيضخّم أرقامه.
			 * * الحقل ده نص حر في نموذج الإنشاء (readonly عند التعديل بس)،
			 * * وفحص التفرّد اللي جوه Contract::generateRandomContract()
			 * * بيتخطّى لما الكود ييجي من الفورم — فالقاعدة دي هي الحاجز الفعلي.
			 */
			'code'=>[
				'required','string','max:255',
				Rule::unique('contracts','code')
					->where(fn ($query) => $query->where('company_id', $this->companyIdForRule()))
					->ignore($this->contractIdForRule()),
			],
			'amount'=>['required',new TwoNumericsAreEqual(collect($this->input($columnName.'.*'))->sum('amount'),$this->get('amount'),$message)],
			$columnName.'.*.so_number'=>[new UniqueArrayRule($this->input($columnName.'.*.so_number',[]),__('Sales Order Number Can Not Be Repeated'))]
        ];
    }

	public function messages()
	{
		return [
			'code.unique'=>__('This contract code is already used by another contract in this company'),
		];
	}

	/**
	 * * الـ Request ده مستخدم في الإنشاء والتعديل، والراوت بيمرر
	 * * {company} في الحالتين — ساعات ككائن وساعات كـ id
	 */
	private function companyIdForRule():?int
	{
		$company = $this->route('company');

		return $company ? (int) (is_object($company) ? $company->id : $company) : null;
	}

	/**
	 * * في التعديل بس بيبقى فيه {contract} في الراوت — بنستثنيه من فحص
	 * * التفرّد عشان العقد ما يتعارضش مع نفسه (الحقل بيتبعت readonly)
	 */
	private function contractIdForRule():?int
	{
		$contract = $this->route('contract');

		return $contract ? (int) (is_object($contract) ? $contract->id : $contract) : null;
	}
}
