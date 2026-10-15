<?php

namespace App\Http\Requests;

use App\Rules\DateMustBeGreaterThanDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreLgRenewalDateRequest extends FormRequest
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
        return [
            'renewal_date'=>['required',new DateMustBeGreaterThanDate($this->get('renewal_date'),$this->get('expiry_date'),__('Renewal Date Must Be Greater Than Expiry Date'))],
            /**
             * The three terms the bank may re-price at renewal.
             *
             * Left empty they mean "the bank did not change this", so
             * they are nullable rather than required — that is also
             * what makes the endpoint keep working for anything still
             * posting only a date and a fee.
             *
             * They arrive display-formatted ("20,000"), same as
             * fees_amount, so the numeric check runs on the unformatted
             * value. Negative is rejected: a bank never asks for a
             * negative cover, and a stray minus would post a refund
             * that never happened.
             *
             * @see \App\Support\LetterOfGuarantee\LgRenewalTerms
             */
            'cash_cover_amount'=>['nullable',$this->amountRule(__('Cash Cover'))],
            'lg_commission_amount'=>['nullable',$this->amountRule(__('LG Commission Amount'))],
            'min_lg_commission_fees'=>['nullable',$this->amountRule(__('Min LG Commission Fees'))],
        ];
    }

    protected function amountRule(string $label): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($label) {
            if ($value === '' || is_null($value)) {
                return;
            }

            $amount = number_unformat($value);

            if (! is_numeric($amount) || $amount < 0) {
                $fail(__(':field Must Be A Positive Number', ['field' => $label]));
            }
        };
    }
}
