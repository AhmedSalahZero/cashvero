<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ContractCashProjection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ContractCashProjection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ContractCashProjection query()
 * @mixin \Eloquent
 */
class ContractCashProjection extends Model
{
	protected $guarded = [];
	protected $casts = [
		'amounts'=>'array',
	];
}
