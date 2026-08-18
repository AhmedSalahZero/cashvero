<?php

namespace App\Http\Requests;

use App\Models\LetterOfCreditIssuance;
use App\Rules\LetterOfCreditFacilityRoomRule;
use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLetterOfCreditIssuanceRequest extends FormRequest
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

    /**
     * * ملحوظة على النطاق: الحقول اللي هنا موجودة في الفورمات الحية
     * * (lc-facility / hundred-percentage-cash-cover).
     * * مثلا cash_cover_rate **مش** موجود في فورمات الـ cd و الـ td ،
     * * فمينفعش يبقى required هنا
     */
    public function rules()
    {
        return [
			'lc_amount'=>['required','gt:0'],
            'lc_currency' => ['required', 'string'],
            /**
             * * exchange_rate بيتضرب في lc_amount عشان يطلع المبلغ بالعملة
             * * الأساسية. لو صفر كل الحركات بتنزل بصفر
             */
            'exchange_rate' => ['required', 'gt:0'],
            'issuance_date' => ['required', 'date'],
            'lc_type' => ['required', 'string'],
            /**
             * * الاتنين دول بيتعمل عليهم find() في الكنترولر وبيتقرا منهم ->id
             * * على طول ، فلو مش موجودين كانت الصفحة بترمي 500
             */
            'financial_institution_id' => ['required', 'exists:financial_institutions,id'],
            'lc_fees_and_commission_account_id' => ['required', 'exists:financial_institution_accounts,id'],
            /**
             * * رقم الاعتماد من البنك — مينفعش يتكرر مع نفس البنك في نفس الشركة
             */
            'lc_code' => [
                'required',
                new UniqueToCompanyAndAdditionalColumnsRule(
                    'LetterOfCreditIssuance',
                    'lc_code',
                    $this->currentIssuanceId(),
                    [['financial_institution_id', '=', $this->get('financial_institution_id')]],
                    __('This LC Code Already Exist For This Bank')
                ),
            ],
            /**
             * * سحبة من اعتماد لازم متتخطاش المتبقي من الفاسيليتي —
             * * client-flagged (2026-08-18), جنب باج العملة اللي كان
             * * بيخلي "Total LCs Room" يفضل يبين الحد كامل حتى مع
             * * اعتمادات موجودة فعلا. مربوطة بمصدر lc-facility بس، من
             * * route('source') مش من الريكويست نفسه.
             */
            'lc_facility_room' => [
                new LetterOfCreditFacilityRoomRule(
                    $this->route('company'),
                    // Same conversion as LetterOfCreditIssuance::getLcAmountInMainCurrency() —
                    // room is tracked in the company's main currency, so the
                    // amount compared against it has to be in the same units.
                    (float) number_unformat($this->input('lc_amount')) * (float) number_unformat($this->input('exchange_rate', 1)),
                    $this->route('source'),
                    $this->input('financial_institution_id'),
                    $this->input('lc_facility_id'),
                    $this->input('lc_type'),
                    $this->currentIssuanceId() ?: null
                ),
            ],
        ];
    }

    /**
     * * في الإنشاء بيرجع 0 (يعني ما تستثنيش أي سجل) ، وفي التعديل بيرجع
     * * id السجل الحالي عشان ما يعتبرش نفسه تكرار
     */
    protected function currentIssuanceId(): int
    {
        $letterOfCreditIssuance = $this->route('letterOfCreditIssuance');

        return $letterOfCreditIssuance instanceof LetterOfCreditIssuance ? $letterOfCreditIssuance->id : 0;
    }

    /**
     * Human names for the validation messages.
     *
     * Without these Laravel builds messages from the raw column name —
     * "The lc amount field is required", "The financial institution id
     * field is required" — which is what the form's error summary shows
     * the user verbatim. The labels here match the form's own field
     * captions so the message names the control the person is looking at.
     */
    public function attributes(): array
    {
        return [
            'transaction_name' => __('Transaction Name'),
            'financial_institution_id' => __('Bank'),
            'lc_facility_id' => __('LC Facility'),
            'lc_type' => __('LC Type'),
            'lc_code' => __('LC Code'),
            'lc_amount' => __('LC Amount'),
            'lc_currency' => __('LC Currency'),
            'lc_cash_cover_currency' => __('Cash Cover Currency'),
            'exchange_rate' => __('Exchange Rate'),
            'issuance_date' => __('Issuance Date'),
            'due_date' => __('Due Date'),
            'lc_duration_days' => __('LC Duration (Days)'),
            'partner_id' => __('Beneficiary Name'),
            'contract_id' => __('Contract Reference'),
            'new_purchase_order_number' => __('New PO'),
            'purchase_order_id' => __('Purchase Order'),
            'lc_fees_and_commission_account_id' => __('LC Fees & Commission Account'),
            'cash_cover_deducted_from_account_type' => __('Cash Cover Account Type'),
            'cash_cover_deducted_from_account_id' => __('Cash Cover Account'),
            'cash_cover_rate' => __('Cash Cover Rate'),
            'lc_commission_rate' => __('LC Commission Rate'),
            'issuance_fees' => __('Issuance Fees'),
            'min_lc_commission_fees' => __('Min LC Commission Fees'),
        ];
    }
}
