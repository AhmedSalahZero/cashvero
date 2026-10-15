<?php

namespace App\Models;

use App\Interfaces\Models\IHaveCreditOverdraftStatement;
use App\Traits\Models\HasCreditStatements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * * بند واحد جوه مصروف نقدي متعدد
 *
 * * ⚠️ البند هو اللي بيملك سطر كشف الحساب ، مش الحركة الأم — و ده مقصود :
 * * المطلوب إن ٣ بنود في حركة واحدة ينزلوا ٣ أسطر منفصلة بمبالغهم في
 * * الكشف ، مش سطر واحد بالإجمالي . عشان كده الجداول بتاعة الكشوف
 * * اتضاف لها multiple_cash_expense_item_id
 *
 * @property int $id
 * @property int $multiple_cash_expense_id
 * @property float $paid_amount
 */
class MultipleCashExpenseItem extends Model implements IHaveCreditOverdraftStatement
{
    use HasCreditStatements;

    protected $table = 'multiple_cash_expense_items';

    protected $guarded = ['id'];

    protected $casts = [
        'paid_amount' => 'decimal:2',
    ];

    public function multipleCashExpense(): BelongsTo
    {
        return $this->belongsTo(MultipleCashExpense::class, 'multiple_cash_expense_id', 'id');
    }

    public function cashExpenseCategoryName(): BelongsTo
    {
        return $this->belongsTo(CashExpenseCategoryName::class, 'cash_expense_category_name_id', 'id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MultipleCashExpenseAllocation::class, 'multiple_cash_expense_item_id', 'id');
    }

    /* ── أسطر الكشوف : واحد لكل نوع حساب ممكن يتصرف منه ───────────── */

    public function cashInSafeCreditStatement(): HasOne
    {
        return $this->hasOne(CashInSafeStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function currentAccountCreditBankStatement(): HasOne
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function cleanOverdraftCreditBankStatement(): HasOne
    {
        return $this->hasOne(CleanOverdraftBankStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function fullySecuredOverdraftCreditBankStatement(): HasOne
    {
        return $this->hasOne(FullySecuredOverdraftBankStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function overdraftAgainstCommercialPaperCreditBankStatement(): HasOne
    {
        return $this->hasOne(OverdraftAgainstCommercialPaperBankStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function overdraftAgainstAssignmentOfContractCreditBankStatement(): HasOne
    {
        return $this->hasOne(OverdraftAgainstAssignmentOfContractBankStatement::class, 'multiple_cash_expense_item_id', 'id');
    }

    /**
     * * handleCreditStatement بتسأل الموديل نفسه هل الصرف كاش ولا لأ ،
     * * و ده بيتحدد على مستوى الحركة الأم مش البند
     */
    public function isCashPayment(): bool
    {
        return (bool) $this->multipleCashExpense?->isCashPayment();
    }

    public function getAmount(): float
    {
        return (float) $this->paid_amount;
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->getAmount(), 2);
    }

    public function getExpenseName(): string
    {
        return $this->cashExpenseCategoryName?->getName() ?? __('N/A');
    }

    public function getCategoryName(): string
    {
        return $this->cashExpenseCategoryName?->cashExpenseCategory?->getName() ?? __('N/A');
    }

    /**
     * * بيشيل سطر الكشف بتاع البند ده من أي كشف نزل فيه
     *
     * * من غير ده الرصيد يفضل متخصوم منه بند اتمسح — و دي فلوس ، مش
     * * مجرد صف في جدول
     */
    public function deleteStatements(): void
    {
        foreach ([
            $this->cashInSafeCreditStatement,
            $this->currentAccountCreditBankStatement,
            $this->cleanOverdraftCreditBankStatement,
            $this->fullySecuredOverdraftCreditBankStatement,
            $this->overdraftAgainstCommercialPaperCreditBankStatement,
            $this->overdraftAgainstAssignmentOfContractCreditBankStatement,
        ] as $statement) {
            $statement?->delete();
        }
    }
}
