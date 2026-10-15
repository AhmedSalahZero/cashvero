<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property array<array-key, mixed> $fields
 * @property string $model_name
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereModelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomizedFieldsExportation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CustomizedFieldsExportation extends Model
{

    protected $guarded = [];
    
    protected $casts = [
        'fields' => 'array',
    ];

    // Company Scoop
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id);
    }
}
