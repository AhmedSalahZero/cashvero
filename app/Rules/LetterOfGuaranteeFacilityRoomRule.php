<?php

namespace App\Rules;

use App\Http\Controllers\LetterOfGuaranteeFacilityController;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * "You can never issue more against a facility than the facility has
 * room for."
 *
 * Reuses LetterOfGuaranteeFacilityController::updateOutstandingBalanceAndLimits()
 * directly (same controller-instantiation-inside-a-Rule pattern already
 * established by LetterOfCreditFacilityRoomRule, with the same caveat:
 * this only works because the current global Request() carries what
 * that controller method reads internally) rather than duplicating its
 * calculation — one source of truth for "how much room is left," used
 * identically by the live on-screen figure and this server-side guard.
 *
 * Unlike LC, letter_of_guarantee_statements — and therefore total_room —
 * are tracked in the facility's own currency, which is also the
 * issuance's lg_amount. No exchange-rate conversion belongs here.
 *
 * Scoped to LG_FACILITY source only — Against CD/TD and Hundred-
 * Percentage-Cash-Cover sources don't draw against a shared facility
 * limit the same way, so there's no "room" to exceed.
 */
class LetterOfGuaranteeFacilityRoomRule implements ImplicitRule
{
    public function __construct(
        protected $company,
        protected $lgAmount,
        protected ?string $source,
        protected $financialInstitutionId,
        protected $lgFacilityId,
        protected ?string $lgType,
        protected $currentIssuanceId = null
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if ($this->source !== LetterOfGuaranteeIssuance::LG_FACILITY) {
            return true;
        }
        if (! $this->financialInstitutionId || ! $this->lgFacilityId || ! $this->lgType) {
            return true;
        }

        $facility = LetterOfGuaranteeFacility::find($this->lgFacilityId);
        if (! $facility) {
            return true;
        }

        $request = Request();
        $request->merge([
            'financialInstitutionId' => $this->financialInstitutionId,
            'lgType' => $this->lgType,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'letterOfGuaranteeFacilityId' => $this->lgFacilityId,
            'lgIssuanceId' => $this->currentIssuanceId,
        ]);
        $response = (new LetterOfGuaranteeFacilityController)->updateOutstandingBalanceAndLimits($request, $this->company);
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
         * amount from the outstanding total when lgIssuanceId is passed
         * (see updateOutstandingBalanceAndLimits()'s own $lgAmount
         * subtraction) — so re-saving an unchanged issuance in edit
         * mode compares against room that already has its own amount
         * added back, the same compensation pattern every other
         * balance/room Rule in this app follows.
         */
        return $room >= (float) number_unformat($this->lgAmount);
    }

    public function message(): string
    {
        return __('This exceeds what is left of the LG Facility. Reduce the amount or pick another facility.');
    }
}
