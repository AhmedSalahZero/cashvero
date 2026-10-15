<?php

namespace App\Models;

use App\Traits\Models\IsOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $contract_id
 * @property string|null $po_number purchase order number
 * @property numeric|null $amount
 * @property string|null $start_date_1
 * @property string|null $end_date_1
 * @property numeric|null $execution_percentage_1
 * @property int|null $execution_days_1
 * @property int|null $collection_days_1
 * @property string|null $start_date_2
 * @property string|null $end_date_2
 * @property numeric|null $execution_percentage_2
 * @property int|null $execution_days_2
 * @property int|null $collection_days_2
 * @property string|null $start_date_3
 * @property string|null $end_date_3
 * @property numeric|null $execution_percentage_3
 * @property int|null $execution_days_3
 * @property int|null $collection_days_3
 * @property string|null $start_date_4
 * @property string|null $end_date_4
 * @property numeric|null $execution_percentage_4
 * @property int|null $execution_days_4
 * @property int|null $collection_days_4
 * @property string|null $start_date_5
 * @property string|null $end_date_5
 * @property numeric|null $execution_percentage_5
 * @property int|null $execution_days_5
 * @property int|null $collection_days_5
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PoAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read bool|null $allocations_exists
 * @property-read \App\Models\Contract|null $contract
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeIssuance> $letterOfGuaranteeIssuances
 * @property-read int|null $letter_of_guarantee_issuances_count
 * @property-read bool|null $letter_of_guarantee_issuances_exists
 * @property-write mixed $end_date1
 * @property-write mixed $end_date2
 * @property-write mixed $end_date3
 * @property-write mixed $end_date4
 * @property-write mixed $end_date5
 * @property-write mixed $start_date1
 * @property-write mixed $start_date2
 * @property-write mixed $start_date3
 * @property-write mixed $start_date4
 * @property-write mixed $start_date5
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder onlyForCompany(int $companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCollectionDays1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCollectionDays2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCollectionDays3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCollectionDays4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCollectionDays5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereEndDate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereEndDate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereEndDate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereEndDate4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereEndDate5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionDays1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionDays2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionDays3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionDays4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionDays5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionPercentage1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionPercentage2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionPercentage3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionPercentage4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereExecutionPercentage5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereStartDate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereStartDate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereStartDate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereStartDate4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereStartDate5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PurchaseOrder whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class PurchaseOrder extends Model
{
	protected $guarded = ['id'];
	
	use IsOrder ;
	
	public function getNumber()
	{
		return $this->po_number;
	}
	
	public function letterOfGuaranteeIssuances()
	{
		return $this->hasMany(LetterOfGuaranteeIssuance::class , 'purchase_order_id','id');
	}
	public function scopeOnlyForCompany(Builder $builder , int $companyId)
	{
		return $builder->where('company_id',$companyId);
	}
	public function getOrderColumnName()
	{
		return 'po_number';
	}	
	public function allocations()
	{
		return $this->hasMany(PoAllocation::class,'purchase_order_id','id');
	}
	// public function storeNewAllocation(array $allocations)
	// {
	// 	foreach($allocations as $invoiceId => $allocationsArr){
	// 		foreach($allocationsArr as $index => $allocationArr){
	// 			$partnerId = $allocationArr['partner_id'] ?? 0 ;
	// 			$purchaseOrderId = $allocationArr['purchase_order_id'] ?? 0 ;
	// 			$allocationAmount = number_unformat($allocationArr['allocation_amount'] ?? 0) ;
	// 			$allocationPercentage = number_unformat($allocationArr['allocation_percentage'] ?? 0) ;
	// 			if($allocationAmount>0){
	// 				$this->allocations()->create([
	// 					'allocation_amount'=>$allocationAmount,
	// 					'allocation_percentage'=>$allocationPercentage,
	// 					'purchase_order_id'=>$purchaseOrderId,
	// 					'partner_id'=>$partnerId ,
	// 					'invoice_id'=>$invoiceId
	// 				]);
	// 			}
	// 		}
	// 	}
	// }
	
	
}
