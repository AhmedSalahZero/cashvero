<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class DeductionAmountRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected float $net_balance ; 
    public function __construct($netBalance)
    {
		$this->net_balance = $netBalance;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		/**
		 * * المبالغ بتيجي من الفورمة كنصوص ، و ممكن تكون متفرمتة
		 * * (1,000.00) — الجمع المباشر عليها بيرمي فاتال في PHP 8.4
		 */
		$amounts = array_sum(\App\Helpers\HArr::unformatValues(
			array_column(Request()->get('deductions', []), 'amount')
		));

		/**
		 * * مقارنة الفلوس بفرق ضئيل : الرصيد رقم عشري بيتراكم عليه انحراف ،
		 * * فمساواة تامة ممكن ترفض حفظ نفس القيمة بفرق مليار جزء من الجنيه
		 */
		return round($this->net_balance, 2) + 0.001 >= round($amounts, 2);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Deduction Amount Must Be Less Than Or Equal Net Balance');
    }
}
