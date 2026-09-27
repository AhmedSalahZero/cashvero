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
		$merged = ['amount'=>number_unformat($this->amount)];
		/**
		 * * العقد بتنفيذ شهري ماعندهوش أوامر، فالمفتاح ده بييجي ناقص من
		 * * الفورم أصلاً — ساعتها ما نضيفوش للـ merge عشان ما نخلقش
		 * * مصفوفة فاضية مكان قيمة مش موجودة
		 */
		$orders = $this->input($columnName);
		if(is_array($orders)){
			$merged[$columnName] = $this->unformatNumericKeysFromArray($orders,['amount']);
		}
		$this->merge($merged);
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
			/**
			 * * العقد بتنفيذ شهري ماعندهوش أوامر — جزئية الأوامر مخفية في
			 * * الفورم — فقاعدة "مجموع الأوامر = قيمة العقد" ما تنطبقش عليه
			 * * (مجموعها صفر وكانت هتمنع الحفظ من الأساس).
			 */
			'amount'=>$this->boolean('is_monthly_executed')
				? ['required','numeric','gt:0']
				: ['required',new TwoNumericsAreEqual(collect($this->input($columnName.'.*'))->sum('amount'),$this->get('amount'),$message)],
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
	 * * تواريخ مراحل التنفيذ لازم تفضل جوه نافذة العقد .
	 *
	 * * الفورم (Contracts/Form.vue) حاطط min/max على حقول المراحل مربوطة
	 * * بتواريخ العقد ، فالمتصفح بيمنع الاختيار برّه المدى — و ده كويس
	 * * لكنه حماية متصفح بس : اي POST مباشر او استيراد بيعدّي من تحته .
	 *
	 * * الرسالة بتسمّي الامر و رقم المرحلة و التاريخ المخالف عشان تبقى
	 * * كافية لوحدها لو اللي بيعرضها بيعرض اول خطأ بس .
	 */
	public function withValidator($validator):void
	{
		$validator->after(function ($validator) {
			$contractStart = $this->asDate($this->input('start_date'));
			$contractEnd = $this->asDate($this->input('end_date'));

			if (! $contractStart || ! $contractEnd) {
				return;
			}

			$modelType = $this->route('type');
			$columnName = $modelType == 'Supplier' ? 'purchasesOrders' : 'salesOrders';
			$orderLabel = $modelType == 'Supplier' ? __('Purchase Order') : __('Sales Order');

			foreach ((array) $this->input($columnName, []) as $index => $order) {
				if (! is_array($order)) {
					continue;
				}

				$orderNumber = $order[$modelType == 'Supplier' ? 'po_number' : 'so_number'] ?? ($index + 1);

				for ($phase = 1; $phase <= 5; $phase++) {
					// المراحل الفاضية مالهاش تواريخ معتبرة
					if ((float) ($order['execution_percentage_'.$phase] ?? 0) <= 0) {
						continue;
					}

					$fields = [
						'start_date_'.$phase => __('Execution Start Date'),
						'end_date_'.$phase => __('Execution End Date'),
					];

					foreach ($fields as $field => $label) {
						$value = $this->asDate($order[$field] ?? null);
						if (! $value) {
							continue;
						}

						$isBefore = $value < $contractStart;
						$isAfter = $value > $contractEnd;
						if (! $isBefore && ! $isAfter) {
							continue;
						}

						$validator->errors()->add(
							$columnName.'.'.$index.'.'.$field,
							__(':label (:phase) — :order :number: :value :direction :boundary', [
								'label' => $label,
								'phase' => __('Phase').' '.$phase,
								'order' => $orderLabel,
								'number' => $orderNumber,
								'value' => $value,
								'direction' => $isBefore
									? __('is before the contract start date')
									: __('is after the contract end date'),
								'boundary' => $isBefore ? $contractStart : $contractEnd,
							])
						);
					}
				}
			}
		});
	}

	/**
	 * * فورم الـ Vue بيبعت <input type="date"> يعني Y-m-d . بنقبل كمان
	 * * صيغة الداتبيكر القديمة m/d/Y لو جت من اي مسار تاني .
	 */
	private function asDate($value):?string
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		$value = trim($value);

		if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
			return substr($value, 0, 10);
		}

		$parts = explode('/', $value);
		if (count($parts) === 3) {
			[$month, $day, $year] = $parts;

			return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
		}

		return null;
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
