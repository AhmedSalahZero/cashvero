<?php

namespace App\Models;

use App\OutstandingBreakdown;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function storeOutstandingBreakdown(Request $request, Company $company): void
    {
        $outstandingBalance = $request->get('outstanding_balance', 0);
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
}
