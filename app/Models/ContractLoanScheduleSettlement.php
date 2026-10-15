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

    /**
     * The repayment rows this settlement posted on the leasing
     * contract's own drawdown ledger — see
     * handleLeasingContractRepayment() below.
     */
    public function leasingContractBankStatements()
    {
        return $this->hasMany(LeasingContractBankStatement::class, 'contract_loan_schedule_settlement_id', 'id')->orderBy('full_date', 'desc');
    }

    public function deleteAllRelations(): void
    {
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
        LoanStatement::deleteButTriggerChangeOnLastElement($this->loanStatements);
        LeasingContractBankStatement::deleteButTriggerChangeOnLastElement($this->leasingContractBankStatements);
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

    /* ─────────────────────────────────────────────────────────────────
     | Leasing Contract repayment
     |
     | Copies LoanScheduleSettlement::handleMediumTermLoanRepayment()
     | for the leasing side: repaying an installment posts its PRINCIPLE
     | portion as a debit on the contract's drawdown ledger, which lifts
     | end_balance back toward zero and replenishes room. The interest
     | portion is recorded next to it but never moves the balance —
     | a leasing installment already bundles its interest inside
     | schedule_payment, so adding it here would double-count it.
     ───────────────────────────────────────────────────────────────── */

    /**
     * * جزء ال principle اللي اتسدد لحد اجمالي مبلغ $paidSoFar على القسط دا.
     * * الفايدة بتتاخد الاول بالكامل ، فاللي بعدها بس هو اللي بيروح للاصل.
     */
    public static function principlePaidFor(float $paidSoFar, float $interestAmount, float $principleAmount): float
    {
        return max(0, min($paidSoFar - $interestAmount, $principleAmount));
    }

    /**
     * * قيمة الفايدة اللي اتسددت لحد اجمالي مبلغ $paidSoFar على القسط دا.
     * * الفايدة بتتاخد الاول بالكامل .. فا هي ابسط حاجه: اللي دفعته او قيمة
     * * الفايدة .. ايهما اقل.
     */
    public static function interestPaidFor(float $paidSoFar, float $interestAmount): float
    {
        return max(0, min($paidSoFar, $interestAmount));
    }

    /**
     * Posts this settlement's principle half onto the contract ledger.
     *
     * ⚠️ Deliberately computed as (paid AFTER this settlement) minus
     * (paid BEFORE it), not as a share of this settlement's own amount:
     * when an installment is paid over several settlements the interest
     * must be taken in full first, and only then does the principle
     * start moving. Taking a proportion of each payment would free room
     * too early.
     */
    public function handleLeasingContractRepayment(int $companyId, string $commentEn, string $commentAr): void
    {
        $schedule = $this->contractLoanSchedule;
        $leasingContract = $schedule?->leasingContract;

        /**
         * * لو العقد ما اتسحبش منه اي حاجه من خلال كاش فيرو يبقي مفيش حساب
         * * ننزل عليه اصلا .. العقود القديمة اللي الشركة خدتها قبل كاش فيرو
         * * بتتسدد اقساطها بس ، وتفصيلة الفايدة/الاصل بتاعتها بتظهر برضه في
         * * شاشة كشف حساب العقد لانها بتتحسب من جدول الاقساط مباشرة.
         */
        if (! $leasingContract || ! $leasingContract->hasDrawdowns()) {
            return;
        }

        $interestAmount = (float) $schedule->getInterestAmount();
        $principleAmount = (float) $schedule->getPrincipleAmount();

        /**
         * * اللي اتدفع قبل الدفعة دي على نفس القسط .. مرتب بال id لان الشاشة
         * * اصلا ما بتسمحش بتعديل او حذف غير اخر دفعة
         */
        $paidBefore = (float) $schedule->settlements()
            ->where('id', '<', $this->id)
            ->sum('amount');
        $paidAfter = $paidBefore + (float) $this->getAmount();

        $principleDebit = self::principlePaidFor($paidAfter, $interestAmount, $principleAmount)
            - self::principlePaidFor($paidBefore, $interestAmount, $principleAmount);
        $interestPaid = self::interestPaidFor($paidAfter, $interestAmount)
            - self::interestPaidFor($paidBefore, $interestAmount);

        if ($principleDebit <= 0 && $interestPaid <= 0) {
            return;
        }

        $this->leasingContractBankStatements()->create([
            'leasing_contract_id' => $leasingContract->id,
            'company_id' => $companyId,
            'type' => LeasingContractBankStatement::INSTALLMENT_REPAYMENT,
            'date' => $this->getDate(),
            'limit' => $leasingContract->getLimit(),
            'beginning_balance' => 0,
            'debit' => max(0, $principleDebit),
            'credit' => 0,
            'interest_amount' => max(0, $interestPaid),
            'comment_en' => $commentEn,
            'comment_ar' => $commentAr,
        ]);
    }
}
