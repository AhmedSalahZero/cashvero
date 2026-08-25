<?php

namespace App\Support\LetterOfGuarantee;

use App\Models\CurrentAccountBankStatement;
use App\Models\LetterOfGuaranteeCashCoverStatement;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LgRenewalDateHistory;

/**
 * The terms a bank is allowed to change when it renews an LG.
 *
 * A renewal was only ever a new expiry date plus a renewal fee. In
 * reality the bank re-prices the guarantee at renewal: it can ask for
 * a different cash cover (10,000 becomes 20,000) and a different
 * commission. Both take effect for the NEW term only — everything
 * already posted for the terms that came before stays exactly as it
 * was.
 *
 * Two rules make the numbers add up:
 *
 *   1. The issuance row (letter_of_guarantee_issuances) always holds
 *      the CURRENT terms. That is what cancellation refunds, the
 *      dashboards and the edit form all read, so they follow the
 *      renewal without any of them having to know renewals exist.
 *
 *   2. Only the DIFFERENCE is posted, dated at the start of the new
 *      term. Cash cover is a running balance, not a per-period
 *      charge — the original 10,000 is still blocked, so raising the
 *      cover to 20,000 moves 10,000 more, not 20,000 more. Lowering
 *      it refunds the difference the same way.
 *
 * Every renewal row also keeps the terms that were in force BEFORE it
 * (previous_*), so editing or deleting that row puts the issuance back
 * exactly where it was instead of guessing.
 *
 * A NULL on the renewal row means "this renewal did not touch that
 * term" — which is what every renewal recorded before this feature
 * existed looks like, and they keep behaving exactly as they did.
 *
 * @see \App\Http\Controllers\LetterOfGuaranteeIssuanceRenewalDateController
 */
class LgRenewalTerms
{
    /**
     * Marks the cash-cover statement row a renewal posts, to tell it
     * apart from the 'debit-lg-amount' row the original issuance
     * posts and the for-cancellation row.
     */
    public const CASH_COVER_TYPE = 'renewal-cash-cover';

    /** The three term fields a renewal may carry. */
    public const FIELDS = ['cash_cover_amount', 'lg_commission_amount', 'min_lg_commission_fees'];

    /**
     * Normalizes what the renewal form submits.
     *
     * A field left empty is NOT zero — it means "the bank did not
     * change this", so it stays NULL and the issuance keeps whatever
     * it already had. Amounts arrive display-formatted ("20,000"),
     * same as the renewal fee does.
     *
     * @return array{cash_cover_amount: ?float, lg_commission_amount: ?float, min_lg_commission_fees: ?float}
     */
    public static function fromInput(array $input): array
    {
        $terms = [];
        foreach (self::FIELDS as $field) {
            $terms[$field] = self::amount($input[$field] ?? null);
        }

        return $terms;
    }

    protected static function amount($value): ?float
    {
        if (is_null($value) || $value === '' || $value === 'null') {
            return null;
        }

        return (float) number_unformat($value);
    }

    /**
     * Applies a renewal's new terms to the issuance and posts the cash
     * cover difference.
     *
     * MUST run before the new term's commission rows are posted — they
     * read the commission straight off the issuance, so the issuance
     * has to already be carrying the new one.
     *
     * $effectiveDate is the START of the new term (the expiry the
     * renewal is extending), which is the same date the renewal fee
     * and the new term's first commission are posted on.
     */
    public static function apply(
        LgRenewalDateHistory $history,
        LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance,
        array $terms,
        string $effectiveDate
    ): void {
        $historyChanges = [];
        $issuanceChanges = [];

        $previousCashCoverAmount = (float) $letterOfGuaranteeIssuance->getCashCoverAmount();
        $previousCashCoverRate = (float) $letterOfGuaranteeIssuance->getCashCoverRate();

        if (! is_null($terms['cash_cover_amount'])) {
            $cashCoverAmount = (float) $terms['cash_cover_amount'];
            $cashCoverRate = self::rateFor($letterOfGuaranteeIssuance, $cashCoverAmount, $previousCashCoverRate);

            $historyChanges['cash_cover_amount'] = $cashCoverAmount;
            $historyChanges['cash_cover_rate'] = $cashCoverRate;
            $historyChanges['previous_cash_cover_amount'] = $previousCashCoverAmount;
            $historyChanges['previous_cash_cover_rate'] = $previousCashCoverRate;

            $issuanceChanges['cash_cover_amount'] = $cashCoverAmount;
            $issuanceChanges['cash_cover_rate'] = $cashCoverRate;
        }

        if (! is_null($terms['lg_commission_amount'])) {
            $historyChanges['lg_commission_amount'] = (float) $terms['lg_commission_amount'];
            $historyChanges['previous_lg_commission_amount'] = (float) $letterOfGuaranteeIssuance->getLgCommissionAmount();
            $issuanceChanges['lg_commission_amount'] = (float) $terms['lg_commission_amount'];
        }

        if (! is_null($terms['min_lg_commission_fees'])) {
            $historyChanges['min_lg_commission_fees'] = (float) $terms['min_lg_commission_fees'];
            $historyChanges['previous_min_lg_commission_fees'] = (float) $letterOfGuaranteeIssuance->getMinLgCommissionFees();
            $issuanceChanges['min_lg_commission_fees'] = (float) $terms['min_lg_commission_fees'];
        }

        if ($historyChanges) {
            $history->update($historyChanges);
        }

        if ($issuanceChanges) {
            $letterOfGuaranteeIssuance->update($issuanceChanges);
        }

        if (isset($issuanceChanges['cash_cover_amount'])) {
            self::postCashCoverDifference(
                $history,
                $letterOfGuaranteeIssuance,
                $previousCashCoverAmount,
                $issuanceChanges['cash_cover_amount'],
                $effectiveDate
            );
        }
    }

    /**
     * Undoes apply(): the issuance goes back to the terms that were in
     * force before this renewal, and the cash cover difference this
     * renewal posted is removed.
     *
     * Safe to call on a renewal that changed no terms (every one
     * recorded before this feature existed) — it finds nothing to
     * revert and nothing to delete.
     */
    public static function revert(LgRenewalDateHistory $history, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance): void
    {
        self::removeCashCoverDifference($history, $letterOfGuaranteeIssuance);

        $issuanceChanges = [];

        if (! is_null($history->cash_cover_amount)) {
            $issuanceChanges['cash_cover_amount'] = (float) $history->previous_cash_cover_amount;
            $issuanceChanges['cash_cover_rate'] = (float) $history->previous_cash_cover_rate;
        }

        if (! is_null($history->lg_commission_amount)) {
            $issuanceChanges['lg_commission_amount'] = (float) $history->previous_lg_commission_amount;
        }

        if (! is_null($history->min_lg_commission_fees)) {
            $issuanceChanges['min_lg_commission_fees'] = (float) $history->previous_min_lg_commission_fees;
        }

        if ($issuanceChanges) {
            $letterOfGuaranteeIssuance->update($issuanceChanges);
        }

        $history->update([
            'cash_cover_amount' => null,
            'cash_cover_rate' => null,
            'lg_commission_amount' => null,
            'min_lg_commission_fees' => null,
            'previous_cash_cover_amount' => null,
            'previous_cash_cover_rate' => null,
            'previous_lg_commission_amount' => null,
            'previous_min_lg_commission_fees' => null,
        ]);
    }

    /**
     * The cover rate has to follow the amount, otherwise an Advance
     * Payment LG refunds the wrong money on cancellation —
     * getCashCoverCancellationAmount() prices that type off the RATE,
     * not the stored amount. When there is no LG amount to divide by
     * there is nothing sensible to recompute, so the old rate stands.
     */
    protected static function rateFor(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, float $cashCoverAmount, float $previousRate): float
    {
        $lgAmount = (float) $letterOfGuaranteeIssuance->getLgAmount();

        return $lgAmount > 0 ? round($cashCoverAmount / $lgAmount * 100, 2) : $previousRate;
    }

    /**
     * Posts the difference in the same two places the original
     * issuance posts cash cover: the LG cash-cover ledger, and the
     * current account the cover is deducted from.
     *
     * A cover that goes UP debits the LG ledger and takes the extra
     * out of the current account; a cover that goes DOWN credits the
     * ledger and gives the difference back — the mirror image of what
     * cancellation does.
     *
     * The two cases the original issuance skips are skipped here for
     * the same reasons: an opening-balance LG's cover was never posted
     * (it is already inside the opening balance), and a cover held
     * against a CD/TD never moves through a current account at all.
     */
    protected static function postCashCoverDifference(
        LgRenewalDateHistory $history,
        LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance,
        float $previousAmount,
        float $newAmount,
        string $effectiveDate
    ): void {
        $difference = round($newAmount - $previousAmount, 2);

        if ($difference == 0.0) {
            return;
        }

        if ($letterOfGuaranteeIssuance->isOpeningBalance() || $letterOfGuaranteeIssuance->isCdOrTd()) {
            return;
        }

        $isIncrease = $difference > 0;
        $movement = abs($difference);

        $letterOfGuaranteeIssuance->handleLetterOfGuaranteeCashCoverStatement(
            $letterOfGuaranteeIssuance->getFinancialInstitutionBankId(),
            $letterOfGuaranteeIssuance->getSource(),
            $letterOfGuaranteeIssuance->lg_facility_id,
            $letterOfGuaranteeIssuance->getLgType(),
            $letterOfGuaranteeIssuance->company_id,
            $effectiveDate,
            0,
            $isIncrease ? $movement : 0,
            $isIncrease ? 0 : $movement,
            (string) $letterOfGuaranteeIssuance->getLgCurrency(),
            0,
            self::CASH_COVER_TYPE,
            $history->id
        );

        $financialInstitutionAccountId = (int) $letterOfGuaranteeIssuance->getCashCoverDeductedFromAccountId();

        if (! $financialInstitutionAccountId) {
            return;
        }

        CurrentAccountBankStatement::create([
            'financial_institution_account_id' => $financialInstitutionAccountId,
            'company_id' => $letterOfGuaranteeIssuance->company_id,
            'letter_of_guarantee_issuance_id' => $letterOfGuaranteeIssuance->id,
            'lg_renewal_date_history_id' => $history->id,
            'is_renewal_cash_cover' => 1,
            'is_active' => 1,
            'is_credit' => $isIncrease ? 1 : 0,
            'is_debit' => $isIncrease ? 0 : 1,
            'credit' => $isIncrease ? $movement : 0,
            'debit' => $isIncrease ? 0 : $movement,
            'date' => $effectiveDate,
            'comment_en' => self::comment('en', $letterOfGuaranteeIssuance, $isIncrease),
            'comment_ar' => self::comment('ar', $letterOfGuaranteeIssuance, $isIncrease),
        ]);
    }

    /**
     * Both rows this renewal posted go away together — the ledger row
     * and the current-account row. Anything the renewal did NOT post
     * (the original issuance's cover, other renewals') is untouched:
     * only rows carrying this renewal's id are matched.
     *
     * withoutGlobalScope('only_active') because a renewal dated in the
     * future is posted inactive, and an inactive row still has to be
     * removable.
     */
    protected static function removeCashCoverDifference(LgRenewalDateHistory $history, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance): void
    {
        LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement(
            $letterOfGuaranteeIssuance->letterOfGuaranteeCashCoverStatements()
                ->where('lg_renewal_date_history_id', $history->id)
                ->orderBy('full_date', 'desc')
                ->get()
        );

        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement(
            CurrentAccountBankStatement::withoutGlobalScope('only_active')
                ->where('letter_of_guarantee_issuance_id', $letterOfGuaranteeIssuance->id)
                ->where('lg_renewal_date_history_id', $history->id)
                ->where('is_renewal_cash_cover', 1)
                ->orderBy('full_date', 'desc')
                ->get()
        );
    }

    protected static function comment(string $locale, LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, bool $isIncrease): string
    {
        $key = $isIncrease
            ? 'Cash Cover Increase On Renewal [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]'
            : 'Cash Cover Decrease On Renewal [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]';

        return __($key, [
            'lgType' => __((string) $letterOfGuaranteeIssuance->getLgType(), [], $locale),
            'customerName' => $letterOfGuaranteeIssuance->getBeneficiaryName(),
            'transactionName' => (string) $letterOfGuaranteeIssuance->getTransactionName(),
        ], $locale);
    }
}
