<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $branch_name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LeasingContract> $contracts
 */
class LeasingCompany extends Model
{
    protected $guarded = ['id'];

    public static function availableNames(): array
    {
        return config('leasing.companies', []);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(LeasingContract::class);
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function getBranchName(): string
    {
        return (string) ($this->branch_name ?? '');
    }
}
