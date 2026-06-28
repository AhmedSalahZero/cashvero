<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $financial_institution_id في حاله لو كانت null يبقي all
 * @property string $odoo_code
 * @property int $odoo_id
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InterestRevenueAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class InterestRevenueAccount extends Model
{
	// use HasOdooPaymentMethod;
	protected $guarded = ['id'];
	public function getOdooId():int 
	{
		// if(is_null($this->odoo_id)){
		// 	throw new \Exception('Odoo Code For Branch ' . $this->getName() . ' Not Found');
		// }
		return $this->odoo_id?:0;
	}
	public function financialInstitution()
	{
		return $this->belongsTo(FinancialInstitution::class,'financial_institution_id','id');
	}
	public function getFinancialInstitutionId()
	{
		return $this->financial_institution_id;
	}
	public function getOdooCode()
	{
		return $this->odoo_code ;
	}
	// public function getJournalId():int 
	// {
	// 	return $this->journal_id ;
	// }
	
}
