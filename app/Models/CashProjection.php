<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $is_contract is contract cash flow (1) or is cash flow (0)
 * @property string|null $name
 * @property string $type in or out
 * @property array<array-key, mixed>|null $amounts
 * @property int|null $cashflow_report_id
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereAmounts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereCashflowReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereIsContract($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashProjection whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashProjection extends Model
{
	protected $guarded = [];
	protected $casts = [
		'amounts'=>'array',
	];
}
