<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCreatedAt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int|null $journal_id
 * @property int|null $odoo_id
 * @property string|null $odoo_outbound_cheque_payment_method_id
 * @property string|null $odoo_inbound_cheque_payment_method_id
 * @property string|null $odoo_outbound_transfer_payment_method_id
 * @property string|null $odoo_inbound_transfer_payment_method_id
 * @property string|null $odoo_code
 * @property string|null $name
 * @property string|null $currency
 * @property int|null $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereJournalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooInboundChequePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooInboundTransferPaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooOutboundChequePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereOdooOutboundTransferPaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBranch whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class CashVeroBranch extends Model
{
	const BRANCHES = 'branches';
	protected $table ='branch';
	use HasCreatedAt,HasBasicStoreRequest;
    protected $dates = [
    ];


    protected $guarded = [];



	public function getId(){
		return $this->id ;
	}
	public function getName()
	{
		return $this->name ;
	}
	
	public function scopeOnlyCompany(Builder $query,$companyId){
		return $query->where('company_id',$companyId);
	}
	public function getOdooCode():?string 
	{
		return $this->odoo_code ;
	}
	
	public function getCurrencyName()
	{
		return $this->currency;
	}
}
