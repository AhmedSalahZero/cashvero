<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class ContractPaymentService
{
	public function __calculate(float $totalCostAfterContingencyRate ,array $executionAmounts ,int $startDateAsIndex , float $downPaymentRateOne, array $ratesWithDueDays,array $dateIndexWithDate, array $dateWithDateIndex)
	{
		// $ratesWithDueDays = $this->formatRatesWithDueDays($collectionPolicyValue);
		$downPaymentOneAmount = $totalCostAfterContingencyRate * ($downPaymentRateOne /100);
		$collections[$startDateAsIndex] =$downPaymentOneAmount ;
		$dateValue = $executionAmounts ;
		foreach ($dateValue as $currentDate => $target) {
			foreach ($ratesWithDueDays as $dueDay => $rate) {
				$rate =  $rate / 100;
				$actualMonthsNumbers = $dueDay < 30 ? 0 : round((($dueDay) / 30));
				$dateAsString =  is_numeric($currentDate) ?   $dateIndexWithDate[$currentDate] :$currentDate ;
				$currentDateObject = Carbon::make($dateAsString);
				$date =$currentDateObject->addMonths($actualMonthsNumbers);
				$month = $date->format('m');
				$year = $date->format('Y');
				$day = $date->format('d');
				$fullDate =$year . '-' . $month . '-' . $day;
				$dateIndex =  $dateWithDateIndex[$fullDate]??null;
				if(is_null($dateIndex)){
					continue;
				}
				$collections[$dateIndex] = ($target * $rate) + ($collections[$dateIndex] ?? 0);
			}
		}
		return $collections ;
	}
	
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
	
}
