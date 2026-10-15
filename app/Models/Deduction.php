<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCreatedAt;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction onlyForCompany(int $companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Deduction whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Deduction extends Model
{
	use HasBasicStoreRequest,HasCreatedAt;
	const DEDUCTIONS = 'deductions';
	
	protected $guarded = ['id'];
	public function getId()
	{
		return $this->id;
	}
	public function getName()
	{
		return $this->name;
	}
	
	public function scopeOnlyForCompany(Builder $builder , int $companyId)
	{
		return $builder->where('company_id',$companyId);
	}
	
	public static function calculateAmountInMainCurrency($amount , string $date , string $invoiceCurrency , $invoiceExchangeRate  ,Company $company):array 
	{
		$mainFunctionCurrency = $company->getMainFunctionalCurrency();
		$date = Carbon::make($date)->format('Y-m-d');
		if($invoiceCurrency == $mainFunctionCurrency){
			return [
				'amount_in_main_currency'=>$amount,
				'amount_in_invoice_exchange_rate'=>$amount
			];
		}
		$foreignExchangeRate  = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($invoiceCurrency,$mainFunctionCurrency,$date,$company->id );
		return [
			'amount_in_main_currency'=>$amount * $foreignExchangeRate,
			'amount_in_invoice_exchange_rate'=> $amount * $invoiceExchangeRate
		] ;
	}

}
