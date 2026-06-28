<?php

namespace App\Dates;


class DurationYears
{
	public function years($financial_start_date, $start_from, $duration, $type = null)
	{

		//  type = years to return years array for the target section destribution
		$duration = $duration - 1;
		$start_date = date("01-m-Y", strtotime(date("Y-m-d", strtotime($financial_start_date)) . " +$start_from  month"));

		$start_month = date("m", strtotime($start_date));
		// $current_year = date("Y", strtotime($current_date));
		$end_date 	= date("Y-m-d", strtotime(date("Y-m-d", strtotime($start_date)) . " +$duration  month"));
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

					if (strtotime($current_date) >= strtotime($start_date) && strtotime($current_date) <= strtotime($end_date)) {
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
