<?php

namespace App\Models;

use App\OutstandingBreakdown;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\FactoringStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int $factoring_company_id
 * @property string|null $contract_start_date
 * @property string|null $contract_end_date
 * @property string $recourse_type
 * @property string|null $currency
 * @property string|null $limit
 * @property string|null $outstanding_balance
 * @property string|null $balance_date
 * @property float|null $borrowing_rate
 * @property float|null $margin_rate
 * @property float|null $interest_rate
 * @property float|null $min_interest_rate
 * @property float|null $highest_debt_balance_rate
 * @property float|null $admin_fees_rate
 * @property int|null $to_be_setteled_max_within_days
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\FactoringCompany|null $factoringCompany
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\OutstandingBreakdown> $outstandingBreakdowns
 */
class FactoringContract extends Model
{
    public const WITH_RECOURSE = 'with_recourse';

    public const WITHOUT_RECOURSE = 'without_recourse';

    protected $guarded = ['id'];

    public static function recourseTypes(): array
    {
        return [
            self::WITH_RECOURSE => __('With Recourse'),
            self::WITHOUT_RECOURSE => __('Without Recourse'),
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

    public function outstandingBreakdowns(): HasMany
    {
        return $this->hasMany(OutstandingBreakdown::class, 'model_id', 'id')
            ->where('model_type', self::class);
    }

    public function factoringStatements(): HasMany
    {
        return $this->hasMany(FactoringStatement::class);
    }

    public function storeLimitStatement(int $companyId): void
    {
        if (!$this->contract_start_date || $this->getLimit() <= 0) {
            return;
        }

        FactoringStatement::create([
            'company_id' => $companyId,
            'factoring_company_id' => $this->factoring_company_id,
            'factoring_contract_id' => $this->id,
            'factoring_transaction_id' => null,
            'entry_type' => FactoringStatement::TYPE_CONTRACT_LIMIT,
            'date' => $this->contract_start_date,
            'debit' => $this->getLimit(),
            'credit' => 0,
            'currency' => $this->getCurrency(),
            'comment_en' => __('Contract Limit'),
            'comment_ar' => __('Contract Limit', [], 'ar'),
            'created_by' => auth()->id(),
        ]);
    }

    public function getRemainingLimit(?int $exceptTransactionId = null): float
    {
        $disbursementQuery = $this->factoringStatements()
            ->where('entry_type', FactoringStatement::TYPE_FACTORING_DISBURSEMENT);

        $restoringQuery = $this->factoringStatements()
            ->whereIn('entry_type', [
                FactoringStatement::TYPE_FACTORING_SETTLEMENT,
                FactoringStatement::TYPE_FACTORING_REJECTION,
            ]);

        if ($exceptTransactionId) {
            $disbursementQuery->where('factoring_transaction_id', '!=', $exceptTransactionId);
            $restoringQuery->where('factoring_transaction_id', '!=', $exceptTransactionId);
        }

        $used = round(
            (float) $disbursementQuery->sum('credit') - (float) $restoringQuery->sum('debit'),
            2
        );

        return max(0, round($this->getLimit() - $used, 2));
    }

    public function syncLimitStatement(int $companyId): void
    {
        if (!$this->contract_start_date || $this->getLimit() <= 0) {
            $this->factoringStatements()
                ->where('entry_type', FactoringStatement::TYPE_CONTRACT_LIMIT)
                ->delete();

            return;
        }

        $statement = $this->factoringStatements()
            ->where('entry_type', FactoringStatement::TYPE_CONTRACT_LIMIT)
            ->first();

        if ($statement) {
            $statement->update([
                'debit' => $this->getLimit(),
                'credit' => 0,
                'currency' => $this->getCurrency(),
                'date' => $this->contract_start_date,
            ]);

            return;
        }

        $this->storeLimitStatement($companyId);
    }

    public function storeOutstandingBreakdown(Request $request, Company $company): void
    {
        $outstandingBalance = number_unformat($request->get('outstanding_balance', 0));
        // Defense-in-depth with StoreFactoringContractRequest's gte:0 —
        // never wipe breakdowns on a negative outstanding balance.
        if ($outstandingBalance < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'outstanding_balance' => [__('Outstanding balance cannot be negative.')],
            ]);
        }
        $this->outstandingBreakdowns()->delete();

        if ($outstandingBalance >= 0) {
            foreach ($request->get('outstanding_breakdowns', []) as $outstandingBreakdownArr) {
                if (empty($outstandingBreakdownArr['settlement_date'])) {
                    continue;
                }

                unset($outstandingBreakdownArr['id']);
                $outstandingBreakdownArr['company_id'] = $company->id;
                $outstandingBreakdownArr['model_type'] = self::class;
                $outstandingBreakdownArr['amount'] = number_unformat($outstandingBreakdownArr['amount']);
                $this->outstandingBreakdowns()->create($outstandingBreakdownArr);
            }
        }
    }

    public function deleteRelations(): void
    {
        $this->outstandingBreakdowns()->delete();
        $this->factoringStatements()->delete();
    }

    public function getContractStartDateFormatted(): string
    {
        return $this->contract_start_date
            ? Carbon::make($this->contract_start_date)->format('m-d-Y')
            : '';
    }

    public function getContractEndDateFormatted(): string
    {
        return $this->contract_end_date
            ? Carbon::make($this->contract_end_date)->format('m-d-Y')
            : '';
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getCurrencyFormatted(): string
    {
        return Str::upper((string) $this->getCurrency());
    }

    public function getLimit(): float
    {
        return (float) $this->limit;
    }

    public function getLimitFormatted(): string
    {
        return number_format($this->getLimit(), 2);
    }

    public function getRecourseTypeLabel(): string
    {
        return self::recourseTypes()[$this->recourse_type] ?? (string) $this->recourse_type;
    }

    public function getBorrowingRateFormatted(): string
    {
        return number_format((float) $this->borrowing_rate, 2);
    }

    public function getMarginRateFormatted(): string
    {
        return number_format((float) $this->margin_rate, 2);
    }

    public function getInterestRateFormatted(): string
    {
        return number_format((float) $this->interest_rate, 2);
    }

    public function scopeActiveOnDate($query, ?string $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');

        return $query
            ->where('contract_start_date', '<=', $date)
            ->where('contract_end_date', '>=', $date);
    }

    public function getContractInterestRate(): float
    {
        return (float) $this->borrowing_rate + (float) $this->margin_rate;
    }

    /**
     * Facility Renewal — Phase 7 (final facility type). Mirrors
     * CleanOverdraft's implementation exactly — same fields, same
     * validation rules, same reasoning throughout.
     */
    public function termsHistories(): HasMany
    {
        return $this->hasMany(FactoringContractTermsHistory::class, 'factoring_contract_id', 'id')->orderBy('effective_date');
    }

    public function getTermsAsOfDate(string $date): ?FactoringContractTermsHistory
    {
        return $this->termsHistories()
            ->where('effective_date', '<=', $date)
            ->reorder('effective_date', 'desc')
            ->orderByDesc('id')
            ->first();
    }

    public function getLatestTerms(): ?FactoringContractTermsHistory
    {
        return $this->termsHistories()->reorder('effective_date', 'desc')->orderByDesc('id')->first();
    }

    public function getCurrentChapterStartDateFormatted(): ?string
    {
        $latest = $this->getLatestTerms();
        $date = $latest?->effective_date ?: $this->contract_start_date;
        return $date ? Carbon::make($date)->format('d-m-Y') : null;
    }

    public function hasRenewals(): bool
    {
        return $this->termsHistories()->count() > 1;
    }

    /**
     * A "transaction" here means real money has moved against this
     * contract — a disbursement, settlement, or rejection entry.
     * Mirrors CleanOverdraft::hasAnyTransactions()'s "only rows where
     * money actually moved count" rule; the automatic Contract Limit
     * marker row doesn't count.
     */
    public function hasAnyTransactions(): bool
    {
        return $this->factoringStatements()
            ->whereIn('entry_type', [
                FactoringStatement::TYPE_FACTORING_DISBURSEMENT,
                FactoringStatement::TYPE_FACTORING_SETTLEMENT,
                FactoringStatement::TYPE_FACTORING_REJECTION,
            ])
            ->exists();
    }

    public function createOriginalTermsHistory(): FactoringContractTermsHistory
    {
        return $this->termsHistories()->create([
            'company_id' => $this->company_id,
            'effective_date' => $this->contract_start_date,
            'limit' => $this->limit,
            'borrowing_rate' => $this->borrowing_rate,
            'margin_rate' => $this->margin_rate,
            'interest_rate' => $this->interest_rate,
            'min_interest_rate' => $this->min_interest_rate,
            'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
            'admin_fees_rate' => $this->admin_fees_rate,
            'to_be_setteled_max_within_days' => $this->to_be_setteled_max_within_days,
            'contract_end_date' => $this->contract_end_date,
            'is_original' => true,
            'notes' => 'Original facility terms.',
        ]);
    }

    /**
     * Records a renewal: a new dated row of terms. Anything left null
     * simply carries forward the previous chapter's value — the user
     * only enters what actually changed. Deliberately does NOT create
     * a new factoring_contracts row — this contract keeps its
     * identity, same reasoning as every other facility type.
     */
    public function renew(string $effectiveDate, array $newTerms, int $userId): FactoringContractTermsHistory
    {
        if ($this->termsHistories()->count() === 0) {
            $this->createOriginalTermsHistory();
        }

        $previous = $this->getLatestTerms();

        if ($previous && $effectiveDate <= $previous->effective_date) {
            throw new \InvalidArgumentException(
                __('A renewal date must be after the facility\'s most recent renewal date (:date).', ['date' => $previous->getEffectiveDateFormatted()])
            );
        }

        $currentEndDate = $previous?->contract_end_date ?: $this->contract_end_date;
        if ($currentEndDate && $effectiveDate <= $currentEndDate) {
            throw new \InvalidArgumentException(
                __('A renewal date must be after the current contract end date (:date).', ['date' => Carbon::make($currentEndDate)->format('d-m-Y')])
            );
        }

        if (empty($newTerms['contract_end_date'])) {
            throw new \InvalidArgumentException(
                __('A renewal must include a new contract end date — the previous end date can no longer apply once the renewal starts after it.')
            );
        }

        $borrowingRate = $newTerms['borrowing_rate'] ?? $previous?->borrowing_rate ?? $this->borrowing_rate;
        $marginRate = $newTerms['margin_rate'] ?? $previous?->margin_rate ?? $this->margin_rate;

        $termsRow = $this->termsHistories()->create([
            'company_id' => $this->company_id,
            'effective_date' => $effectiveDate,
            'limit' => $newTerms['limit'] ?? $previous?->limit ?? $this->limit,
            'borrowing_rate' => $borrowingRate,
            'margin_rate' => $marginRate,
            // Interest Rate is always Borrowing Rate + Margin Rate,
            // same rule as getContractInterestRate() already encodes
            // for the live facility — never independently typed.
            'interest_rate' => (float) $borrowingRate + (float) $marginRate,
            'min_interest_rate' => $newTerms['min_interest_rate'] ?? $previous?->min_interest_rate ?? $this->min_interest_rate,
            'highest_debt_balance_rate' => $newTerms['highest_debt_balance_rate'] ?? $previous?->highest_debt_balance_rate ?? $this->highest_debt_balance_rate,
            'admin_fees_rate' => $newTerms['admin_fees_rate'] ?? $previous?->admin_fees_rate ?? $this->admin_fees_rate,
            'to_be_setteled_max_within_days' => $newTerms['to_be_setteled_max_within_days'] ?? $previous?->to_be_setteled_max_within_days ?? $this->to_be_setteled_max_within_days,
            'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
            'notes' => $newTerms['notes'] ?? null,
            'is_original' => false,
            'created_by' => $userId,
        ]);

        $this->update([
            'limit' => $termsRow->limit,
            'borrowing_rate' => $termsRow->borrowing_rate,
            'margin_rate' => $termsRow->margin_rate,
            'interest_rate' => $termsRow->interest_rate,
            'min_interest_rate' => $termsRow->min_interest_rate,
            'highest_debt_balance_rate' => $termsRow->highest_debt_balance_rate,
            'admin_fees_rate' => $termsRow->admin_fees_rate,
            'to_be_setteled_max_within_days' => $termsRow->to_be_setteled_max_within_days,
            'contract_end_date' => $termsRow->contract_end_date,
        ]);

        $this->syncLimitStatement($this->company_id);

        return $termsRow;
    }

    /**
     * Deletes the most recent renewal only. Blocked if any real
     * transaction is dated on/after that renewal's effective date,
     * since removing it would silently change what terms those
     * transactions are judged against.
     */
    public function deleteLatestRenewal(): void
    {
        $latest = $this->getLatestTerms();

        if (!$latest || $latest->is_original) {
            throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
        }

        $blockingTransactionsExist = $this->factoringStatements()
            ->whereIn('entry_type', [
                FactoringStatement::TYPE_FACTORING_DISBURSEMENT,
                FactoringStatement::TYPE_FACTORING_SETTLEMENT,
                FactoringStatement::TYPE_FACTORING_REJECTION,
            ])
            ->where('date', '>=', $latest->effective_date)
            ->exists();

        if ($blockingTransactionsExist) {
            throw new \InvalidArgumentException(
                __('This renewal cannot be deleted because there are transactions dated on or after its effective date (:date). Please remove those transactions first.', ['date' => $latest->getEffectiveDateFormatted()])
            );
        }

        $latest->delete();

        $newLatest = $this->getLatestTerms();
        $this->update([
            'limit' => $newLatest->limit,
            'borrowing_rate' => $newLatest->borrowing_rate,
            'margin_rate' => $newLatest->margin_rate,
            'interest_rate' => $newLatest->interest_rate,
            'min_interest_rate' => $newLatest->min_interest_rate,
            'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
            'admin_fees_rate' => $newLatest->admin_fees_rate,
            'to_be_setteled_max_within_days' => $newLatest->to_be_setteled_max_within_days,
            'contract_end_date' => $newLatest->contract_end_date,
        ]);

        $this->syncLimitStatement($this->company_id);
    }
}
