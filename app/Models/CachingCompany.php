<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $model
 * @property int $company_id
 * @property int $job_id
 * @property string $key_name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany whereJobId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany whereKeyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CachingCompany whereModel($value)
 * @mixin \Eloquent
 */
class CachingCompany extends Model
{
    protected $guarded =  ['id'] ; 
    public $timestamps = false ; 
    protected $table = 'caching_company';
    
}
