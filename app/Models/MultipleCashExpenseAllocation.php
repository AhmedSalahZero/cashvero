<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * * توزيع بند مصروف على عقد
 *
 * * التوزيع هنا مربوط بالبند نفسه مش بالحركة كلها ، عشان المستخدم يعرف
 * * التوزيع ده بتاع أنهي مصروف بالظبط
 */
class MultipleCashExpenseAllocation extends Model
{
    protected $table = 'multiple_cash_expense_allocations';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MultipleCashExpenseItem::class, 'multiple_cash_expense_item_id', 'id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }
}
