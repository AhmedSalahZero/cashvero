<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;


class CalculateDurationService
{
	public function calculateMonthsDurationPerYear(string $itemStartDate, string $studyEndDate, float $studyDuration , bool $convertDatesToDatabaseFormat = false)
	{
		$result = $this->getStudyYears($studyEndDate, $itemStartDate, (($studyDuration * 12) - 1), 'years') ;
		if($convertDatesToDatabaseFormat){
			$result = collect($result)->map(function($item,$key){
				$items  = [];
				foreach($item as $date => $val){
					$items[Carbon::make($date)->format('Y-m-d')] = $val;
				}
				return $items;
			})->toArray();
		
		}
		return $result ;
	}
	public function getStudyYears($end_date, $starting_date, $duration, $type = null)
	{
		//  type = years to return years array for the target section destribution

		$start_date = date("01-m-Y", strtotime($starting_date));

		$start_month = date("m", strtotime($start_date));
		// Years Between Start And End Date
		$getRangeYears = range(gmdate('Y', strtotime($start_date)), gmdate('Y', strtotime($end_date)));

		if ($type == "years_only") {
			return $getRangeYears;
		} elseif ($type == "years") {
			$duration_monthes_in_years = [];

			// If the month is in the duration of the sales plan ; the month value will be 1 else 0
			foreach ($getRangeYears as $key => $year) {

				for ($i = 1; $i <= 12; $i++) {

					$current_date = "01-" . $i . "-" . $year;

					$current_date = date("d-m-Y", strtotime($current_date));
					// && strtotime($current_date) <= strtotime($end_date)
					if (strtotime($current_date) >= strtotime($start_date)) {
						$duration_monthes_in_years[$year][$current_date] = 1;
					} else {
						$duration_monthes_in_years[$year][$current_date] = 0;
					}
				}
			}
			return $duration_monthes_in_years;
		}
	}
}
