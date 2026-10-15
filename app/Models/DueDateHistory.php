<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $model_id
 * @property string $due_date التاريخ اللي تم تاجيل الدفع ليه
 * @property numeric $amount هي عباره عن القيمة المتبقه من الفاتورة خلال تاريخ هذا التاجيل بمعني انك لما اجلت الفاتورة كان متبقي عليك الف جنية مثلا تاني مره اجلتها كان باقي عليك500 مثلا
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $model_type وليكن مثلا CustomerInvoice , SupplierInvoice
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\CustomerInvoice|null $customerInvoice
 * @property-read \App\Models\SupplierInvoice|null $supplierInvoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DueDateHistory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DueDateHistory extends Model
{
	protected $guarded = [
		'id'
	];
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	public function customerInvoice()
	{
		return $this->belongsTo(CustomerInvoice::class,'model_id','id')->where('model_type','CustomerInvoice');
	}
	public function supplierInvoice()
	{
		return $this->belongsTo(SupplierInvoice::class,'customer_invoice_id','id')->where('model_type','SupplierInvoice');
	}
	public function getDueDate()
    {
        return $this->due_date ;
    }
	public function getDueDateFormatted()
    {
		$dueDate = $this->getDueDate() ;
        return $dueDate ? Carbon::make($dueDate)->format('d-m-Y') : null   ;
    }
	public function setDueDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['due_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['due_date'] = $year.'-'.$month.'-'.$day;
	}
	public function getDueDateFormattedForDatePicker()
	{
		$date = $this->getDueDate();
		return $date ? Carbon::make($date)->format('m/d/Y') : null;
	}
	public function getAmount()
	{
		return $this->amount ;
	}
	public function getAmountFormatted()
	{
		$amount = $this->getAmount();
		return number_format($amount) ;
	}
	
}
