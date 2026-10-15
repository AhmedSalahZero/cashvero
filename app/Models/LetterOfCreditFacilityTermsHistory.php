<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated "chapter" of an LC Facility's terms — either the original
 * contract (is_original = true), or a later renewal. Covers BOTH
 * halves of an LC Facility's terms: the flat Financing Terms &
 * Conditions (limit, borrowing/margin/interest/admin-fees rates —
 * same idea as FullySecuredOverdraftTermsHistory), and, via
 * termAndConditions(), its own set of 3 per-LC-type rate rows (same
 * idea as LetterOfGuaranteeFacilityTermsHistory).
 *
 * @property int $id
 * @property int $company_id
 * @property int $letter_of_credit_facility_id
 * @property string $effective_date
 * @property float|null $limit
 * @property float|null $cd_or_td_lending_percentage
 * @property float|null $borrowing_rate
 * @property float|null $bank_margin_rate
 * @property float|null $interest_rate
 * @property float|null $min_interest_rate
 * @property float|null $highest_debt_balance_rate
 * @property float|null $admin_fees_rate
 * @property string|null $contract_end_date
 * @property string|null $notes
 * @property bool $is_original
 * @property int|null $created_by
 */
class LetterOfCreditFacilityTermsHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_original' => 'boolean',
    ];

    public function letterOfCreditFacility(): BelongsTo
    {
        return $this->belongsTo(LetterOfCreditFacility::class, 'letter_of_credit_facility_id', 'id');
    }

    public function termAndConditions(): HasMany
    {
        return $this->hasMany(LetterOfCreditFacilityTermAndCondition::class, 'terms_history_id', 'id');
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
        return number_format($this->limit ?: 0);
    }

    public function isRenewal(): bool
    {
        return ! $this->is_original;
    }
}
