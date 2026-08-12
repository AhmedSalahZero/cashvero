<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One dated "chapter" of an LG Facility's terms — either the original
 * contract (is_original = true), or a later renewal. Owns its own set
 * of 4 Term & Conditions rows (one per LG type) via termAndConditions().
 *
 * @property int $id
 * @property int $company_id
 * @property int $letter_of_guarantee_facility_id
 * @property string $effective_date
 * @property float|null $limit
 * @property string|null $contract_end_date
 * @property string|null $notes
 * @property bool $is_original
 * @property int|null $created_by
 */
class LetterOfGuaranteeFacilityTermsHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_original' => 'boolean',
    ];

    public function letterOfGuaranteeFacility(): BelongsTo
    {
        return $this->belongsTo(LetterOfGuaranteeFacility::class, 'letter_of_guarantee_facility_id', 'id');
    }

    public function termAndConditions(): HasMany
    {
        return $this->hasMany(LetterOfGuaranteeFacilityTermAndCondition::class, 'terms_history_id', 'id');
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
