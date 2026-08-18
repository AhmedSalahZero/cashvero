<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        /**
         * ⚠️ getStartDate() returns the INT 0 for a contract with no
         * start date, and Carbon::make(0) is null — so this
         * used to fatal with "Call to a member function format() on
         * null" on any screen that formatted a dateless contract.
         * Surfaced by the contract statement screen, which reads both
         * dates unconditionally.
         */
        $date = Carbon::make($this->getStartDate());

        return $date ? $date->format('d-m-Y') : '—';
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
        /**
         * ⚠️ getEndDate() returns the INT 0 for a contract with no
         * end date, and Carbon::make(0) is null — so this
         * used to fatal with "Call to a member function format() on
         * null" on any screen that formatted a dateless contract.
         * Surfaced by the contract statement screen, which reads both
         * dates unconditionally.
         */
        $date = Carbon::make($this->getEndDate());

        return $date ? $date->format('d-m-Y') : '—';
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

    /**
     * Bug fix (2026-08-15): this used to just call $schedule->delete() for
     * every installment on the schedule, without first deleting each
     * installment's payment settlements. Those settlements are what create
     * rows in loan_statements (the account ledger), so deleting the contract
     * this way left orphaned loan_statements / contract_loan_schedule_settlements
     * rows behind — pointing at a schedule/contract that no longer existed.
     *
     * destroySchedule() in LeasingContractController already does this
     * correctly (settlement->deleteAllRelations() + settlement->delete()
     * before schedule->delete()). This now reuses that same pattern so
     * deleting a whole contract (destroy()/update() in the controller)
     * cleans up fully instead of leaving ledger entries behind.
     */
    public function deleteRelations(): void
    {
        DB::transaction(function () {
            $this->contractLoanSchedules->each(function (ContractLoanSchedule $schedule) {
                $schedule->settlements->each(function (ContractLoanScheduleSettlement $settlement) {
                    $settlement->deleteAllRelations();
                    $settlement->delete();
                });
                $schedule->delete();
            });
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

    /* ─────────────────────────────────────────────────────────────────
     | Leasing Contract as a SOURCE OF PAYMENT
     |
     | Reached through the "Through Leasing" money type on the Money
     | Payment screen: the leasing company pays the supplier straight out
     | of this contract, which draws its limit down. See the header of
     | database/migrations/2026_08_18_150000_add_leasing_as_money_payment_type.php
     | for the business shape and why this is a money type rather than an
     | account type.
     ───────────────────────────────────────────────────────────────── */

    public function bankStatements(): HasMany
    {
        return $this->hasMany(LeasingContractBankStatement::class, 'leasing_contract_id', 'id');
    }

    /**
     * * هل اتسحب من العقد دا اي حاجه فعلا (يعني اتدفع بيه فاتورة مورد)
     */
    public function hasDrawdowns(): bool
    {
        return $this->bankStatements()->where('credit', '>', 0)->exists();
    }

    /**
     * * الرو المتاح = الحد ناقص المسحوب .. وبيزيد تاني كل ما يتسدد جزء من
     * * ال principle من الاقساط (نفس قاعدة القرض متوسط الاجل بالظبط)
     *
     * ⚠️ العقد اللي لسه مافيش عليه اي حركة ما لوش صف في الجدول خالص .. يبقي
     * * غياب الصف معناه "لسه ما اتسحبش منه حاجه" مش "الرصيد صفر" .. فالمتاح
     * * هو الحد كله. ولاحظ ان دا لازم يتطبق على كل تاريخ لوحده: لو اتسحب في
     * * 01-02 وانت بتدخل دفعة بتاريخ 15-01 يبقي المتاح في التاريخ ده لسه
     * * الحد الكامل.
     */
    public function getAvailableRoomAt(?string $date = null): float
    {
        /**
         * @var LeasingContractBankStatement|null $lastRow
         */
        $lastRow = $this->bankStatements()
            ->when($date, fn ($q) => $q->where('date', '<=', $date))
            ->orderByRaw('full_date desc , id desc')
            ->first();

        return $lastRow ? (float) $lastRow->getRoom() : (float) $this->getLimit();
    }

    public function getAvailableRoomAtFormatted(?string $date = null): string
    {
        return number_format($this->getAvailableRoomAt($date), 2);
    }

    /**
     * The contracts the "Through Leasing" card may pay from: every
     * RUNNING contract of the chosen leasing company, in this company,
     * matching the payment currency.
     *
     * There is deliberately no new/existing flag (owner's decision,
     * 2026-08-18) — a contract is payable simply by being running.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function payableFor(int $companyId, int $leasingCompanyId, ?string $currencyName = null)
    {
        return self::where('company_id', $companyId)
            ->where('leasing_company_id', $leasingCompanyId)
            ->where('status', self::RUNNING)
            ->when($currencyName, fn ($q) => $q->where('currency', $currencyName))
            ->orderBy('name')
            ->get();
    }
}
