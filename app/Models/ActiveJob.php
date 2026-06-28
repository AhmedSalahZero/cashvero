<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $status
 * @property string $model
 * @property string $model_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereModelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ActiveJob whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ActiveJob extends Model
{
   
    protected $table = 'active_jobs';
 
    protected $guarded = [];
    // Company Scoop
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id);
    }
}
