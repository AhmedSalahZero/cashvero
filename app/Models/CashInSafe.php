<?php

namespace App\Models;

use App\Traits\Models\IsCashInSafe;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $money_received_id
 * @property int|null $receiving_branch_id
 * @property string|null $receipt_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $company_id
 * @property-read \App\Models\MoneyReceived $moneyReceived
 * @property-read \App\Models\Branch|null $receivingBranch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereReceiptNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereReceivingBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafe whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashInSafe extends Model
{
	use IsCashInSafe;
    protected $guarded = ['id'];
	
	public function moneyReceived()
	{
		return $this->belongsTo(MoneyReceived::class,'money_received_id');
	}
	
}
