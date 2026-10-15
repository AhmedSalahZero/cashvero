<?php

namespace App\Rules;

use App\Models\FinancialInstitutionAccount;
use Illuminate\Contracts\Validation\Rule;
use App\Support\BankStatements\GeneratedMonthEndInterestRows;
use Illuminate\Support\Facades\DB;

/**
 * * حركات كشف الحساب متسجلة كارقام مجردة و مفيش فيها عمود عملة
 * * العملة متسجلة مرة واحدة بس على الحساب نفسه
 * * فا تغيير عملة الحساب معناه ان كل الحركات القديمة تتقري بعملة تانية من غير ما ارقامها تتحول
 * * و مفيش طريقة نحولها بيها لان مفيش سعر صرف متسجل مع كل حركة
 * * علشان كدا ممنوع تغيير العملة طول ما في حركات فعلية غير الرصيد الافتتاحي
 */
class CanNotChangeCurrencyWhenAccountHasStatementsRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected FinancialInstitutionAccount $financial_institution_account ;
    public function __construct(FinancialInstitutionAccount $financialInstitutionAccount)
    {
        $this->financial_institution_account = $financialInstitutionAccount ;
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
		if(! $value || $value == $this->financial_institution_account->getCurrency()){
			return true ;
		}

		/**
		 * * نفس علة DateCanNotBeAfterAnyStatementRule بالظبط : الصفوف اللي
		 * * بيولدها النظام لفوائد اخر الشهر و لسه فاضية مش حركات ، و كانت
		 * * بتمنع تغيير العملة برسالة "فيه حركات على الحساب" على حساب
		 * * مفيهوش ولا حركة واحدة
		 *
		 * * التعريف متعرف في مكان واحد بس علشان القاعدتين ما يفترقوش
		 * @see \App\Support\BankStatements\GeneratedMonthEndInterestRows
		 */
		$query = DB::table('current_account_bank_statements')
			->where('financial_institution_account_id',$this->financial_institution_account->id)
			->where('is_beginning_balance',0);

		$hasStatements = GeneratedMonthEndInterestRows::excludeUntouchedFrom($query)->exists();

		return ! $hasStatements ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Currency Can Not Be Changed Because There Are Transactions On This Account');
    }

}
