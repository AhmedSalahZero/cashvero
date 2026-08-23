<?php

namespace App\Support\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Models\Partner;

/**
 * When an LG issuance must be tied to a contract.
 *
 * Two things excuse it, and they are excused for different reasons:
 *
 *   Bid Bond — a tender guarantee is issued to WIN the work. The
 *     contract does not exist yet; it gets signed if the bid succeeds.
 *     Requiring one would make the type impossible to use.
 *
 *   An "other partner" beneficiary — an authority, a court, a landlord.
 *     There is no customer contract behind it to point at, and the
 *     Contracts screen only holds Customer and Supplier contracts.
 *
 * A partner flagged BOTH customer and other can go either way, so the
 * contract stays optional there: the user may attach one, and nothing
 * forces it. Only a pure customer on a non-Bid-Bond type must supply
 * one.
 *
 * Read by the store/update validation and published to the issuance
 * form, so the field the user sees and the rule the server applies can
 * never disagree.
 */
class LgContractRequirement
{
    /**
     * Is a contract required for this LG type + beneficiary?
     */
    public static function isRequired(?string $lgType, ?int $partnerId): bool
    {
        if ($lgType === LgTypes::BID_BOND) {
            return false;
        }

        return ! self::partnerIsOther($partnerId);
    }

    /**
     * A beneficiary that is flagged "other partner" — whether or not it
     * is also a customer.
     */
    public static function partnerIsOther(?int $partnerId): bool
    {
        if (! $partnerId) {
            return false;
        }

        return Partner::query()
            ->whereKey($partnerId)
            ->where('is_other_partner', 1)
            ->exists();
    }

    /**
     * The ids the form may leave the contract empty for, out of the
     * beneficiaries it is showing.
     *
     * Sent to the page so it can drop the asterisk the moment such a
     * beneficiary is picked, without another round trip.
     *
     * @param  list<int>  $partnerIds
     * @return list<int>
     */
    public static function partnerIdsWithoutContractRequirement(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        return Partner::query()
            ->whereIn('id', $partnerIds)
            ->where('is_other_partner', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
