<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $leasing_company_id
 * @property string $status
 * @property string|null $name
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string $currency
 * @property numeric $limit
 * @property numeric $paid_amount
 * @property numeric $outstanding_amount
 * @property numeric $borrowing_rate
 * @property numeric $margin_rate
 * @property int|null $duration
 * @property string|null $installment_payment_interval
 * @property-read \App\Models\LeasingCompany|null $leasingCompany
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContractLoanSchedule> $contractLoanSchedules
 */
class LeasingContract extends Model
{
    use HasBasicStoreRequest;

    const RUNNING = 'running';

    protected $guarded = ['id'];

    public static function getAllTypes(): array
    {
        return [self::RUNNING];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getStartDate()
    {
        return $this->start_date ?: 0;
    }

    public function getStartDateFormatted()
    {
        return Carbon::make($this->getStartDate())->format('d-m-Y');
    }

    public function setStartDateAttribute($value)
    {
        if (!$value) {
            return null;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['start_date'] = $value;

            return;
        }
        $this->attributes['start_date'] = $date[2] . '-' . $date[0] . '-' . $date[1];
    }

    public function getEndDate()
    {
        return $this->end_date ?: 0;
    }

    public function getEndDateFormatted()
    {
        return Carbon::make($this->getEndDate())->format('d-m-Y');
    }

    public function setEndDateAttribute($value)
    {
        if (!$value) {
            return null;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['end_date'] = $value;

            return;
        }
        $this->attributes['end_date'] = $date[2] . '-' . $date[0] . '-' . $date[1];
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getCurrencyFormatted()
    {
        return __($this->getCurrency());
    }

    public function leasingCompany(): BelongsTo
    {
        return $this->belongsTo(LeasingCompany::class);
    }

    public function getLeasingCompanyName(): string
    {
        return $this->leasingCompany?->getName() ?? __('N/A');
    }

    public function getBorrowingRate()
    {
        return $this->borrowing_rate ?: 0;
    }

    public function getBorrowingRateFormatted()
    {
        return number_format($this->getBorrowingRate(), 2) . ' %';
    }

    public function getMarginRate()
    {
        return $this->margin_rate ?: 0;
    }

    public function getMarginRateFormatted()
    {
        return number_format($this->getMarginRate(), 2) . ' %';
    }

    public function getInterestRate()
    {
        return $this->getMarginRate() + $this->getBorrowingRate();
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function getDurationFormatted()
    {
        return $this->getDuration() . ' ' . __('Months');
    }

    public function getPaymentInstallmentInterval()
    {
        return $this->installment_payment_interval;
    }

    public function getPaymentInstallmentIntervalFormatted()
    {
        return str_to_upper($this->getPaymentInstallmentInterval());
    }

    public function contractLoanSchedules(): HasMany
    {
        return $this->hasMany(ContractLoanSchedule::class);
    }

    public function deleteRelations(): void
    {
        $this->contractLoanSchedules->each(function (ContractLoanSchedule $schedule) {
            $schedule->delete();
        });
    }

    public function getLimit()
    {
        return $this->limit ?: 0;
    }

    public function getLimitFormatted()
    {
        return number_format($this->getLimit());
    }

    public function getLoanOutstanding()
    {
        return $this->outstanding_amount ?: 0;
    }

    public function getLoanOutstandingFormatted()
    {
        return number_format($this->getLoanOutstanding());
    }

    public function getNextInstallmentDateAndAmount(string $date): array
    {
        $schedules = $this->relationLoaded('contractLoanSchedules')
            ? $this->contractLoanSchedules
            : $this->contractLoanSchedules()->get();

        $nextInstallment = $schedules
            ->filter(fn ($schedule) => $schedule->date >= $date)
            ->sortBy('date')
            ->first();

        return [
            'amount_formatted' => $nextInstallment ? $nextInstallment->getSchedulePaymentFormatted() : 0,
            'date_formatted' => $nextInstallment ? $nextInstallment->getDateFormatted() : null,
        ];
    }

    public function getTotalPastDueRemaining()
    {
        $pastDueItems = $this->getLoanPastDuesDetailsArray();

        return array_sum(array_column($pastDueItems, 'remaining'));
    }

    public function getTotalPastDueRemainingFormatted()
    {
        return number_format($this->getTotalPastDueRemaining());
    }

    public function getLoanPastDuesDetailsArray(): array
    {
        $mapSchedule = function ($schedule) {
            return [
                'date' => $schedule->date,
                'schedule_payment' => $schedule->cheque_amount,
                'remaining' => $schedule->remaining,
            ];
        };

        if ($this->relationLoaded('contractLoanSchedules')) {
            return $this->contractLoanSchedules
                ->whereIn('status', ['past_due', 'partially_paid_and_past_due'])
                ->map($mapSchedule)
                ->values()
                ->all();
        }

        return $this->contractLoanSchedules()
            ->whereIn('status', ['past_due', 'partially_paid_and_past_due'])
            ->get(['date', 'cheque_amount', 'remaining'])
            ->map($mapSchedule)
            ->values()
            ->all();
    }
}
