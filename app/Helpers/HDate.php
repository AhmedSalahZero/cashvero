<?php 
namespace App\Helpers;

use App\ReadyFunctions\Date;
use Carbon\Carbon;

class HDate 
{
	public static function formatDateFromDatePicker(?string $date):?string
	{
		$originDate = $date;
		if(!$date){
			return null ;
		}
		$date = explode('/',$date);
		if(isset($date[1])){
			return $date[2] .'-'.$date[1] . '-'.$date[0];
		}
		return $originDate ;
	}	
	public static function generateUniqueDateTimeForModel(string $fullClassName , string $columnName , string $dateTime , array $additionalConditions)
	{
		return self::searchForUnique($fullClassName  , $columnName , $dateTime , $additionalConditions);
	}
	public static function searchForUnique(string $fullClassName , string $columnName , string $dateTime , array $additionalConditions)
	{
		$query = $fullClassName::where($columnName,$dateTime);
		foreach($additionalConditions as $condition){
			$additionalColumnName = $condition[0];
			$operation = $condition[1];
			$value = $condition[2];
			$query->where($additionalColumnName,$operation,$value);
		}
		$isExist = $query->exists() ;
		if($isExist){
			$dateTime = Carbon::make($dateTime)->addSecond()->format('Y-m-d H:i:s');
			return self::searchForUnique($fullClassName  , $columnName , $dateTime , $additionalConditions);
		}
		return $dateTime;
	}
	public static function allDatesGreaterThanOrEqual(array $dates , ?string $checkDate = null)
	{
		if(is_null($checkDate)){
			return false ;
		}
		foreach($dates as $date){
			$currentDate = Carbon::make($date) ;
			if(is_null($currentDate)){
				return false ;
			}
			$lessThan = $currentDate->lessThan(Carbon::make($checkDate));
			if($lessThan){
				return false ;
			}
		}
		return true ;
	}
	public static function generateStartDateAndEndDateBetween(string $startDate , string $endDate):array{
		
		$result  = [];
		$dates = generateDatesBetweenTwoDates(Carbon::make($startDate),Carbon::make($endDate));
		$currentStartDate = null;
		foreach($dates as $startDate){
			$startDate = $currentStartDate ?: $startDate;
			$endDateOfCurrentMonth = Carbon::make($startDate)->endOfMonth()->format('Y-m-d');
			if(Carbon::make($endDateOfCurrentMonth)->greaterThan(Carbon::make($endDate))){
				$endDateOfCurrentMonth = $endDate ;
			}
			$result[] = ['start_date'=>$startDate,'end_date'=>$endDateOfCurrentMonth];
			$currentStartDate = Carbon::make($endDateOfCurrentMonth)->addDay()->format('Y-m-d');
		}
		return $result;
		
	}
	public static function generateEndOfMonthsDatesBetweenTwoDates(Carbon $startDate , Carbon $endDate)
	{
		$startDate = $startDate->endOfMonth();
		$endDate = $endDate->endOfMonth();
		$currentDate = $startDate;
		$intervalDates = [
			$currentDate->format('Y-m-d')
		];
		
		while ($endDate->greaterThan($currentDate)){
			$intervalDates[] = $currentDate->addMonthWithNoOverflow()->endOfMonth()->format('Y-m-d');
		}
		return $intervalDates;

	}
	public static function generateDatesBetweenStartDateAndDuration(int $currentStartDateAsIndex,string $startDate , int $duration,string $intervalName , bool $isAtEnd = true)
	{
		$dateService = new Date;
		
		$intervalValue = 12 ;
		switch($intervalName) {
			case 'monthly':
				$intervalValue = 1 ;
				break;
				case 'quarterly':
					$intervalValue = 3;
					break;
					case 'semi annually':
						$intervalValue = 6 ;
						break;
		}
		
		$startDateDay =explode('-',$startDate)[2];
		$result=[];
		if($isAtEnd){
			for($i =0 ; $i <= ($duration/$intervalValue); $i++  ){
				$result[$currentStartDateAsIndex]=$dateService->addMonths($startDateDay,$startDate,$i*$intervalValue);
				$currentStartDateAsIndex+=$intervalValue;
			}
		}else{
			for($i =0 ; $i <= ($duration/$intervalValue); $i++  ){
				$result[$currentStartDateAsIndex]=$dateService->addMonths($startDateDay,$startDate,$i*$intervalValue);
				$currentStartDateAsIndex+=$intervalValue;
			}
		}
		return $result;
		
		
	}
	

	public static function getNthKeyAfter($array, $specificKey, $n)
{
    // Get all keys from the array
    $keys = array_keys($array);
    
    // Find the position of the specific key
    $keyPosition = array_search($specificKey, $keys);
    
    // Check if the specific key exists
    if ($keyPosition === false) {
        return null; // Key not found
    }
    
    // Calculate the position of the nth key after
    $targetPosition = $keyPosition + $n;
    
    // Check if the target position exists
    if (isset($keys[$targetPosition])) {
        return $keys[$targetPosition];
    }
    
    return null; // No nth key exists
}
	public static function getDateAfterIndex(array $datesAsIndexString , array $datesAsStringIndex  , string $date , int $numberOfShifts)
	{
		$index =$datesAsStringIndex[$date];
		$nextIndex = self::getNthKeyAfter($datesAsIndexString, $index, $numberOfShifts) ;
		
		return $datesAsIndexString[$nextIndex]??null;
	}
	public static function calculateDaysCountAtEnd(array $items,int $intervalValue,?int $currentDaysCount = null):array{
		
		$currentDayCount = 0 ; 
		$dayCounts = [];
		$secondDate = null ;
		$loopIndex = 0 ;
		foreach($items as $currentDateIndex => $dateAsString){
			
			if($loopIndex == 0){
				$dayCounts[$currentDateIndex] = $currentDayCount ;
				$loopIndex++;
			}
			else{
				if(!is_null($currentDaysCount)){
					$dayCounts[$currentDateIndex] =$currentDaysCount;
					continue;   
				}
				$secondDate = $dateAsString  ;
				$firstDate = $items[$currentDateIndex-$intervalValue];
				$secondDateTime  = strtotime($secondDate.' 00:00:00');
				$firstDateTime  = strtotime($firstDate.' 00:00:00');
				$result = $secondDateTime-$firstDateTime;
				$result = round(($result) / (60 * 60 * 24));
				$dayCounts[$currentDateIndex] =   (int)$result;
			}
		}
		return $dayCounts;
		
	}
	public static function calculateDaysCountAtBeginning(array $items,int $intervalValue,?int $currentDaysCount = null):array{
		$currentDayCount = 0 ; 
		$dayCounts = [];
		$secondDate = null ;
		$loopIndex = 0 ;
		foreach($items as $currentDateIndex => $dateAsString){
			
			
				if(!is_null($currentDaysCount)){
					$dayCounts[$currentDateIndex] =$currentDaysCount;
					continue;   
				}
				$secondDate = $items[$currentDateIndex+$intervalValue]??null  ;
				
				$firstDate = $dateAsString ;
				if(!is_null($secondDate)){
					$secondDateTime  = strtotime($secondDate.' 00:00:00');
				$firstDateTime  = strtotime($firstDate.' 00:00:00');
				$result = $secondDateTime-$firstDateTime;
				$result = round(($result) / (60 * 60 * 24));
				$dayCounts[$currentDateIndex] =   (int)$result;
				}
				
		}
		return $dayCounts;
		
	}
	
	public static function convertDateIndexArrayToDateString(array $payload, array $dateIndexWithDate){
		$result = [];
		foreach($payload as $dateIndex => $value){
			$dateAsString = $dateIndexWithDate[$dateIndex];
			$result[$dateAsString] = $value;
		}
		return $result;
	}
	public static function convertDatesIndexToDateIndexes(array $dateIndexes, array $dateIndexWithDate){
		$result = [];
		foreach($dateIndexes as $index=>$dateIndex ){
			$dateAsString = $dateIndexWithDate[$dateIndex];
			$result[$dateIndex] = $dateAsString;
		}
		return $result;
	}
	public static function getValueFromDateAsYearAndMonth(array $items, string $date):float
	{
		$date = explode('-', $date);
		$year = $date[0];
		$month = $date[1];
		foreach($items as $dateAsString=>$value){
			$date = explode('-', $dateAsString);
			$dateYear = $date[0];
			$dateMonth = $date[1];
			if($dateYear == $year && $dateMonth == $month){
				return $value;
			}
		}
		return 0;
	}
	
public static function convertStringKeysToIndexes(array $items, array $datesAsIndexAndString)
{
    $result = [];
    foreach ($items as $dateAsString => $value) {
        $dateAsIndex = array_search($dateAsString, $datesAsIndexAndString);
        if ($dateAsIndex === false) {
            continue;
        }
        $result[$dateAsIndex] = $value ;
    }
    return $result ;
}


public static function getMinDateOfWeek(array $dateAndWeek, int $weekNo, int $year)
{
    $items = [];
    foreach ($dateAndWeek as $date => $currentWeek) {
        $currentYear = Carbon::make($date)->year ;
        if ($weekNo == $currentWeek && $currentYear == $year) {
            $items[$date]=$currentWeek ;
        }
    }
    return [
        'start_date'=>array_key_first($items),
        'end_date'=>array_key_last($items),
    ];
}


public static function getDayNumberBetweenDates(int $firstDateYear, Carbon $secondDate)
{

    $week = 1 ;
    $dates = generateDatesBetweenTwoDates(Carbon::make('01-01-'.$firstDateYear), $secondDate, 'addDay');
    $weeks = [];
    $day  =1 ;

    foreach ($dates as $index =>$dateAsString) {
        if (Carbon::make($dateAsString)->month == '01' && Carbon::make($dateAsString)->day == '01') {
            $day = 1 ;
            $week = 1;
        }
        // if(Carbon::make($dateAsString)->month == '12' && Carbon::make($dateAsString)->day == '31' || Carbon::make($dateAsString)->month == '12' && Carbon::make($dateAsString)->day == '30') {
        //     $week = 52;
        // }
        $weeks[$dateAsString] = $week ;
        // if($day % 7 == 0) {
        $week ++ ;
        // }
        $day++;
    }
    return $weeks  ;
}

public static function getMonthNumberBetweenDates(int $firstDateYear, Carbon $secondDate)
{
    $datesFormatted = [];
    $dates = generateDatesBetweenTwoDates(Carbon::make('01-01-'.$firstDateYear), $secondDate, 'addDay');
    foreach ($dates as $date) {
        $datesFormatted[$date] = Carbon::make($date)->month ;
    }
    return $datesFormatted;
}


public static function getWeekNumberBetweenDates(int $firstDateYear, Carbon $secondDate)
{

    $week = 1 ;
    $dates = generateDatesBetweenTwoDates(Carbon::make('01-01-'.$firstDateYear), $secondDate, 'addDay');
    $weeks = [];
    $day  =1 ;

    foreach ($dates as $index =>$dateAsString) {
        if (Carbon::make($dateAsString)->month == '01' && Carbon::make($dateAsString)->day == '01') {
            $day = 1 ;
            $week = 1;
        }
        if (Carbon::make($dateAsString)->month == '12' && Carbon::make($dateAsString)->day == '31' || Carbon::make($dateAsString)->month == '12' && Carbon::make($dateAsString)->day == '30') {
            $week = 52;
        }
        $weeks[$dateAsString] = $week ;
        if ($day % 7 == 0) {
            $week ++ ;
        }
        $day++;
    }
    return $weeks  ;
}

}
