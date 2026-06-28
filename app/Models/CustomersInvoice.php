<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\StaticBoot;
/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomersInvoice withoutTrashed()
 * @mixin \Eloquent
 */
class CustomersInvoice extends Model
{
    use SoftDeletes,StaticBoot;
    protected $guarded = [];
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id);
    }

}
