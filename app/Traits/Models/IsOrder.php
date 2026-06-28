<?php 

namespace App\Traits\Models;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait IsOrder {
	public function getId()
	{
		return $this->id ;
	}
	
	public function getNumber()
	{
		return $this->so_number ;
	}
	public function getAmount()
	{
		return $this->amount;
	}
	public function getAmountFormatted()
	{
		return number_format($this->getAmount(),0) ;
	}
	public function getExecutionPercentage(int $index){
		return $this['execution_percentage_'.$index];
	}
	public function getActualAmount(int $index){
	
		return $this->getExecutionPercentage($index) / 100 * $this->getAmount();
	}
	// public function getExecutionDays(int $index){
	// 	return $this['execution_days_'.$index];
	// }
	public function getCollectionDays(int $index){
		return $this['collection_days_'.$index];
	}
	
	public function getStartDate(int $index)
	{
		return $this['start_date_'.$index]; 
	}
	
	public function getStartDateFormatted(int $index)
	{
		$date = $this->getStartDate($index) ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
	public function getEndDate(int $index)
	{
		return $this['end_date_'.$index]; 
	}
	
	public function getEndDateFormatted(int $index)
	{
		$date = $this->getEndDate($index) ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
	
	public function setStartDate1Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date_1'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date_1'] = $year.'-'.$month.'-'.$day;
	}	
	
	public function setStartDate2Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date_2'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date_2'] = $year.'-'.$month.'-'.$day;
	}	
	public function setStartDate3Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date_3'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date_3'] = $year.'-'.$month.'-'.$day;
	}
	public function setStartDate4Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date_4'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date_4'] = $year.'-'.$month.'-'.$day;
	}
	public function setStartDate5Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date_5'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date_5'] = $year.'-'.$month.'-'.$day;
	}
	
	
	
	
	
	
	
	
	public function setEndDate1Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date_1'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date_1'] = $year.'-'.$month.'-'.$day;
	}	
	
	public function setEndDate2Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date_2'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date_2'] = $year.'-'.$month.'-'.$day;
	}	
	public function setEndDate3Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date_3'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date_3'] = $year.'-'.$month.'-'.$day;
	}
	public function setEndDate4Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date_4'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date_4'] = $year.'-'.$month.'-'.$day;
	}
	public function setEndDate5Attribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date_5'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date_5'] = $year.'-'.$month.'-'.$day;
	}
	
	public function contract()
	{
		return $this->belongsTo(Contract::class,'contract_id','id');
	}
	
	public function scopeOnlyForCompany(Builder $builder , int $companyId)
	{
		return $builder->where('company_id',$companyId);
	}
}
