<?php

namespace App\Models;

use App\Traits\Models\HandlesFactoringBankDebit;
use App\Traits\Models\HandlesFactoringStatement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $recourse_type
 * @property string $factoring_date
 * @property int $factoring_company_id
 * @property int $factoring_contract_id
 * @property int $customer_id
 * @property int $customer_invoice_id
 * @property string $invoice_currency
 * @property string $invoice_amount
 * @property string $factoring_percentage
 * @property string $factoring_amount
 * @property string $contract_interest_rate
 * @property int $diff_in_days
 * @property string $factoring_interest_amount
 * @property string $other_charges
 * @property string $received_amount
 * @property int $financial_institution_id
 * @property int $account_type_id
 * @property string $account_number
 * @property int|null $settlement_id
 */
class FactoringTransaction extends Model
{
    use HandlesFactoringBankDebit, HandlesFactoringStatement;

    public const WITHOUT_RECOURSE = 'without_recourse';

    public const WITH_RECOURSE = 'with_recourse';

    protected $guarded = ['id'];

    public static function calculateAmounts(
        float $invoiceAmount,
        float $factoringPercentage,
        float $borrowingRate,
        float $marginRate,
        float $otherCharges,
        string $factoringDate,
        string $invoiceDueDate
    ): array {
        $factoringAmount = ($invoiceAmount * $factoringPercentage) / 100;
        $contractInterestRate = $borrowingRate + $marginRate;
        $dueDate = Carbon::parse($invoiceDueDate)->startOfDay();
        $factorDate = Carbon::parse($factoringDate)->startOfDay();
        /**
         * ⚠️ REAL BUG FIXED HERE (same Carbon 3 sign-bug class already
         * found and fixed on TimeOfDeposit::calculateInterestAmount()
         * and Cheque::calculateChequeExpectedCollectionDate()).
         *
         * $dueDate (the invoice's due date, almost always LATER —
         * factoring happens before an invoice is due, that's the
         * whole point of factoring) was the base, $factorDate (the
         * earlier date) was the argument. Under Carbon 2 this always
         * returned a positive day-count regardless of order; Carbon 3
         * (shipped with this project's Laravel 12) made it signed by
         * default, so this returned a NEGATIVE day-count in the
         * normal case, not an edge case. The stored 'diff_in_days'
         * column on every factoring transaction was affected (shown
         * back to the user on the edit screen) — traced and confirmed
         * this does NOT affect the actual money fields saved
         * (factoring_interest_amount / received_amount come from the
         * frontend's own submitted values, cross-checked by a separate
         * sum-consistency rule, not from this calculation). Fixed by
         * forcing $absolute = true.
         */
        $diffInDays = $dueDate->diffInDays($factorDate, true);
        $factoringInterestAmount = (($factoringAmount * $contractInterestRate / 100) / 360) * $diffInDays;
        $receivedAmount = $factoringAmount - $factoringInterestAmount - $otherCharges;

        return [
            'factoring_amount' => round($factoringAmount, 2),
            'contract_interest_rate' => round($contractInterestRate, 4),
            'diff_in_days' => $diffInDays,
            'factoring_interest_amount' => round($factoringInterestAmount, 2),
            'received_amount' => round($receivedAmount, 2),
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function factoringCompany(): BelongsTo
    {
        return $this->belongsTo(FactoringCompany::class);
    }

    public function factoringContract(): BelongsTo
    {
        return $this->belongsTo(FactoringContract::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function financialInstitution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function getFactoringDateFormatted(): string
    {
        return $this->factoring_date
            ? Carbon::make($this->factoring_date)->format('m-d-Y')
            : '';
    }

    public static function getStatementComment(string $factoringCompanyName): string
    {
        return __('Settled Through Factoring Without Recourse [:company]', [
            'company' => $factoringCompanyName,
        ]);
    }

    public function deleteRelations(): void
    {
        $this->deleteBankDebitStatements();
        $this->deleteDifferenceReceivedBankStatements();
        $this->deleteCollectionBankStatements();
        $this->deleteRejectionBankStatements();
        $this->deleteFactoringStatements();
        if ($this->settlement) {
            $this->settlement->delete();
        }
    }

    public static function blockedInvoiceIdsForMoneyReceived(int $companyId)
    {
        return static::query()
            ->where('company_id', $companyId)
            ->where('recourse_type', self::WITH_RECOURSE)
            ->where('is_collected', false)
            ->where('is_rejected', false)
            ->pluck('customer_invoice_id');
    }

    public function isSettled(): bool
    {
        return (bool) $this->is_settled;
    }

    public function isDifferenceReceived(): bool
    {
        return (bool) $this->is_difference_received;
    }

    public function getDifferenceAmount(): float
    {
        return round((float) $this->factoring_amount - (float) $this->received_amount, 2);
    }

    public function getCollectionDifferenceAmount(): float
    {
        return $this->getDifferenceAmount();
    }

    public function isCollected(): bool
    {
        return (bool) $this->is_collected;
    }

    public function isRejected(): bool
    {
        return (bool) $this->is_rejected;
    }

    public function isPendingWithRecourse(): bool
    {
        return $this->recourse_type === self::WITH_RECOURSE
            && !$this->isCollected()
            && !$this->isRejected();
    }
}
