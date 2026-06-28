<?php

namespace App\Models;

use App\Traits\Models\IsOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property int $company_id
 * @property int $contract_id
 * @property string|null $so_number
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Contract $contract
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder onlyForCompany(int $companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCollectionDays1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCollectionDays2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCollectionDays3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCollectionDays4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCollectionDays5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereEndDate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereEndDate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereEndDate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereEndDate4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereEndDate5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionDays1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionDays2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionDays3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionDays4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionDays5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionPercentage1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionPercentage2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionPercentage3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionPercentage4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereExecutionPercentage5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereSoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereStartDate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereStartDate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereStartDate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereStartDate4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereStartDate5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SalesOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SalesOrder extends Model
{
	use IsOrder ;
	protected $guarded = ['id'];
	public function getOrderColumnName()
	{
		return 'so_number';
	}
	
}
