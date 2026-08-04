<?php

namespace App\Models;

use App\Services\Api\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $from_currency
 * @property string $to_currency
 * @property string $date
 * @property numeric $exchange_rate
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereFromCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereToCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\ForeignExchangeRate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ForeignExchangeRate extends Model
{
	/** @var array<string, float|int> */
	private static array $exchangeRateRequestMemo = [];

	protected $guarded = [
		'id'
	];

	public static function beginRequestMemo(): void
	{
		self::$exchangeRateRequestMemo = [];
	}

	public static function endRequestMemo(): void
	{
		self::$exchangeRateRequestMemo = [];
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	
	public function getDate()
    {
        return $this->date ;
    }
	public function getDateFormatted()
    {
		$date = $this->getDate() ;
        return $date ? Carbon::make($date)->format('d-m-Y') : null   ;
    }
	public function setDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['date'] = $year.'-'.$month.'-'.$day;
	}
	public function getDateFormattedForDatePicker()
	{
		$date = $this->getDate();
		return $date ? Carbon::make($date)->format('m/d/Y') : null;
	}
	public function getExchangeRate()
	{
		return $this->exchange_rate?:1 ;
	}
	public function getExchangeRateFormatted()
	{
		return number_format($this->getExchangeRate()) ;
	}
	public function getFromCurrency()
	{
		return $this->from_currency; 
	}
	public function getToCurrency()
	{
		return $this->to_currency; 
	}
	public static function getExchangeRateForCurrencyAndClosestDate(string $fromCurrency , string $toCurrency , string $closestDate,int $companyId , $exchangeRates = null){
		// $exchangeRates = 
		$orderBy = 'orderByDesc';
		if(is_null($exchangeRates)){
			$exchangeRates = self::get();
			$orderBy= 'sortByDesc';
		}
	
		$exchangeRate = $exchangeRates->where('company_id',$companyId)->where('from_currency',$fromCurrency)->where('to_currency',$toCurrency)->where('date','<=',$closestDate)
			->sortByDesc('date')
			->first();
	
		return $exchangeRate ? $exchangeRate->getExchangeRate() : 1 ;
	}
	public static function getExchangeRateAt($receivingCurrency,$mainFunctionalCurrency,$receivingDate,$companyId,$foreignExchangeRates)
	{
		if ($receivingCurrency === $mainFunctionalCurrency) {
			return 1;
		}

		$memoKey = implode('|', [
			(string) $companyId,
			(string) $receivingCurrency,
			(string) $mainFunctionalCurrency,
			(string) $receivingDate,
		]);

		if (array_key_exists($memoKey, self::$exchangeRateRequestMemo)) {
			return self::$exchangeRateRequestMemo[$memoKey];
		}

		$rate = self::getExchangeRateForCurrencyAndClosestDate(
			$receivingCurrency,
			$mainFunctionalCurrency,
			$receivingDate,
			$companyId,
			$foreignExchangeRates,
		);
		self::$exchangeRateRequestMemo[$memoKey] = $rate;

		return $rate;
	}

	/**
	 * ! نفس getExchangeRateAt لكن يرجع 1 لو المستدعي لم يمرر العملة الوظيفية أو الأسعار
	 * ! تستخدمها دوال التوقعات القديمة اللي بارامتراتها اختيارية عشان ما تكسرش أي مستدعي
	 */
	public static function getExchangeRateAtOrOne($receivingCurrency,$mainFunctionalCurrency,$receivingDate,$companyId,$foreignExchangeRates)
	{
		if (empty($mainFunctionalCurrency) || is_null($foreignExchangeRates)) {
			return 1;
		}

		return self::getExchangeRateAt($receivingCurrency,$mainFunctionalCurrency,$receivingDate,$companyId,$foreignExchangeRates);
	}

	public static function importOdooExchangeRates(Company $company)
	{
		
		$exchangeRateService = new ExchangeRateService($company);
		$mainFunctionCurrency = $company->getMainFunctionalCurrency();
		$oldForeignExchangeRates = ForeignExchangeRate::where('company_id',$company->id)->get();
		foreach(getCurrenciesForSuppliersAndCustomers($company->id) as $currencyName){
			if(is_null($currencyName)){
				continue;
			}
			if($currencyName != $mainFunctionCurrency){
				$newExchangeRates = $exchangeRateService->getExchangeRates($currencyName) ;
				
					$newRates = $newExchangeRates['rates']??[];
					$secondaryCurrency = $newExchangeRates['currency']??null;
					foreach($newRates as $newRateArr){
						$date = $newRateArr['date'];
						$rate = $newRateArr['direct_rate'];
						$oldForeignExchangeRateAtDate = $oldForeignExchangeRates->where('date',$date)->where('to_currency',$mainFunctionCurrency)->where('from_currency',$secondaryCurrency)->first();
						if($oldForeignExchangeRateAtDate){
							$oldForeignExchangeRateAtDate->update([
								'exchange_rate'=>$rate
							]);
						}else{
							ForeignExchangeRate::create([
								'date'=>$date ,
								'exchange_rate'=>$rate ,
								'from_currency'=>$secondaryCurrency,
								'to_currency'=>$mainFunctionCurrency,
								'company_id'=>$company->id ,
							]);
						}
						
					}
				
				
			}
		}
	}
	
}
