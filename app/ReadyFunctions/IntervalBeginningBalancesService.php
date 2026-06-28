<?php

namespace App\ReadyFunctions;

use App\Models\HospitalitySector;

class IntervalBeginningBalancesService
{
	public function __calculate(array $datesAndValues, string $financialYearStartMonth, string $interval,array $dateIndexWithDate,  $hospitalitySector)
	{
		$result = [];
		if ($interval == 'monthly') {
			return $datesAndValues;
		}
		switch($interval) {
			case 'quarterly':
				$result = $this->calculateIntervalBeginningBalanceQuarterly($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
				break;
				case 'semi-annually':
					$result = $this->calculateIntervalBeginningBalanceSemiAnnually($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
					break;
					case 'annually':
						$result = $this->calculateIntervalBeginningBalanceAnnually($datesAndValues, $financialYearStartMonth, $dateIndexWithDate);
						break;
				
			}
			return $result;
		
	}

	protected function calculateIntervalBeginningBalanceQuarterly(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $dateAsIndex => $value) {
				$dateAsString = $dateIndexWithDate[$dateAsIndex];
				$monthNumber = explode('-', $dateAsString)[1];
				if ($monthNumber == 3 ||$monthNumber == '03' ) {
					$result[$dateAsIndex] = $value ;
				}
				if ($monthNumber == 3 ||$monthNumber == '03' || $monthNumber == 6 || $monthNumber == '06' || $monthNumber == 9|| $monthNumber == '09' || $monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsIndex] = $value ;
				}
			}
			return $result ;
		}
	}
	
	protected function calculateIntervalBeginningBalanceSemiAnnually(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $dateAsIndex => $value) {
				$dateAsString = $dateIndexWithDate[$dateAsIndex];
				$monthNumber = explode('-', $dateAsString)[1];
				if ( $monthNumber == 6 || $monthNumber == '06' || $monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsIndex] = $value ;
				}
			}
			return $result ;
		}
	}
	
	protected function calculateIntervalBeginningBalanceAnnually(array $datesAndValues, string $financialYearStartMonth, array $dateIndexWithDate)
	{
		$result  = [];
		if ($financialYearStartMonth == '01' || $financialYearStartMonth == 'january') {
			foreach ($datesAndValues as $dateAsIndex => $value) {
				$dateAsString = $dateIndexWithDate[$dateAsIndex];
				$monthNumber = explode('-', $dateAsString)[1];
				if ($monthNumber == 12 || $monthNumber == '12') {
					$result[$dateAsIndex] = $value ;
				}
			}
			return $result ;
		}
	}
	
}
