<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $model_name
 * @property string|null $field_name
 * @property string|null $view_name
 * @property int $is_sales_trend
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereFieldName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereIsSalesTrend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereModelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TablesField whereViewName($value)
 * @mixin \Eloquent
 */
class TablesField extends Model
{
    
    protected $guarded = [];
	
	
	
}
