<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $is_contract is contract cash flow (1) or is cash flow (0)
 * @property string|null $report_name
 * @property string $report_interval monthly,  weekly ..etc
 * @property string $start_date
 * @property string $end_date
 * @property array<array-key, mixed> $report_data
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashProjection> $cashProjects
 * @property-read int|null $cash_projects_count
 * @property-read bool|null $cash_projects_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereIsContract($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereReportData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereReportInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereReportName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashflowReport whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashflowReport extends Model
{
	protected $guarded = [];
	protected $casts = [
		'report_data'=>'array'
	];
	public function cashProjects()
	{
		return $this->hasMany(CashProjection::class,'cashflow_report_id');
	}
	public function getName():string 
	{
		return $this->report_name?:'N/A' ;
	}
	public function getReportName():string 
	{
		return $this->getName();
	}
	public function getIntervalName():string 
	{
		return $this->report_interval ;
	}
	public function getStartDate():string 
	{
		return $this->start_date ;
	}
	public function getStartDateFormatted()
	{
		$date = $this->getStartDate() ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
	public function getEndDate():string
	{
		return $this->end_date ;
	}
	public function getEndDateFormatted()
	{
		$date = $this->getEndDate() ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
		
	
}
