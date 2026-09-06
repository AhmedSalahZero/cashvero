<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * * رقم الشيك المستلم لازم يبقى فريد بالنسبة للبنك الساحب و العميل
 *
 * * قبل كده كان الفحص على (الشركة + البنك الساحب) بس ، يعني عميلين
 * * مختلفين من نفس البنك ما كانش ينفع يكون عندهم نفس رقم الشيك — و ده
 * * مش صحيح ، كل عميل عنده دفتر شيكات مستقل
 */
class UniqueChequeNumberForCustomerRule implements Rule
{
    protected $id;

    protected $draweeBankId;

    protected $customerId;

    protected $failedMessage;

    public function __construct(?int $draweeBankId, $excludeId = null, $failedMessage = null, $customerId = null)
    {
        $this->draweeBankId = $draweeBankId;
        $this->id = $excludeId;
        $this->failedMessage = $failedMessage;
        /**
         * * بنقراه من الريكوست لو المنادي ما بعتوش ، عشان كل نداءات
         * * القاعدة القديمة تفضل شغالة زي ما هي
         */
        $this->customerId = $customerId ?? request()->get('customer_id');
    }

    public function passes($attribute, $value)
    {
        if (! $this->draweeBankId) {
            return false;
        }

        return ! DB::table('cheques')
            ->join('money_received', 'money_received.id', '=', 'cheques.money_received_id')
            ->where('cheques.company_id', getCurrentCompanyId())
            ->where('cheques.drawee_bank_id', $this->draweeBankId)
            ->where('money_received.partner_id', $this->customerId)
            ->where('cheques.cheque_number', '=', $value)
            ->where('cheques.id', '!=', $this->id)
            ->exists();
    }

    public function message()
    {
        return $this->failedMessage;
    }
}
