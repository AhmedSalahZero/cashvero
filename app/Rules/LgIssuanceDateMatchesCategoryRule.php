<?php

namespace App\Rules;

use App\Models\LetterOfGuaranteeIssuance;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * The issuance date has to sit on the right side of the company's
 * Opening Balance Date, and which side depends on the Issuance Type.
 *
 *   Opening Balance — the LG was already outstanding when the company
 *       started on CashVero, so it must have been issued BEFORE the
 *       opening balance date. One dated on or after it is not an
 *       opening position at all; it is a new issuance.
 *
 *   New Issuance — issued while the company has been on the system, so
 *       on or after the opening balance date. One dated earlier belongs
 *       to the opening balance instead.
 *
 * ⚠️ ImplicitRule: Laravel skips a non-implicit rule when the value is
 * an empty string, and the LG forms post empty selects as blanks. The
 * rule has to run on an empty date to say the date is required at all.
 */
class LgIssuanceDateMatchesCategoryRule implements ImplicitRule
{
    private string $failure = '';

    public function __construct(
        private ?string $categoryName,
        private ?string $companyOpeningBalanceDate,
    ) {}

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        // Nothing to compare against — the company has no opening
        // balance date set, so this rule has no opinion. `required`
        // elsewhere still covers a missing date.
        if (blank($this->companyOpeningBalanceDate) || blank($value) || $value === 'null') {
            return true;
        }

        $issuanceDate = $this->asDate($value);
        $openingDate = $this->asDate($this->companyOpeningBalanceDate);

        if (! $issuanceDate || ! $openingDate) {
            return true;
        }

        if ($this->categoryName === LetterOfGuaranteeIssuance::OPENING_BALANCE) {
            if ($issuanceDate >= $openingDate) {
                $this->failure = __(
                    'An Opening Balance LG must be issued before the company\'s Opening Balance Date (:date).',
                    ['date' => Carbon::make($openingDate)->format('d/m/Y')]
                );

                return false;
            }

            return true;
        }

        if ($this->categoryName === LetterOfGuaranteeIssuance::NEW_ISSUANCE) {
            if ($issuanceDate < $openingDate) {
                $this->failure = __(
                    'A New Issuance LG must be issued on or after the company\'s Opening Balance Date (:date).',
                    ['date' => Carbon::make($openingDate)->format('d/m/Y')]
                );

                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->failure;
    }

    /**
     * The forms post d/m/Y from the date picker; imports and API
     * clients post Y-m-d. Compared as Y-m-d strings so the two never
     * disagree about which is earlier.
     */
    private function asDate($value): ?string
    {
        try {
            $normalised = \App\Helpers\HDate::formatDateFromDatePicker(trim((string) $value));

            return Carbon::make($normalised)?->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
