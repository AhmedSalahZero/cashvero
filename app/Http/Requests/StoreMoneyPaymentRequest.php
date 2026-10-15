<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitution;
use App\Models\MoneyPayment;
use App\Rules\ActiveFinancialInstitutionAccountRule;
use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use App\Rules\AtLeaseOneSettlementMustBeExist;
use App\Rules\ContractDownPaymentRule;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\LeasingContractRoomRule;
use App\Rules\MediumTermLoanFirstInstallmentDateRule;
use App\Rules\MediumTermLoanRoomRule;
use App\Rules\ReceivingOrPaymentDateRule;
use App\Rules\SettlementPlusWithoutCanNotBeGreaterNetBalance;
use App\Rules\UnappliedAmountForContractAsDownPaymentRule;
use App\Rules\UniqueChequeNumberRule;
use App\Rules\UniqueReceiptNumberForReceivingBranchRule;
use App\Rules\ValidAllocationsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyPaymentRequest extends FormRequest 
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
	
	protected function prepareForValidation():array 
	{
		$paidAmounts = $this->paid_amount ;
		$paidAmounts = collect($paidAmounts)->map(function($item){
			return number_unformat($item);
		})->toArray();
		
		
		$additionalData = [];
		if($this->down_payment_type == MoneyPayment::DOWN_PAYMENT_GENERAL || $this->down_payment_type == MoneyPayment::SETTLEMENT_OF_OPENING_BALANCE){
			$additionalData = [
				'contract_id'=>null,
				'purchases_orders_amounts'=>[],
				'settlements'=>[],
			];
		}
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): editing an
		 * existing Outgoing Transfer / Cash Payment failed with "There
		 * Is No Enough Balance" even when there genuinely was enough —
		 * because AmountCanNotBeGreaterThanEndBalanceAtPaymentDate's
		 * balance-compensation logic (which correctly excludes THIS
		 * transaction's own already-posted amount from the check) only
		 * runs when the request has 'modelId'/'modelType' — which this
		 * Store/Update-shared request never actually sent. It's the
		 * exact same mechanism the on-screen balance preview already
		 * uses correctly (that's why the displayed balance was right
		 * but saving still failed) — just never wired into the save
		 * action itself. Only applies when actually editing (a
		 * moneyPayment route-bound model exists); a brand-new payment
		 * has no prior amount to compensate for.
		 */
		if ($this->route('moneyPayment')) {
			$additionalData['modelId'] = $this->route('moneyPayment')->id;
			$additionalData['modelType'] = 'MoneyPayment';
			/**
			 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): same
			 * class of bug as Money Received's Cheque Number / Receipt
			 * Number — editing without changing either failed with a
			 * false "already exists" error, since current_cheque_id
			 * (Payable Cheque) / cash_id (Cash Payment) were never
			 * actually submitted. Resolved from this record's own
			 * related Payable Cheque / Cash Payment row.
			 */
			$additionalData['current_cheque_id'] = $this->route('moneyPayment')->payableCheque?->id;
			$additionalData['cash_id'] = $this->route('moneyPayment')->cashPayment?->id;
		}
		$this->merge(array_merge([
			'paid_amount'=>$paidAmounts,
			'unapplied_amount'=>number_unformat($this->get('unapplied_amount'))
		] , $additionalData));
		
		return [];
	}
	

   
    public function rules()
    {
		$companyId = getCurrentCompanyId();
		$type = $this->type ; 
		$paidAmount = $this->{'paid_amount.'.$type };
		$partnerType = $this->partner_type;
		
		
		$financialInstitution = null ;
		$accountTypeId = $this->input('account_type.'.$type);
		$accountNumber = $this->input('account_number.'.$type);
		$financialInstitutionId = $this->input('delivery_bank_id.'.$type);
		$isLeasing = $type == MoneyPayment::LEASING ;
		$openingBalanceDate = null;
		if($financialInstitutionId && $accountTypeId && $accountNumber ){
			$financialInstitution = FinancialInstitution::find($financialInstitutionId);
			$openingBalanceDate =$financialInstitution->getOpeningBalanceForAccount($accountTypeId,$accountNumber); 
		}
        return [
			'supplier_id'=>'required',
			'type'=>'required',
			'delivery_branch_id'=>$type == MoneyPayment::CASH_PAYMENT  ? ['required','not_in:-1'] : [],
			'paid_amount.'.$type => ['required','gt:0'],
			'account_type.'.$type => $accountTypeValidation = $type == MoneyPayment::OUTGOING_TRANSFER || $type == MoneyPayment::PAYABLE_CHEQUE ? 'required' : 'sometimes',
			'account_number.'.$type => $type == MoneyPayment::OUTGOING_TRANSFER || $type == MoneyPayment::PAYABLE_CHEQUE
				? ['required', new ActiveFinancialInstitutionAccountRule($companyId, $accountTypeId, $accountNumber, $financialInstitutionId)]
				: ['sometimes'],
			'delivery_date'=>['required',new ReceivingOrPaymentDateRule($companyId,$type,[MoneyPayment::OUTGOING_TRANSFER],[MoneyPayment::CASH_PAYMENT],$financialInstitutionId,$accountTypeId,$accountNumber)],
			'unapplied_amount'=>'sometimes|gte:0',
			'net_balance_rules'=>new SettlementPlusWithoutCanNotBeGreaterNetBalance($this->get('settlements',[])),
			'settlements'=>$partnerType =='is_supplier' ? new AtLeaseOneSettlementMustBeExist($this->get('settlements',[])) : [],
			'cheque_number'=>$type == MoneyPayment::PAYABLE_CHEQUE ? ['required',new UniqueChequeNumberRule(Request()->input('account_number.payable_cheque'),Request()->get('current_cheque_id'),__('Cheque Number Already Exist'))] : [],
			'due_date'=>$type == MoneyPayment::PAYABLE_CHEQUE ? ['required',new DateMustBeGreaterThanOrEqualDate(null,$openingBalanceDate , __('Cheque Due Date Must Be Greater Than Or Equal Account Opening Date') )]:[],
			'receipt_number'=>$type== MoneyPayment::CASH_PAYMENT ? ['required',new UniqueReceiptNumberForReceivingBranchRule('cash_payments',$this->delivery_branch_id?:0,$this->cash_id,__('Receipt Number For This Branch Already Exist'))] : [],
			'purchases_orders_amounts'=>$partnerType =='is_supplier' ? [new UnappliedAmountForContractAsDownPaymentRule($this->unapplied_amount?:0,$this->is_down_payment,$paidAmount)] : [], 
			'allocations'=>[new ValidAllocationsRule()],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate($type,$this->input('paid_amount.'.$type),$this->route('company'),$this->input('account_type.'.$type),$this->input('account_number.'.$type),$financialInstitutionId,$this->delivery_date,$this->delivery_branch_id,null),
			/**
			 * * سحبة من قرض متوسط الاجل ما ينفعش تتخطى المتبقي من القرض
			 * * والرول دي بتغطي ال
			 * * payable_cheque
			 * * كمان اللي الرول اللي فوق مش بتغطيه
			 */
			'medium_term_loan_room'=>new MediumTermLoanRoomRule($this->route('company'),$this->input('paid_amount.'.$type),$accountTypeId,$accountNumber,$financialInstitutionId,$type == MoneyPayment::PAYABLE_CHEQUE ? $this->due_date : $this->delivery_date),
			/**
			 * * لسه ما اتصرفش لكن اليوم اللي هيتسدد فيه اول قسط وصل ..
			 * * يبقي القرض معتبر اتصرف بالفعل ومينفعش يتدفع بيه فواتير
			 * * تاني من التاريخ ده وما بعده
			 */
			'medium_term_loan_first_installment_date'=>new MediumTermLoanFirstInstallmentDateRule($this->route('company'),$accountTypeId,$accountNumber,$financialInstitutionId,$type == MoneyPayment::PAYABLE_CHEQUE ? $this->due_date : $this->delivery_date),
			/**
			 * * الدفع من خلال ليزنج: شركة التأجير والعقد بس.
			 * * ال exists بتضمن ان العقد بتاع الشركة دي فعلا وجاري ، وقاعدة
			 * * ال room بتمنع الدفع باكتر من المتبقي في العقد بتاريخ الدفع.
			 * * وباقي قواعد حسابات البنوك فوق بتعدي من غير ما تشتغل لان نوع
			 * * الدفع ده مش في اي من قوايمها.
			 */
			'leasing_company_id'=>$isLeasing ? ['required'] : [],
			'leasing_contract_id'=>$isLeasing ? [
				'required',
				Rule::exists('leasing_contracts','id')
					->where('company_id',$companyId)
					->where('leasing_company_id',$this->input('leasing_company_id'))
					->where('status',\App\Models\LeasingContract::RUNNING),
				new LeasingContractRoomRule($companyId,$this->input('paid_amount.'.$type),$this->input('leasing_company_id'),$this->delivery_date),
			] : [],
			'downPayment_over_contract'=>[new ContractDownPaymentRule($paidAmount,false)]
        ];
    }
	public function messages()
	{
		$type = $this->type ; 
		return [
		
			'account_type.'.$type.'.required' => __('Please Select Account Type') ,
			'account_number.'.$type.'.required' => __('Please Select Account Number') ,
			'unapplied_amount.gte'=>__('Invalid Unapplied Amount'),
			'type.required'=>__('Please Select Money Type'),
			'paid_amount.'.$type.'.required'=>__('Please Enter Paid Amount'),
			'paid_amount.'.$type.'.gt'=>__('Paid Amount Must Be Greater Than Zero'),
			'delivery_branch_id.not_in'=>__('Please Enter New Branch Name'),
			'due_date.required'=>__('Cheque Due Date Is Required'),
			'delivery_date.required'=>__('Please Select Payment Date'),
			'cheque_number.required'=>__('Please Insert Cheque Number'),
			'leasing_company_id.required'=>__('Please Select Leasing Company'),
			'leasing_contract_id.required'=>__('Please Select Contract Name'),
			'leasing_contract_id.exists'=>__('This Contract Does Not Belong To The Selected Leasing Company'),
		];
	}
	
}
