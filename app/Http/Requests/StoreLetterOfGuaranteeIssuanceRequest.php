<?php

namespace App\Http\Requests;

use App\Enums\LgTypes;
use App\Models\LetterOfGuaranteeIssuance;
use App\Rules\LgContractRequiredRule;
use App\Rules\LgIssuanceDateMatchesCategoryRule;
use App\Rules\LgTermAmountRule;
use App\Rules\LetterOfGuaranteeFacilityRoomRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLetterOfGuaranteeIssuanceRequest extends FormRequest
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
     * The company this issuance belongs to comes from the route, the
     * same way every other action on these forms resolves it.
     */
    private function companyOpeningBalanceDate(): ?string
    {
        $company = $this->route('company');

        return $company?->opening_balance_date;
    }

    /**
     * The beneficiary as an id, treating the form's "null" sentinel and
     * a blank value alike.
     */
    private function partnerIdFromRequest(): ?int
    {
        $partnerId = $this->get('partner_id');

        return ($partnerId === 'null' || blank($partnerId)) ? null : (int) $partnerId;
    }

    public function rules()
    {
        return [
			'transaction_name'=>['required'],
			'lg_fees_and_commission_account_id'=>'required',
			/**
			 * * تاريخ الاصدار لازم يقع على الناحية الصح من تاريخ الرصيد
			 * * الافتتاحي للشركة ، و الناحية دي بتتحدد بنوع الاصدار
			 * @see \App\Rules\LgIssuanceDateMatchesCategoryRule
			 */
			'issuance_date'=>[
				'required',
				new LgIssuanceDateMatchesCategoryRule(
					$this->get('category_name'),
					$this->companyOpeningBalanceDate()
				),
			],
			'lg_amount'=>['required','gt:0'],
			/**
			 * A draw against an LG Facility must not exceed what is
			 * left of that facility. Bound to source lg-facility only,
			 * from route('source') rather than the request itself —
			 * same pattern as lc_facility_room on LC issuance.
			 */
			'lg_facility_room' => [
				new LetterOfGuaranteeFacilityRoomRule(
					$this->route('company'),
					$this->input('lg_amount'),
					$this->route('source'),
					$this->input('financial_institution_id'),
					$this->input('lg_facility_id'),
					$this->input('lg_type'),
					$this->currentIssuanceId() ?: null
				),
			],
			'cash_cover_amount'=>[new LgTermAmountRule($this->get('category_name'),$this->get('cash_cover_deducted_from_account_type'),$this->get('lg_fees_and_commission_account_id'),$this->get('issuance_date'),$this->get('cash_cover_amount',0),$this->get('lg_commission_amount',0),$this->get('min_lg_commission_fees',0),$this->get('issuance_fees',0),$this->get('company_id'),$this->get('financial_institution_id'))],
			/**
			 * A contract is required unless the LG type or the
			 * beneficiary excuses it — see LgContractRequirement for
			 * which, and why. Previously only Bid Bond was excused, so
			 * a Final/Advance-Payment/Performance LG issued to an
			 * authority or a landlord could not be saved at all: there
			 * is no customer contract behind such a beneficiary to
			 * point at.
			 *
			 * NOTE: the form sends the literal string "null" for an
			 * empty select (see HasBasicStoreRequest::storeBasicForm),
			 * so a plain 'required' rule wouldn't catch it — this
			 * closure treats that sentinel the same as truly empty.
			 */
			'contract_id'=>[new LgContractRequiredRule($this->get('lg_type'), $this->partnerIdFromRequest())],
        ];
    }

    /**
     * On create this is 0 (exclude nothing). On edit it is the current
     * row's id so the room lookup can add that issuance's amount back.
     */
    protected function currentIssuanceId(): int
    {
        $letterOfGuaranteeIssuance = $this->route('letterOfGuaranteeIssuance');

        return $letterOfGuaranteeIssuance instanceof LetterOfGuaranteeIssuance ? $letterOfGuaranteeIssuance->id : 0;
    }
}
