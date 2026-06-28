<?php

namespace App\ReadyFunctions;

use App\Models\HospitalitySector;
use App\ReadyFunctions\SCurveService;
use App\ReadyFunctions\StraightMethodService;

class ConstructionExecutionAndPayment
{
	public function __calculate(float $hardConstructionCost, float $hardContingencyRate, int $hardConstructionStartDateAsIndex, int $duration, string $hardExecutionMethod,array $dateIndexWithDate,  $hospitalitySector):array 
	{
		$hardTotalConstructionCost = $hardConstructionCost * (1+ ($hardContingencyRate / 100));
		return $this->calculateConstructionExecution($hardTotalConstructionCost, $hardExecutionMethod, $hardConstructionStartDateAsIndex, $duration,$dateIndexWithDate, $hospitalitySector);
	}

	protected function calculateConstructionExecution(float $hardTotalConstructionCost, string $hardExecutionMethod, int $hardConstructionStartDateAsIndex, int $duration,array $dateIndexWithDate,  $hospitalitySector = null):array
	{
		switch($hardExecutionMethod) {
			case 'straight-line':
				$hardConstructionStartDateAsString = $dateIndexWithDate[$hardConstructionStartDateAsIndex];
				$straightMethodService = new StraightMethodService();
				return $straightMethodService->calculateStraightAmount($hardTotalConstructionCost, $hardConstructionStartDateAsString, $duration);
			case 's-curve':
				$sCurveService = new SCurveService();
				$startDateAsString =$hospitalitySector->getHardConstructionStartDateAsString();
				return $sCurveService->__calculate($hardTotalConstructionCost, $duration,$startDateAsString);
			default :
			return [];
		}
	}
	
	protected function calculateEquityFundingAmount(float $totalHardConstructionCost, float $hardEquityFundingRate)
	{
		return $totalHardConstructionCost * ($hardEquityFundingRate / 100);
	}
	
	protected function calculateTotalHardConstructionCost(float $hardConstructionCost,float $hardContingencyRate )
	{
		return  $hardConstructionCost * (1+ ($hardContingencyRate / 100));
	}
	public function calculateHardConstructionEquityPayment(array $hardConstructionPayments, float $hardConstructionCost, float $hardContingencyRate, float $hardEquityFundingRate)
	{
		$totalHardConstructionCost = $this->calculateTotalHardConstructionCost($hardConstructionCost,$hardContingencyRate);
		$equityFundingAmount = $this->calculateEquityFundingAmount($totalHardConstructionCost, $hardEquityFundingRate);
		$hardConstructionEquityPayment = [];
		$remainingEquityFunding = [];
		$firstLoop = true;
		foreach ($hardConstructionPayments as $dateIndex => $hardConstructionPaymentValue) {
			$nextDateIndex = getNextDate($hardConstructionPayments,$dateIndex);
			if ($firstLoop) {
				$remainingEquityFunding[$dateIndex] = $equityFundingAmount;
				$firstLoop= false;
			}
			if ($remainingEquityFunding[$dateIndex] >= $hardConstructionPaymentValue) {
				$hardConstructionEquityPayment[$dateIndex] = $hardConstructionPaymentValue;
				if($nextDateIndex){
					$remainingEquityFunding[$nextDateIndex] = $remainingEquityFunding[$dateIndex] -$hardConstructionEquityPayment[$dateIndex];
				}
			} else {
				$hardConstructionEquityPayment[$dateIndex] = $remainingEquityFunding[$dateIndex];
				if($nextDateIndex){
					$remainingEquityFunding[$nextDateIndex] = $remainingEquityFunding[$dateIndex] -$hardConstructionEquityPayment[$dateIndex];
				}
			}
		}
		return $hardConstructionEquityPayment;
	}
	
	public function calculateHardConstructionLoanWithdrawal(array $hardConstructionPayments, float $hardConstructionCost,float $hardContingencyRate, float $equityFundingRate)
	{
		$totalHardConstructionCost = $this->calculateTotalHardConstructionCost($hardConstructionCost,$hardContingencyRate);
		
		$equityFundingAmount = $this->calculateEquityFundingAmount($totalHardConstructionCost, $equityFundingRate);
		$hardConstructionLoanWithdrawal = [];
		$isFirstNestedIf = true;
		foreach ($hardConstructionPayments as $index=>$landPayment) {
			$previousIndex = getPreviousDate($hardConstructionPayments, $index);
			$equityPaymentBalance[$index]  = $equityFundingAmount - $landPayment;
			$equityFundingAmount = $equityPaymentBalance[$index];
			if ($equityPaymentBalance[$index] < 0) {
				if ($isFirstNestedIf) {
					$hardConstructionLoanWithdrawal[$index] = $equityPaymentBalance[$index] * -1;
				} else {
					$hardConstructionLoanWithdrawal[$index] =  ($equityPaymentBalance[$index] - $equityPaymentBalance[$previousIndex]) * -1;
				}
				$isFirstNestedIf = false;
			}
		}
		if (array_sum($hardConstructionLoanWithdrawal) > -1 && array_sum($hardConstructionLoanWithdrawal) < 1) {
			return [];
		}

		return $hardConstructionLoanWithdrawal;
	}
	

}
