<?php

namespace App\ReadyFunctions;



class IntervalEndBalancesService
{
	public function __calculate(array $datesAndValues, string $financialYearStartMonth, string $interval, array $dateIndexWithDate,array $keyOfItemsToExclude=[])
	{
		$result = [];
		if ($interval == 'monthly') {
			return $datesAndValues;
		}
		switch($interval) {
			case 'quarterly':
				$result = $this->calculateIntervalEndBalanceQuarterly($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
				break;
				case 'semi-annually':
					$result = $this->calculateIntervalEndBalanceSemiAnnually($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
					break;
					case 'annually':
						$result = $this->calculateIntervalEndBalanceAnnually($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
						break;
				
			}
			return $result;
		
	}

	protected function calculateIntervalEndBalanceQuarterly(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
	
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $date => $value) {
				$dateAsString = is_numeric($date) ? $dateIndexWithDate[$date] : $date;
				$monthNumber = explode('-', $dateAsString)[1];
				if ($monthNumber == 3 ||$monthNumber == '03' || $monthNumber == 6 || $monthNumber == '06' || $monthNumber == 9|| $monthNumber == '09' || $monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsString] = $value ;
				}
			}
			return $result ;
		}
		elseif ($financialYearStartMonth == '04' || $financialYearStartMonth == 'april') {
			foreach ($datesAndValues as $date => $value) {
				$dateAsString = is_numeric($date) ? $dateIndexWithDate[$date] : $date;
				$monthNumber = explode('-', $dateAsString)[1];
				if ($monthNumber == 6 ||$monthNumber == '06' || $monthNumber == 9 || $monthNumber == '09' || $monthNumber == 12|| $monthNumber == '12' 
				 # TODO:
				// || $monthNumber == 3 of next year || $monthNumber == '03 of next year'
				
				) {
					$result[$dateAsString] = $value ;
				}
			}
			return $result ;
		}
	}
	
	protected function calculateIntervalEndBalanceSemiAnnually(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $date => $value) {
				$dateAsString = is_numeric($date) ? $dateIndexWithDate[$date] : $date;
				$monthNumber = explode('-', $dateAsString)[1];
				if ( $monthNumber == 6 || $monthNumber == '06' || $monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsString] = $value ;
				}
			}
			return $result ;
		}
	}
	
	protected function calculateIntervalEndBalanceAnnually(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $date => $value) {
				$dateAsString = is_numeric($date) ? $dateIndexWithDate[$date] : $date;
				$monthNumber = explode('-', $dateAsString)[1];
				if ($monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsString] = $value ;
				}
			}
			return $result ;
		}
	}
	
}
