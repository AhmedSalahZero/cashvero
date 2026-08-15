<?php

namespace App\Models;

use App\Traits\StaticBoot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $leasing_contract_id
 * @property int $company_id
 * @property string|null $date
 * @property numeric|null $beginning_balance
 * @property numeric|null $cheque_amount
 * @property numeric|null $interest_amount
 * @property numeric|null $principle_amount
 * @property numeric|null $end_balance
 * @property numeric $remaining
 * @property string|null $status
 * @property string|null $cheque_number
 * @property int|null $drawee_bank_id
 * @property string|null $account_number
 * @property-read \App\Models\LeasingContract|null $leasingContract
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContractLoanScheduleSettlement> $settlements
 */
class ContractLoanSchedule extends Model
{
    use StaticBoot;

    protected $guarded = [];

    protected $table = 'contract_loan_schedules';

    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id ?? Request('company_id'));
    }

    public function leasingContract(): BelongsTo
    {
        return $this->belongsTo(LeasingContract::class);
    }

    public function draweeBank(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class, 'drawee_bank_id');
    }

    public function financialInstitutionAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionAccount::class);
    }

    /**
     * Bug fix (client-flagged, confirmed 2026-08-15): account_number used
     * to be a plain text copy taken at import time, so it went stale the
     * moment someone edited the real account's number afterwards. Now
     * that a schedule row can be linked to its actual account record
     * (financial_institution_account_id — see the 2026-08-15 migrations),
     * this accessor prefers that live value. $value is the raw stored
     * column, kept as a fallback for older rows that couldn't be
     * confidently matched to one account during the backfill.
     *
     * This is an accessor (not a separate getter method) so every
     * existing read of $schedule->account_number — the settlement screen,
     * the schedule table export, everywhere — gets the fix automatically.
     */
    public function getAccountNumberAttribute($value)
    {
        return $this->financialInstitutionAccount?->account_number ?: ($value ?? '');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ContractLoanScheduleSettlement::class, 'contract_loan_schedule_id');
    }

    public function getFinancialInstitutionId(): ?int
    {
        return $this->drawee_bank_id;
    }

    public function hasDraweeBank(): bool
    {
        return $this->drawee_bank_id && $this->getRelationValue('draweeBank') !== null;
    }

    public function canSettle(): bool
    {
        return $this->hasLeasingContract() && $this->hasDraweeBank();
    }

    public function getMediumTermLoanName(): string
    {
        return $this->getLeasingContractName();
    }

    public function getMediumTermLoanId(): ?int
    {
        return $this->getLeasingContractId();
    }

    public function getInstallmentNumber(): int
    {
        if (! $this->leasingContract) {
            return 0;
        }

        $index = $this->leasingContract->contractLoanSchedules
            ->sortBy('date')
            ->filter(fn (ContractLoanSchedule $schedule) => $schedule->getChequeAmount() > 0)
            ->values()
            ->search(fn (ContractLoanSchedule $schedule) => $schedule->id === $this->id);

        return $index === false ? 0 : $index + 1;
    }

    public function getLeasingContractName(): string
    {
        return $this->leasingContract?->getName() ?? __('N/A');
    }

    public function getLeasingContractId(): ?int
    {
        return $this->leasing_contract_id ?? $this->leasingContract?->id;
    }

    public function hasLeasingContract(): bool
    {
        return $this->leasing_contract_id && $this->leasingContract !== null;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getDateFormatted()
    {
        $date = $this->getDate();

        return isValidScheduleDate($date) ? Carbon::make($date)->format('d-m-Y') : __('N/A');
    }

    public function getSettlementDefaultDate(): string
    {
        return getSettlementDefaultDate($this->getDate());
    }

    public function getCurrency()
    {
        return $this->leasingContract?->currency ?? '-';
    }

    public function getBeginningBalance()
    {
        return $this->beginning_balance ?: 0;
    }

    public function getBeginningBalanceFormatted()
    {
        return number_format($this->getBeginningBalance());
    }

    public function getChequeAmount()
    {
        return $this->cheque_amount ?: 0;
    }

    public function getChequeAmountFormatted()
    {
        return number_format($this->getChequeAmount());
    }

    /** @deprecated Use getChequeAmount() */
    public function getSchedulePayment()
    {
        return $this->getChequeAmount();
    }

    /** @deprecated Use getChequeAmountFormatted() */
    public function getSchedulePaymentFormatted()
    {
        return $this->getChequeAmountFormatted();
    }

    public function getInterestAmount()
    {
        return $this->interest_amount ?: 0;
    }

    public function getInterestAmountFormatted()
    {
        return number_format($this->getInterestAmount());
    }

    public function getPrincipleAmount()
    {
        return $this->principle_amount ?: 0;
    }

    public function getPrincipleAmountFormatted()
    {
        return number_format($this->getPrincipleAmount());
    }

    public function getEndBalance()
    {
        return $this->end_balance ?: 0;
    }

    public function getEndBalanceFormatted()
    {
        return number_format($this->getEndBalance());
    }

    public function getChequeNumber(): string
    {
        return (string) ($this->cheque_number ?? '');
    }

    public function getAccountNumber(): string
    {
        return (string) ($this->account_number ?? '');
    }

    public function getDraweeBankName(): string
    {
        return $this->getRelationValue('draweeBank')?->getBankName() ?? __('N/A');
    }

    public function getDraweeBankAttribute(): string
    {
        return (string) ($this->getRelationValue('draweeBank')?->getBankName() ?? '');
    }

    public function getStatusFormatted()
    {
        return $this->status ? snakeToCamel($this->status) : __('N/A');
    }

    public function getRemaining()
    {
        return $this->remaining ?: 0;
    }

    public function getRemainingFormatted(): string
    {
        return number_format($this->getRemaining());
    }

    public static function getExportableFields(): array
    {
        return [
            'date' => __('Date'),
            'beginning_balance' => __('Beginning Balance'),
            'cheque_amount' => __('Cheque Amount'),
            'interest_amount' => __('Interest Amount'),
            'principle_amount' => __('Principle Amount'),
            'end_balance' => __('End Balance'),
            'cheque_number' => __('Cheque Number'),
            'drawee_bank' => __('Drawee Bank'),
            'account_number' => __('Account Number'),
        ];
    }

    public static function getImportHeaderAliases(): array
    {
        return [
            'cheque_amount' => ['Cheque Amount', 'cheque_amount', 'Schedule Payment', 'schedule_payment', 'مبلغ الشيك'],
            'account_number' => ['Account Number', 'account_number', 'رقم الحساب'],
            'cheque_number' => ['Cheque Number', 'cheque_number', 'رقم الشيك'],
            'drawee_bank' => ['Drawee Bank', 'drawee_bank', 'البنك المسحوب عليه'],
        ];
    }

    public static function getContractLoanInstallmentsAtDates(
        array &$result,
        $foreignExchangeRates,
        $mainFunctionalCurrency,
        int $companyId,
        array $datesWithWeekNumber,
        string $endDate,
        ?string $currency = null
    ): void {
        $mainType = 'cash_expenses';
        $showAllCurrenciesConverted = $currency === null || $currency === $mainFunctionalCurrency;
        $rows = DB::table('contract_loan_schedules')
            ->where('contract_loan_schedules.company_id', $companyId)
            ->join('leasing_contracts', 'leasing_contracts.id', '=', 'contract_loan_schedules.leasing_contract_id')
            ->when(! $showAllCurrenciesConverted, function ($q) use ($currency) {
                $q->where('leasing_contracts.currency', $currency);
            })
            ->where('contract_loan_schedules.date', '>=', now()->format('Y-m-d'))
            ->where('contract_loan_schedules.date', '<=', $endDate)
            ->where('contract_loan_schedules.date', '>', '0000-00-00')
            ->where('contract_loan_schedules.remaining', '>', 0)
            ->selectRaw('leasing_contracts.name as name, contract_loan_schedules.remaining as paid_amount, contract_loan_schedules.date as date, leasing_contracts.currency as currency')
            ->get();
        $subType = __('Contracts Loan Installments');

        foreach ($rows as $row) {
            if (! isset($datesWithWeekNumber[$row->date])) {
                continue;
            }
            $currentCurrency = $row->currency;
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                $currentCurrency,
                $mainFunctionalCurrency,
                $row->date,
                $companyId,
                $foreignExchangeRates
            );
            $contractName = $row->name ?: __('N/A');
            $currentPaidAmount = $showAllCurrenciesConverted ? $row->paid_amount * $exchangeRate : (float) $row->paid_amount;
            $currentWeekYear = $datesWithWeekNumber[$row->date];
            $result[$mainType][$subType][$contractName]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$contractName]['weeks'][$currentWeekYear])
                ? $result[$mainType][$subType][$contractName]['weeks'][$currentWeekYear] + $currentPaidAmount
                : $currentPaidAmount;
            $result[$mainType][$subType][$contractName]['total'] = isset($result[$mainType][$subType][$contractName]['total'])
                ? $result[$mainType][$subType][$contractName]['total'] + $currentPaidAmount
                : $currentPaidAmount;
            $currentTotal = $currentPaidAmount;
            $result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear])
                ? $result[$mainType][$subType]['total'][$currentWeekYear] + $currentTotal
                : $currentTotal;
        }
    }
}
