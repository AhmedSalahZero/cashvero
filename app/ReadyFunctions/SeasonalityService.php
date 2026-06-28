<?php

namespace App\ReadyFunctions;

use stdClass;

class SeasonalityService
{
	
	public static function calculateSeasonalityPercentagePerMonth(array $distributionPercentages , array $yearsWithItsActiveMonths , array $dateIndexWithDate )
	{
		$finalResult = [];
		
		foreach($yearsWithItsActiveMonths as $year => $itsMonths){
			$result = [];
			//$numberOfActiveMonths = array_sum($itsMonths);
			foreach($itsMonths as $dateAsIndex => $zeroOrOne){
				$dateAsString = $dateIndexWithDate[$dateAsIndex];
				$month = explode('-',$dateAsString)[1];
				if(!isset($distributionPercentages[$month])){
					$distributionPercentages = self::monthlyFlatDistribution();
				}
				$seasonalityAtCurrentMonth = $distributionPercentages[$month];
				$result[$dateAsIndex] = $zeroOrOne ? $seasonalityAtCurrentMonth : 0 ;
			}
			$totalSeasonality = array_sum($result);
			foreach($result as $dateAsIndex => $value){
				$finalResult[$dateAsIndex] = $totalSeasonality ? $value / $totalSeasonality : 0;
			}
		}
		return $finalResult;
	}
	
	public static function salesSeasonality(array $revenueItem, array $duration_months_in_years)
	{
		$flatSeasonalityRate = 1 / 12;

		
		$seasonality_type = $revenueItem['seasonality'];

		//Final Array
		$sales_seasonality_rates = [];

		foreach ($duration_months_in_years as $year => $months) {
			   // In case of Flat Seasonality
				if ($seasonality_type == "flat") {
					array_walk($months, function (&$value, $date) use ($flatSeasonalityRate) {
						$value = $flatSeasonalityRate * $value;
					});

					$total_year_percentages = array_sum($months);
					array_walk($months, function (&$value, $date) use ($total_year_percentages, &$sales_seasonality_rates) {
						$sales_seasonality_rates[$date] = $total_year_percentages == 0 ? 0 : $value / $total_year_percentages;
					});
				}
				// In case of Flate Distribute Quarterly
				elseif ($seasonality_type == "distribute_quarterly") {
					$first_quarter = $revenueItem['quarters'][0] ?? 0;
					$second_quarter = $revenueItem['quarters'][1] ?? 0;
					$third_quarter = $revenueItem['quarters'][2] ?? 0;
					$fourth_quarter = $revenueItem['quarters'][3] ?? 0;

					$first_quarter = ($first_quarter / 100) / 3;
					$second_quarter = ($second_quarter / 100) / 3;
					$third_quarter = ($third_quarter / 100) / 3;
					$fourth_quarter = ($fourth_quarter / 100) / 3;
					array_walk($months, function (&$value, $date) use ($first_quarter, $second_quarter, $third_quarter, $fourth_quarter) {
						//First Quarter OF year
						$month = date("m", strtotime($date));
						if ($month == 1 || $month == 2 || $month == 3) {
							$value = $first_quarter * $value;
						}
						//Second Quarter OF year
						if ($month == 4 || $month == 5 || $month == 6) {
							$value = $second_quarter * $value;
						}
						//Third Quarter OF year
						if ($month == 7 || $month == 8 || $month == 9) {
							$value = $third_quarter * $value;
						}
						//Fourth Quarter OF year
						if ($month == 10 || $month == 11 || $month == 12) {
							$value = $fourth_quarter * $value;
						}
					});
					$total_year_percentages = array_sum($months);

					array_walk($months, function (&$value, $date) use ($total_year_percentages, &$sales_seasonality_rates) {
						$sales_seasonality_rates[$date] = $total_year_percentages == 0 ? 0 : $value / $total_year_percentages;
					});
				}
				// In case of Flate Distribute Monthly
				if ($seasonality_type == "distribute_monthly") {

					array_walk($months, function (&$value, $date) use ($revenueItem) {
						$month = date('F', strtotime($date));
						$month_name =  strtolower($month);
						$monthValue = $revenueItem['distribution_months_values'][$month_name] ?? 0;
						$month_rate = $monthValue / 100;
						$value = $value * $month_rate;
					});
					$total_year_percentages = array_sum($months);
					array_walk($months, function (&$value, $date) use ($total_year_percentages, &$sales_seasonality_rates) {
						$sales_seasonality_rates[$date] = $total_year_percentages == 0 ? 0 : $value / $total_year_percentages;
					});
				}
			
		}
		return $sales_seasonality_rates;
	}

	public function years($end_date, $starting_date, $duration, $type = null)
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
	private static function monthlyFlatDistribution():array 
	{
		return ["01"=> 0.083333333, "02"=> 0.083333333, "03"=> 0.083333333, "04"=> 0.083333333, "05"=> 0.083333333, "06"=> 0.083333333, "07"=> 0.083333333, "08"=> 0.083333333, "09"=> 0.083333333, "10"=> 0.083333333, "11"=> 0.083333333, "12"=> 0.083333333];
	}
	
}
