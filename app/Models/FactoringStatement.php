<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $factoring_company_id
 * @property int $factoring_contract_id
 * @property int|null $factoring_transaction_id
 * @property string $entry_type
 * @property string $date
 * @property string $debit
 * @property string $credit
 * @property string|null $currency
 */
class FactoringStatement extends Model
{
    public const TYPE_CONTRACT_LIMIT = 'contract_limit';

    public const TYPE_FACTORING_DISBURSEMENT = 'factoring_disbursement';

    public const TYPE_FACTORING_SETTLEMENT = 'factoring_settlement';

    public const TYPE_FACTORING_REJECTION = 'factoring_rejection';

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function factoringCompany(): BelongsTo
    {
        return $this->belongsTo(FactoringCompany::class);
    }

    public function factoringContract(): BelongsTo
    {
        return $this->belongsTo(FactoringContract::class);
    }

    public function factoringTransaction(): BelongsTo
    {
        return $this->belongsTo(FactoringTransaction::class);
    }

    public function getComment(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar'
            ? (string) ($this->comment_ar ?: $this->comment_en)
            : (string) ($this->comment_en ?: $this->comment_ar);
    }
}
