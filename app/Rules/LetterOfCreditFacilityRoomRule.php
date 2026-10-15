<?php

namespace App\Rules;

use App\Http\Controllers\LetterOfCreditFacilityController;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * "You can never issue more against a facility than the facility has
 * room for."
 *
 * Reuses LetterOfCreditFacilityController::updateOutstandingBalanceAndLimits()
 * directly (same controller-instantiation-inside-a-Rule pattern already
 * established by MediumTermLoanRoomRule elsewhere in this app, with the
 * same caveat: this only works because the current global Request()
 * carries what that controller method reads internally) rather than
 * duplicating its calculation — one source of truth for "how much room
 * is left," used identically by the live on-screen figure and this
 * server-side guard.
 *
 * IMPORTANT: letter_of_credit_statements — and therefore total_room —
 * is tracked in the company's MAIN functional currency (see
 * LetterOfCreditIssuanceController::storeWithinTransaction()'s
 * $lcAmountInMainCurrency / $lcCashCoverOrCdOrTdCurrency, deliberately
 * untouched, correct, real money-tracking), NOT the LC's own currency
 * or the facility's configured currency. The amount compared against
 * room here must be the LC amount converted the exact same way
 * (lc_amount × exchange_rate), or this would reject/accept on
 * mismatched units the moment an LC's currency differs from the
 * company's main currency.
 *
 * Scoped to LC_FACILITY source only — CD/TD/Hundred-Percentage-Cash-
 * Cover sources don't draw against a shared facility limit the same
 * way, so there's no "room" to exceed.
 */
class LetterOfCreditFacilityRoomRule implements ImplicitRule
{
    public function __construct(
        protected $company,
        protected $lcAmountInMainCurrency,
        protected ?string $source,
        protected $financialInstitutionId,
        protected $lcFacilityId,
        protected ?string $lcType,
        protected $currentIssuanceId = null
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if ($this->source !== LetterOfCreditIssuance::LC_FACILITY) {
            return true;
        }
        if (! $this->financialInstitutionId || ! $this->lcFacilityId || ! $this->lcType) {
            return true;
        }

        $facility = LetterOfCreditFacility::find($this->lcFacilityId);
        if (! $facility) {
            return true;
        }

        $request = Request();
        $request->merge([
            'financialInstitutionId' => $this->financialInstitutionId,
            'lcType' => $this->lcType,
            'source' => LetterOfCreditIssuance::LC_FACILITY,
            'letterOfCreditFacilityId' => $this->lcFacilityId,
            'lcIssuanceId' => $this->currentIssuanceId,
        ]);
        $response = (new LetterOfCreditFacilityController)->updateOutstandingBalanceAndLimits($request, $this->company);
        if (! $response) {
            return true;
        }
        $data = $response->getData(true);
        if (! isset($data['total_room'])) {
            return true;
        }

        // total_room comes back number_format()'d for display ("6,000,000") —
        // same reason every other Rule reusing a display-formatted lookup
        // value in this app strips it first.
        $room = (float) number_unformat($data['total_room']);

        /**
         * The lookup above already excludes THIS issuance's own current
         * amount from the outstanding total when lcIssuanceId is passed
         * (see updateOutstandingBalanceAndLimits()'s own
         * $lcAmountInMainCurrency subtraction) — so re-saving an unchanged
         * issuance in edit mode compares against room that already has
         * its own amount added back, the same compensation pattern every
         * other balance/room Rule in this app follows.
         */
        return $room >= (float) number_unformat($this->lcAmountInMainCurrency);
    }

    public function message(): string
    {
        return __('This exceeds what is left of the LC Facility. Reduce the amount or pick another facility.');
    }
}
