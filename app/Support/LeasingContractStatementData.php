<?php

namespace App\Support;

use App\Models\LeasingContract;
use App\Models\LeasingContractBankStatement;
use Carbon\Carbon;

/**
 * The props behind the Leasing Contract Statement screen
 * (resources/js/Pages/LeasingContract/Statement.vue).
 *
 * Shared because that screen is reached two ways — the 📄 button on a
 * leasing company's contract list, and the Statements sidebar entry
 * that lets the user pick any contract — and the two must never drift
 * into showing different numbers for the same contract.
 *
 * ── The date range ────────────────────────────────────────────────
 * Optional, and only the sidebar route supplies it (the 📄 button
 * shows the contract's whole life). When present it restricts WHICH
 * rows are listed, never how they are calculated:
 *
 *   - Ledger rows are filtered to the period. Their running figures
 *     are the trigger-written ones, so a row inside the period still
 *     opens at whatever the balance genuinely was before it — the
 *     period never restarts the balance at zero.
 *   - The facility figures (drawn / available room) are read AS OF the
 *     end date, so they answer "where did this contract stand at the
 *     end of the period", not "where does it stand today".
 */
class LeasingContractStatementData
{
    /**
     * @return array{contract: array, kpis: array, ledger: array, period: array}
     */
    public static function for(LeasingContract $contract, ?string $startDate = null, ?string $endDate = null): array
    {
        $ledger = $contract->bankStatements()
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
            ->orderByRaw('full_date asc , id asc')
            ->get();

        // As of the end of the period, not as of today.
        $room = $contract->getAvailableRoomAt($endDate);

        /**
         * The statement KPIs, computed the same way Bank Statement's are:
         * over the WHOLE period, not the page in front of you.
         *
         * The beginning balance is the balance this contract carried INTO
         * the period — the closing balance of the last movement before it,
         * or zero when the period opens before the contract was ever
         * touched. It is what makes the period a window rather than a
         * reset: the first row inside it continues from here.
         */
        $beginningBalance = 0.0;

        if ($startDate) {
            $previous = $contract->bankStatements()
                ->where('date', '<', $startDate)
                ->orderByRaw('full_date desc , id desc')
                ->first();

            $beginningBalance = $previous ? (float) $previous->end_balance : 0.0;
        }

        // No movement inside the period means it closed where it opened.
        $endingBalance = $ledger->isNotEmpty()
            ? (float) $ledger->last()->end_balance
            : $beginningBalance;

        return [
            'contract' => [
                'id' => $contract->id,
                'name' => $contract->getName(),
                'currency_formatted' => $contract->getCurrencyFormatted(),
                'limit' => (float) $contract->getLimit(),
                'start_date_formatted' => $contract->getStartDateFormatted(),
                'end_date_formatted' => $contract->getEndDateFormatted(),
                'interest_rate_formatted' => number_format((float) $contract->getInterestRate(), 2).' %',
                'has_drawdowns' => $ledger->isNotEmpty(),
                // Drawn is derived from room so it can never disagree with
                // the ledger's own trigger-computed figure.
                'available_room' => $room,
                'drawn' => (float) $contract->getLimit() - $room,
            ],
            'kpis' => [
                'beginningBalance' => $beginningBalance,
                'endingBalance' => $endingBalance,
                // Named from the ledger's own columns so the KPI and the
                // column beneath it can never mean different things:
                // credit = drawn, debit = principle repaid.
                'totalCredit' => (float) $ledger->sum('credit'),
                'totalDebit' => (float) $ledger->sum('debit'),
                'totalInterest' => (float) $ledger->sum('interest_amount'),
                'availableRoom' => $room,
                'transactionCount' => $ledger->count(),
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'start_formatted' => $startDate ? Carbon::make($startDate)->format('d-m-Y') : null,
                'end_formatted' => $endDate ? Carbon::make($endDate)->format('d-m-Y') : null,
            ],
            'ledger' => $ledger->map(fn (LeasingContractBankStatement $row) => [
                'id' => $row->id,
                'date_formatted' => $row->date ? Carbon::make($row->date)->format('d-m-Y') : __('N/A'),
                'type' => $row->type,
                'is_repayment' => $row->type === LeasingContractBankStatement::INSTALLMENT_REPAYMENT,
                'beginning_balance' => (float) $row->beginning_balance,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
                'interest_amount' => (float) $row->interest_amount,
                'end_balance' => (float) $row->end_balance,
                'room' => (float) $row->room,
                'comment' => $row->{'comment_'.app()->getLocale()} ?: $row->comment_en,
            ])->values(),
        ];
    }
}
