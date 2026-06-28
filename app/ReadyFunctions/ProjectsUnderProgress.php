<?php

namespace App\ReadyFunctions;

use App\Helpers\HArr;

class ProjectsUnderProgress
{
	public function calculateForFFE(int $ffeStartDateAsIndex,int $ffeEndDateAsIndex,array $ffeExecutionAndPayment,array $ffeLoanInterestAmount,array $ffeLoanWithdrawalInterestAmounts, $study,int $operationStartDateAsIndex,array $datesAsStringAndIndex,array $datesIndexWithYearIndex,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber):array
	{
		$fixedStartDateISEqualToFixedEndDate = $ffeEndDateAsIndex == $ffeStartDateAsIndex ;
		$transferDateFactor = $fixedStartDateISEqualToFixedEndDate ? 0 : 1 ;
		$studyDurationPerYear = $study->getStudyDurationPerYear($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber,true, true, false);
		$studyDates = $study->getOnlyDatesOfActiveStudy($studyDurationPerYear,$dateIndexWithDate);
		$result = [];
		$beginningBalance = 0;
		$additions = HArr::sumTwoArray($ffeExecutionAndPayment, []);
		
		$finalFFEExecutionDateAsIndex = array_key_last($ffeExecutionAndPayment);
		if(is_null($finalFFEExecutionDateAsIndex)){
			return [];
		}
		$dateBeforeOperation = $operationStartDateAsIndex == 0 ? $operationStartDateAsIndex : $operationStartDateAsIndex- 1;
		$dateBeforeOperation = $dateBeforeOperation < 0 ? 0 : $dateBeforeOperation ;
		$dateBeforeOperation =  $operationStartDateAsIndex >= $ffeEndDateAsIndex ?   $ffeEndDateAsIndex:$dateBeforeOperation;
		$finalCapitalizedInterestDateAsIndex = $dateBeforeOperation >= $finalFFEExecutionDateAsIndex ? $dateBeforeOperation : $finalFFEExecutionDateAsIndex;
		$transferredToFixedAssetDateAsIndex = $finalCapitalizedInterestDateAsIndex;
		$capitalizedInterest = $study->sumTwoArrayUntilIndex($ffeLoanWithdrawalInterestAmounts, $ffeLoanInterestAmount, $finalCapitalizedInterestDateAsIndex);
		foreach ($studyDates as  $dateAsIndex) {
			$result['beginning_balance'][$dateAsIndex] = $beginningBalance;
			$additionsAtDate = $additions[$dateAsIndex] ?? 0;
			$result['additions'][$dateAsIndex] = $additionsAtDate;
			$capitalizedInterestAtDate =  $capitalizedInterest[$dateAsIndex]??0;
			$result['capitalized_interest'][$dateAsIndex] = $capitalizedInterestAtDate;
			$total = $beginningBalance  + $additionsAtDate  +  $capitalizedInterestAtDate;
			$result['total'][$dateAsIndex] = $total;
			$beginningBalance = $total;
			if ($dateAsIndex == ($transferredToFixedAssetDateAsIndex+$transferDateFactor)    ) {
				$result['transferred_date_and_vales'][$dateAsIndex] = $total;
				$result['end_balance'][$dateAsIndex] = $total  -  $result['transferred_date_and_vales'][$dateAsIndex];
				break;
			} else {

				$result['transferred_date_and_vales'][$dateAsIndex] = 0;
				$result['end_balance'][$dateAsIndex] =$total;
			}
		}
		return $result;
	}
}
