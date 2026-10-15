<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class Date
{
	protected function getMonthEndDate(float $yearOfEndDate, float $monthOfEndDate, string $dateFormat = 'd-m-Y'):string
	{
		return Carbon::create($yearOfEndDate, $monthOfEndDate)->lastOfMonth()->format($dateFormat);
		// return 01-12-20202 for example
	}

	public function addMonths(string $loanStartDateDay , string $date, int $durationInMonths, $dayIndex = 0, $monthIndex = 1, $yearIndex=2):?string
	{
		$month = explode('-', $date)[$monthIndex];
		$year = explode('-', $date)[$yearIndex];
		$dates = [];

		if ($loanStartDateDay == 31) {
			$newDate =$this->getMonthEndDate($year, $month);
			
			$newDate = Carbon::make($newDate)->addMonthsNoOverflow($durationInMonths)
			->endOfMonth()
			->format('Y-m-d');
	
			
			return $newDate;
		} elseif ($loanStartDateDay == 30 || $loanStartDateDay == 29) {
			$newDate =$this->getMonthEndDate($year, $month);
			
			$newDate = Carbon::make($newDate)->addMonthsNoOverflow($durationInMonths)
			->endOfMonth()
			->format('Y-m-d');
			$resultDateMonth = explode('-',$newDate)[1];
			if($resultDateMonth != '02'){
				$resultYear = explode('-',$newDate)[2];
				return $loanStartDateDay .'-'.$resultDateMonth . '-'.$resultYear;
			}
			return $newDate;
		}
		
		else {
			//0.00001
			return Carbon::make($date)->addMonthsNoOverflow($durationInMonths)->format('Y-m-d');
			// $dates = $this->generateDatesBetweenTwoDates(Carbon::make($date), Carbon::make($date)->addMonthsNoOverflow($durationInMonths));
			
		}
		// if (count($dates)) {
			
			
			
		// 	$lastKey = array_key_last($dates);
		
		// 	return $dates[$lastKey];
		// }

		// return null;
	}

	// public function generateDatesBetweenTwoDatesWithLastDay(Carbon $start_date, Carbon $end_date, $method = 'addMonthsNoOverflow',$debug  = false)
	// {
	// 	$dates = [];
	// 	$start_date = $start_date->setTime(0,0,0,0);

	// 	for ($date = $start_date->copy(); $date->lte($end_date); $date->{$method}()) {
	// 		$year = Carbon::make($date)->format('Y');
	// 		$month = Carbon::make($date)->format('m');
	// 		$dates[] =$this->getMonthEndDate($year, $month);
			
	// 	}
	// 	return $dates;
	// }

	public function generateDatesBetweenTwoDates(Carbon $start_date, Carbon $end_date, $method = 'addMonthsNoOverflow', $format = 'd-m-Y', ?string $day = null)
	{
		
		$dates = [];

		for ($date = $start_date->copy(); $date->lte($end_date); $date->{$method}()) {
			$dates[] = $date->format($format);
		}

		return $dates;
	}

	protected function replaceAllDaysInMonthsExceptWith($exceptMonthNumber, $replaceWithDay, array $dates)
	{
		// return
		$newDates = [];
		foreach ($dates as $date) {
			$day = null;
			$month = explode('-', $date)[1];
			$year = explode('-', $date)[2];
			if ($month == $exceptMonthNumber) {
				$day = explode('-', $this->getMonthEndDate($year, $month))[0];
			} else {
				$day = $replaceWithDay;
			}
			$newDate = $day . '-' . $month . '-' . $year;


			$newDates[]=$newDate;
		}

		return $newDates;
	}
}
