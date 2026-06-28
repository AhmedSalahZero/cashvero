<?php

namespace App\Models;

use App\Traits\Models\HasCreditStatements;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLoanScheduleSettlement extends Model
{
    use HasCreditStatements, HasDeleteButTriggerChangeOnLastElement;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(fn (self $settlement) => $settlement->updateContractLoanScheduleRemaining());
        static::updated(fn (self $settlement) => $settlement->updateContractLoanScheduleRemaining());
        static::deleted(fn (self $settlement) => $settlement->updateContractLoanScheduleRemaining());
    }

    public function updateContractLoanScheduleRemaining(): void
    {
        $contractLoanSchedule = $this->contractLoanSchedule;

        if (! $contractLoanSchedule) {
            return;
        }

        $totalSettlement = 0;

        foreach ($contractLoanSchedule->settlements as $settlement) {
            $totalSettlement += $settlement->getAmount();
        }

        $chequeAmount = $contractLoanSchedule->getChequeAmount();
        $remaining = $chequeAmount - $totalSettlement;

        $contractLoanSchedule->update([
            'remaining' => $remaining,
            'status' => resolveLoanScheduleStatus($remaining, $chequeAmount, $contractLoanSchedule->date),
        ]);
    }

    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id ?? Request('company_id'));
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getAmountFormatted()
    {
        return number_format($this->getAmount());
    }

    public function getCurrentAccountNumber()
    {
        return $this->current_account_number;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getDateFormatted()
    {
        $date = $this->getDate();

        return $date ? Carbon::make($date)->format('d-m-Y') : __('N/A');
    }

    public function setDateAttribute($value)
    {
        if (is_object($value)) {
            return $value;
        }

        $date = explode('/', $value);

        if (count($date) != 3) {
            $this->attributes['date'] = $value;

            return;
        }

        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        $this->attributes['date'] = $year.'-'.$month.'-'.$day;
    }

    public function getAccountNumber()
    {
        return $this->current_account_number;
    }

    public function getPaidAmount()
    {
        return $this->getAmount();
    }

    public function getAccountTypeId(): int
    {
        return AccountType::onlyCurrentAccount()->first()->id;
    }

    public function contractLoanSchedule(): BelongsTo
    {
        return $this->belongsTo(ContractLoanSchedule::class, 'contract_loan_schedule_id', 'id');
    }

    public function currentAccountCreditBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'contract_loan_schedule_settlement_id', 'id')->where('is_credit', 1);
    }

    public function currentAccountCreditBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'contract_loan_schedule_settlement_id', 'id')->where('is_credit', 1)->orderBy('full_date', 'desc');
    }

    public function loanStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'contract_loan_schedule_settlement_id', 'id');
    }

    public function loanStatements()
    {
        return $this->hasMany(LoanStatement::class, 'contract_loan_schedule_settlement_id', 'id')->orderBy('full_date', 'desc');
    }

    public function deleteAllRelations(): void
    {
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
        LoanStatement::deleteButTriggerChangeOnLastElement($this->loanStatements);
    }

    public function handleLoanStatement(int $companyId, int $financialInstitutionId, string $accountNumber, string $date, $debitAmount, string $commentEn, string $commentAr): void
    {
        $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $companyId, $financialInstitutionId);
        $this->loanStatements()->create([
            'financial_institution_account_id' => $financialInstitutionAccount->id,
            'company_id' => $companyId,
            'is_debit' => 1,
            'is_credit' => 0,
            'date' => $date,
            'debit' => $debitAmount,
            'comment_en' => $commentEn,
            'comment_ar' => $commentAr,
        ]);
    }
}
