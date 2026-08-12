<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated "chapter" of a Fully Secured Overdraft's terms — either the
 * facility's original contract (is_original = true), or a later renewal.
 * Mirrors CleanOverdraftTermsHistory exactly.
 *
 * @property int $id
 * @property int $company_id
 * @property int $fully_secured_overdraft_id
 * @property string $effective_date
 * @property float|null $limit
 * @property float|null $highest_debt_balance_rate
 * @property float|null $admin_fees_rate
 * @property int|null $to_be_setteled_max_within_days
 * @property string|null $contract_end_date
 * @property string|null $notes
 * @property bool $is_original
 * @property int|null $created_by
 */
class FullySecuredOverdraftTermsHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_original' => 'boolean',
    ];

    public function fullySecuredOverdraft(): BelongsTo
    {
        return $this->belongsTo(FullySecuredOverdraft::class, 'fully_secured_overdraft_id', 'id');
    }

    public function getEffectiveDateFormatted(): ?string
    {
        return $this->effective_date ? Carbon::make($this->effective_date)->format('d-m-Y') : null;
    }

    public function getLimitFormatted(): string
    {
        return number_format($this->limit ?: 0);
    }

    public function isRenewal(): bool
    {
        return ! $this->is_original;
    }
}
