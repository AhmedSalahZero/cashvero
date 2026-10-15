<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $assignment_date
 * @property int|null $overdraft_against_assignment_of_contract_id
 * @property int|null $customer_id
 * @property int|null $contract_id
 * @property float|null $lending_rate
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Contract|null $contract
 * @property-read \App\Models\Partner|null $customer
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContract|null $overdraftAgainstAssignmentOfContract
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereAssignmentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereLendingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereOverdraftAgainstAssignmentOfContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LendingInformationAgainstAssignmentOfContract whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LendingInformationAgainstAssignmentOfContract extends Model
{
    protected $guarded = ['id'];
	
	public static function boot()
	{
		parent::boot();
		static::updated(function(self $lendingInformationAgainstAssignmentOfContract){
			// $lendingInformationAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContract->triggerChangeOnContracts();
		});
		static::deleting(function(self $lendingInformationAgainstAssignmentOfContract){
			$lendingInformationAgainstAssignmentOfContract->contract->update([
				'overdraft_against_assignment_of_contract_id'=>null ,
				'updated_at'=>now()
			]);
		});
		
		static::deleted(function(self $lendingInformationAgainstAssignmentOfContract){
			$lendingInformationAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContract->triggerChangeOnContracts();
		});
	}
	public function overdraftAgainstAssignmentOfContract()
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContract::class,'overdraft_against_assignment_of_contract_id','id');
	}
	
	public function getId(){
		return $this->id ; 
	}
	public function getCustomerId()
	{
		return $this->customer_id;
	}	
	public function customer()
	{
		return $this->belongsTo(Partner::class,'customer_id','id');
	}
	public function getCustomerName()
	{
		return $this->customer ? $this->customer->getName():__('N/A');
	}
	
	public function contract()
	{
		return $this->belongsTo(Contract::class,'contract_id','id');
	}
	public function getContractStartDate()
	{
		return $this->contract ? $this->contract->getStartDate():__('N/A');
		
	}
	public function getContractEndDate()
	{
		return $this->contract ? $this->contract->getEndDate():__('N/A');
	}
	public function getAssignmentEndDate()
	{
		return $this->assignment_date;
	}
	public function getContractAmount()
	{
		return $this->contract ? $this->contract->getAmount():0;
	}
	public function getContractAmountFormatted()
	{
		return number_format($this->getContractAmount());
	}
	public function getLendingAmount()
	{
		return $this->getLendingRate() / 100 * $this->getContractAmount();
	}
	public function getLendingAmountFormatted():string 
	{
		return number_format($this->getLendingAmount());
	}
	public function getContractName()
	{
		return $this->contract ? $this->contract->getName():__('N/A');
	}
	public function getLendingRate()
	{
		return $this->lending_rate ?: 0 ;
	}
	public function getLendingRateFormatted()
	{
		return number_format($this->getLendingRate()) ;
	}
	
}
