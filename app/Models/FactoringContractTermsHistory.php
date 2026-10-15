<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated "chapter" of a Factoring Contract's terms — either the
 * original contract (is_original = true), or a later renewal. Mirrors
 * CleanOverdraftTermsHistory exactly — same fields, same reasoning.
 *
 * @property int $id
 * @property int $company_id
 * @property int $factoring_contract_id
 * @property string $effective_date
 * @property float|null $limit
 * @property float|null $borrowing_rate
 * @property float|null $margin_rate
 * @property float|null $interest_rate
 * @property float|null $min_interest_rate
 * @property float|null $highest_debt_balance_rate
 * @property float|null $admin_fees_rate
 * @property int|null $to_be_setteled_max_within_days
 * @property string|null $contract_end_date
 * @property string|null $notes
 * @property bool $is_original
 * @property int|null $created_by
 */
class FactoringContractTermsHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_original' => 'boolean',
    ];

    public function factoringContract(): BelongsTo
    {
        return $this->belongsTo(FactoringContract::class, 'factoring_contract_id', 'id');
    }

    public function getEffectiveDateFormatted(): ?string
    {
        return $this->effective_date ? Carbon::make($this->effective_date)->format('d-m-Y') : null;
    }

    public function getContractEndDateFormatted(): ?string
    {
        return $this->contract_end_date ? Carbon::make($this->contract_end_date)->format('d-m-Y') : null;
    }

    public function getLimitFormatted(): string
    {
        return number_format($this->limit ?: 0, 2);
    }

    public function isRenewal(): bool
    {
        return ! $this->is_original;
    }
}
