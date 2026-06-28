<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class CollectionPolicyService
{
	public function applyCollectionPolicy($hasCollectionPolicy, ?string $collectionPolicyType, $collectionPolicyValue, array $dateValue)
	{
		$collections = [];
		if (!$hasCollectionPolicy) {
			// reset Collection Policy
			foreach ($dateValue as $date => $value) {
				$collections[$date] = 0;
			}
			
		} elseif ($collectionPolicyType == 'customize' && is_array($collectionPolicyValue)) {
			
			$ratesWithDueDays = $this->formatRatesWithDueDays($collectionPolicyValue);
			foreach ($dateValue as $currentDate => $target) {
				foreach ($ratesWithDueDays as $dueDay => $rate) {
					$rate =  $rate / 100;
					$actualMonthsNumbers = $dueDay < 30 ? 0 : round((($dueDay) / 30));
					$date = (Carbon::make($currentDate))->addMonths($actualMonthsNumbers);
					$month = $date->format('m');
					$year = $date->format('Y');
					$day = $date->format('d');
					$fullDate = $year  . '-' . $month . '-' . $day;
					$collections[$fullDate] = ($target * $rate) + ($collections[$fullDate] ?? 0);
				}
			}
		} elseif ($collectionPolicyType == 'system_default' && $collectionPolicyValue && is_string($collectionPolicyValue)) {
			$collections = $this->sumForInterval($dateValue, $collectionPolicyValue);
		}
		return $collections;
	}
	public function applyMultiCustomizedCollectionPolicy($dueDayWithRates,  array $dateValue)
	{
			$collections = [];
		
			foreach ($dateValue as $currentDateAsIndex => $target) {
				foreach ($dueDayWithRates as $dueDay => $rate) {
					$rate =  $rate / 100;
					$actualMonthsNumbers = $dueDay < 30 ? 0 : round((($dueDay) / 30));
					$newDateAsIndex = $currentDateAsIndex+$actualMonthsNumbers;
					// $month = $date->format('m');
					// $year = $date->format('Y');
					// $day = $date->format('d');
					// $fullDate = $year  . '-' . $month . '-' . $day;
					$collections[$newDateAsIndex] = ($target * $rate) + ($collections[$newDateAsIndex] ?? 0);
				}
			}
		return $collections;
	}
	protected function getNumberOfMonthsForInterval(string $intervalName)
	{
		if($intervalName == 'monthly' || $intervalName == 'cash'){
			return 1 ;
		}
		if($intervalName  == 'quarterly'){
			return 3 ;
		}
		if($intervalName == 'semi-annually'){
			return 6;
		}
		if($intervalName == 'annually'){
			return 12 ;
		}
		throw new \Exception('Custom Error .. Invalid Interval Name'.$intervalName);
	}
	protected function sumForInterval(array $dateValues, string $intervalName)
	{
		$result = [];
		$max = $this->getNumberOfMonthsForInterval($intervalName);
		$i = 1 ;
		$currentSum = 0 ;
		$currentDateIndex = null;
		foreach($dateValues as $dateAsString => $value){
			if($i == 1){
				$currentDateIndex = $dateAsString ;
			}
			$currentSum += $value ; 
			$result[$currentDateIndex] = $currentSum;
			if($i % $max === 0){
				$i = 0 ;
				$currentSum = 0 ;
			}
			$i++;
		}
		return $result;
	
	}

	// protected function getPeriodsForStartMonths($interval)
	// {

	// 	if ($interval == 'monthly') {
	// 		return  [
	// 			1 => [1],
	// 			2 => [2],
	// 			3 => [3],
	// 			4 => [4],
	// 			5 => [5],
	// 			6 => [6],
	// 			7 => [7],
	// 			8 => [8],
	// 			9 => [9],
	// 			10 => [10],
	// 			11 => [11],
	// 			12 => [12],
	// 		];
	// 	}
	// 	if ($interval == 'quarterly') {

	// 		return [
	// 			1 => [1, 2, 3], 4 => [4, 5, 6], 7 => [7, 8, 9], 10 => [10, 11, 12]
	// 		];
	// 	}
	// 	if ($interval == 'semi-annually') {
	// 		return [
	// 			1 => [1, 2, 3, 4, 5, 6], 7 => [7, 8, 9, 10, 11, 12]
	// 		];
	// 	}

	// 	if ($interval == 'annually') {
	// 		return [
	// 			1 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
	// 		];
	// 	}
	// 	return [];
	// }

	protected function formatRatesWithDueDays(array $ratesAndDueDays): array
	{
		$result = [];
		
		foreach ($ratesAndDueDays['due_in_days'] ?? [] as $index => $dueDay) {
			$rate = $ratesAndDueDays['rate'][$index] ?? 0;
			if ($rate) {
				if (isset($result[$dueDay])) {
					$result[$dueDay] += $rate;
				} else {
					$result[$dueDay] = $rate;
				}
			}
		}
		return $result;
	}


	// protected function getSumMonth($month, $mapMonths)
	// {

	// 	foreach ($mapMonths as $sumMonth => $sumMonths) {
	// 		if (in_array($month, $sumMonths)) {
	// 			return $sumMonth;
	// 		}
	// 	}
	// }
}
