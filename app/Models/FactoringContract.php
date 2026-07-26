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
}
