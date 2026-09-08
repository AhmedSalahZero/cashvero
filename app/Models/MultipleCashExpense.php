<?php

namespace App\Models;

use App\Traits\Models\HasUserComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * * مصروف نقدي متعدد البنود : حركة صرف واحدة فيها أكتر من مصروف
 *
 * * منفصلة تمامًا عن CashExpense — جداولها و موديلاتها لوحدها — عشان
 * * المصروفات الحالية (اللي شغالة و مربوطة بأودو) ما تتأثرش بأي حاجة هنا
 *
 * * ملهاش تكامل مع أودو حاليًا ، و التاب بتاعتها بتختفي أصلا لو الشركة
 * * عندها بيانات أودو
 *
 * @property int $id
 * @property string|null $type
 * @property string|null $payment_date
 * @property float|null $paid_amount الإجمالي المحسوب من البنود
 * @property int $company_id
 */
class MultipleCashExpense extends Model
{
    use HasUserComment;

    /**
     * * نفس أنواع الصرف بتاعة المصروف العادي — الشاشة و الكشوف بتفهمها
     */
    public const CASH_PAYMENT = 'cash_payment';

    public const PAYABLE_CHEQUE = 'payable_cheque';

    public const OUTGOING_TRANSFER = 'outgoing_transfer';

    protected $table = 'multiple_cash_expenses';

    protected $guarded = ['id'];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'is_reviewed' => 'boolean',
    ];

    /**
     * * حذف الحركة بيشيل بنودها و توزيعاتها و أسطر الكشوف بتاعتها
     *
     * * البنود عليها cascade في قاعدة البيانات ، لكن أسطر الكشوف لأ —
     * * فبنشيلها هنا صراحةً ، و إلا الرصيد يفضل متخصوم منه مصروف
     * * محذوف
     */
    protected static function booted(): void
    {
        static::deleting(function (self $expense): void {
            foreach ($expense->items as $item) {
                $item->deleteStatements();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(MultipleCashExpenseItem::class, 'multiple_cash_expense_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getPaymentDate(): ?string
    {
        return $this->payment_date;
    }

    public function getPaymentDateFormatted(): string
    {
        return $this->payment_date ? \Carbon\Carbon::make($this->payment_date)->format('d-m-Y') : '-';
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getAmount(): float
    {
        return (float) $this->paid_amount;
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->getAmount(), 2);
    }

    public function isCashPayment(): bool
    {
        return $this->getType() === self::CASH_PAYMENT;
    }

    public function isPayableCheque(): bool
    {
        return $this->getType() === self::PAYABLE_CHEQUE;
    }

    public function isOutgoingTransfer(): bool
    {
        return $this->getType() === self::OUTGOING_TRANSFER;
    }

    /**
     * * تاريخ الكشف : الشيك بينزل بتاريخ استحقاقه ، و الباقي بتاريخ الصرف
     */
    public function getStatementDate(): ?string
    {
        return $this->isPayableCheque() ? ($this->due_date ?: $this->payment_date) : $this->payment_date;
    }

    /**
     * * الإجمالي المحسوب من البنود — هو ده اللي المفروض يتصرف فعلا
     */
    public function calculateTotalFromItems(): float
    {
        return (float) $this->items->sum('paid_amount');
    }

    public function getTypeFormatted(): string
    {
        return match ($this->getType()) {
            self::CASH_PAYMENT => __('Cash Payment'),
            self::PAYABLE_CHEQUE => __('Payable Cheque'),
            self::OUTGOING_TRANSFER => __('Outgoing Transfer'),
            default => (string) $this->getType(),
        };
    }
}
