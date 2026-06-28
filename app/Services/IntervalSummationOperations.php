<?php 
namespace App\Services ;

use Carbon\Carbon;

class IntervalSummationOperations 
{

	
	public function sumForInterval(array $dateValues, string $intervalName,$financialYearStartMonth='january',array $dateIndexWithDate = [],bool $resultAsIndex = false  , bool $preserveOriginalDate = false ):array 
	{

		$result = [];
		$periodInterval = $this->getPeriodsForStartMonths($intervalName,$financialYearStartMonth) ; 
		if(!count($dateIndexWithDate) && app()->bound('dateIndexWithDate')){
			$dateIndexWithDate = app('dateIndexWithDate');
		}
	
		$dateAsStringIndex = removeDateFrom($dateIndexWithDate);
		foreach ($dateValues as $dateAsString => $value) {
			$originalDate = $dateAsString;
		
			$dateAsString = is_numeric($dateAsString) ? ($dateIndexWithDate[$dateAsString]??null) : $dateAsString;
			if(is_null($dateAsString)){
				continue;
			}
			$dateObject = Carbon::make($dateAsString);
			$year = $dateObject->format('Y');
			$month = $dateObject->format('m');
			$originalDay = $dateObject->format('d');
			$sumMonth = sprintf("%02d", $this->getSumMonth($month, $periodInterval));
			$resultDay = Carbon::make('01-'.$sumMonth.'-'.$year)->endOfMonth()->format('d');
			if($preserveOriginalDate){
				$resultDay = $originalDay;
			}
			$resultDate = $resultAsIndex ? $dateAsStringIndex[$sumMonth.'-'.$year]:  $resultDay .'-'.$sumMonth.'-'. $year   ;
			$result[$resultDate] = isset($result[$resultDate]) ? $result[$resultDate] + $value  : $value;
			
		}
		
		return $result;
	}
	protected function getSumMonth($month, $mapMonths)
	{

		foreach ($mapMonths as $sumMonth => $sumMonths) {
			if (in_array($month, $sumMonths)) {
				return $sumMonth;
			}
		}
	}
	protected function getPeriodsForStartMonths($interval,$financialYearStartMonth = 'january')
	{
		if($financialYearStartMonth == 'january' || $financialYearStartMonth=="01"){
			if ($interval == 'monthly') {
				return  [
					1 => [1],
					2 => [2],
					3 => [3],
					4 => [4],
					5 => [5],
					6 => [6],
					7 => [7],
					8 => [8],
					9 => [9],
					10 => [10],
					11 => [11],
					12 => [12],
				];
			}
	
			if ($interval == 'quarterly') {
	
				return [
					3 => [1, 2, 3], 6 => [4, 5, 6],9 => [7, 8, 9], 12 => [10, 11, 12]
				];
			}
			if ($interval == 'semi-annually') {
				return [
					6 => [1, 2, 3, 4, 5, 6], 12 => [7, 8, 9, 10, 11, 12]
				];
			}
	
			if ($interval == 'annually') {
				return [
					12 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
				];
			}
				
		}
		
		
		
		if($financialYearStartMonth == 'april' || $financialYearStartMonth=='04'){
			if ($interval == 'monthly') {
				return  [
					1 => [1],
					2 => [2],
					3 => [3],
					4 => [4],
					5 => [5],
					6 => [6],
					7 => [7],
					8 => [8],
					9 => [9],
					10 => [10],
					11 => [11],
					12 => [12],
				];
			}
	
			if ($interval == 'quarterly') {
	
				return [
					6 => [4, 5, 6], 9 => [7, 8, 9],12 => [10, 11, 12], 3 => [1, 2, 3]
				];
			}
			if ($interval == 'semi-annually') {
				return [
					9 => [4, 5, 6, 7,8,9], 3 => [10,11,12,1,2,3]
				];
			}
	
			if ($interval == 'annually') {
				return [
					3 => [4,5,6,7,8,9,10,11,12,1,2,3]
				];
			}
				
		}
		
		
		
		
		if($financialYearStartMonth == 'july' || $financialYearStartMonth=='07'){
			if ($interval == 'monthly') {
				return  [
					1 => [1],
					2 => [2],
					3 => [3],
					4 => [4],
					5 => [5],
					6 => [6],
					7 => [7],
					8 => [8],
					9 => [9],
					10 => [10],
					11 => [11],
					12 => [12],
				];
			}
	
			if ($interval == 'quarterly') {
	
				return [
					9 => [7,8,9], 12 => [10,11,12], 3 => [1,2,3], 6 => [4,5,6]
				];
			}
			if ($interval == 'semi-annually') {
				return [
					12 => [7,8,9,10,11,12], 6 => [1,2,3,4,5,6]
				];
			}
	
			if ($interval == 'annually') {
				return [
					6 => [7,8,9,10,11,12,1,2,3,4,5,6]
				];
			}
				
		}
		
		
		
	}
	
}
