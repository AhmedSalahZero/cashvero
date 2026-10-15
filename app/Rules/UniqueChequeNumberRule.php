<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * * رقم الشيك المدفوع لازم يبقى فريد بالنسبة لرقم الحساب
 *
 * * قبل كده كان الفحص على (الشركة + بنك التسليم) ، و البنك الواحد ممكن
 * * يكون تحته اكتر من حساب و كل حساب ليه دفتر شيكات مستقل بأرقامه —
 * * فالفحص القديم كان بيرفض ارقام سليمة من حساب تاني
 */
class UniqueChequeNumberRule implements Rule
{
    protected $id;

    protected $accountNumber;

    protected $failedMessage;

    public function __construct($accountNumber, $excludeId = null, $failedMessage = null)
    {
        $this->accountNumber = $accountNumber;
        $this->id = $excludeId;
        $this->failedMessage = $failedMessage;
    }

    public function passes($attribute, $value)
    {
        if ($value == 0) {
            $this->failedMessage = __('Invalid Cheque Number');

            return false;
        }

        if (! $this->accountNumber) {
            return false;
        }

        return ! DB::table('payable_cheques')
            ->where('company_id', getCurrentCompanyId())
            ->where('account_number', $this->accountNumber)
            ->where('cheque_number', '=', $value)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    public function message()
    {
        return $this->failedMessage;
    }
}
