<?php

namespace App\ReadyFunctions;

use App\Helpers\HArr;
use App\Helpers\HDate;
use App\Models\Loan;
use App\ReadyFunctions\Date;
use Carbon\Carbon;

class CalculateVariableLoanAtBeginningService
{
	// public function __calculateBasedOnDiffBaseRates( array $baseRatesMapping ,string $loanType, string $loanStartDate, float $loanAmount, float $marginRate, float $tenor, string $installmentPaymentIntervalName,int $installmentPaymentIntervalValue, float $stepUpRate = 0, string $stepUpIntervalName = null, float $stepDownRate = 0, string $stepDownIntervalName = null, float $gracePeriod  = 0 ,int $monthIndex = 0,array $datesAsStringAndIndex = [],array $dateWithDateIndex = []  ):array 
	// {
		
	// 	$currentStartDateAsIndex=$monthIndex ;
	// 	if($loanAmount <= 0){
	// 		return [] ;
	// 	}
	// 	$fixedAtEndResult = [];
	// 	$i = 0 ;
	// 	$previousResult = [];
	// 	$diffInMonths = 0;
	// 	foreach($baseRatesMapping as $currentBaseRateDate => $currentBaseRate){
	// 		$previousLoopDate  = null ;
		
	// 		if($i != 0){
	// 			$diffInMonths=Carbon::make($currentBaseRateDate)->diffInMonths($loanStartDate);
	// 			$currentBaseRateDateAsIndex = $datesAsStringAndIndex[$currentBaseRateDate];
	// 			$previousLoopDateAsIndex = $currentBaseRateDateAsIndex-1;
	// 			$currentStartDateAsIndex  = $previousLoopDateAsIndex ;
	// 			$loanStartDate = $dateWithDateIndex[$currentStartDateAsIndex];
	// 			$tenor = $tenor -$diffInMonths+ $installmentPaymentIntervalValue ;
				
	// 			if($tenor >= 1 ){
	// 				$loanAmount = HArr::getValueOrPrevious($fixedAtEndResult['current_result'][$i-1]['endBalance'], $currentStartDateAsIndex);
	// 			}
	// 		}
	// 			$currentResultArr = [];
	// 			if($tenor >= 1){
	// 				$currentResultArr =$this->__calculate($previousResult,$i,$loanType,$loanStartDate,$loanAmount,$currentBaseRate,$marginRate,$tenor,$installmentPaymentIntervalName,$stepUpRate,$stepUpIntervalName,$stepDownRate,$stepDownIntervalName,$gracePeriod,$currentStartDateAsIndex);
	// 				$previousResult =$currentResultArr['final_result']??[]; 
	// 				$fixedAtEndResult['current_result'][]= $currentResultArr['result']??[]  ;
	// 				$fixedAtEndResult['final_result']= $currentResultArr['final_result']??[]  ;
	// 				$i++ ;
	// 			}
			
	// 	}
	// 	$finalResult = $fixedAtEndResult['final_result']??[] ;
	// 	unset($finalResult['totals']);
	// 	return $finalResult;
	// }
	
public function __calculate($previousResult ,int $indexOfLoop,string $loanType, string $startDate, float $loanAmount,  $baseRate, float $marginRate, float $tenor, string $installmentPaymentIntervalName,string $interestPaymentIntervalName, float $stepUpRate = 0, ?string $stepUpIntervalName = null, float $stepDownRate = 0, ?string $stepDownIntervalName = null, float $gracePeriod  = 0 ,  int $currentStartDateAsIndex = 0 , array $dateIndexWithDate = [])
	{
		if($loanAmount <= 0 ){
			return [];
		}
		$loanFactors = [];
		$principleFactors = [];
		$principlePaymentIntervalValue =  $this->getInstallmentPaymentIntervalValue($installmentPaymentIntervalName);
		$interestPaymentIntervalValue =  $this->getInstallmentPaymentIntervalValue($interestPaymentIntervalName);
		$installmentPaymentIntervalName = 'monthly';
		$datesAsIndexString=HDate::generateDatesBetweenStartDateAndDuration($currentStartDateAsIndex,$startDate,$tenor,$installmentPaymentIntervalName,false);
		$installmentPaymentIntervalValue = $this->getInstallmentPaymentIntervalValue($installmentPaymentIntervalName);
		$datesIndexAndDaysCount =HDate::calculateDaysCountAtBeginning($datesAsIndexString,$installmentPaymentIntervalValue); 
		unset($datesAsIndexString[array_key_last($datesAsIndexString)]);
		$datesAsStringIndex = array_flip($datesAsIndexString);
		$dateIndexWithDate = $datesAsIndexString;
		$dailyPricing = is_numeric($baseRate) ?  (($baseRate + $marginRate) /100)/360 : $baseRate  ;
		 // base rate in array will be added with margin rate then divided by 360 
		$stepRate = Loan::getStepRate($loanType, $stepUpRate, $stepDownRate);
		$stepRate = $stepRate / 100;
		$isWithCapitalization = Loan::isWithCapitalization($loanType);
		$appliedStepName = Loan::getAppliedStepIntervalName($loanType, $stepUpIntervalName, $stepDownIntervalName);
		$appliedStepValue = $this->getAppliedStepIntervalValue($appliedStepName);
		$installmentStartDateAsIndex = $datesAsStringIndex[HDate::getDateAfterIndex($datesAsIndexString,$datesAsStringIndex,$startDate,($gracePeriod)/$installmentPaymentIntervalValue)];
		$endDateAsIndex = array_key_last($datesAsIndexString);
		$stepFactors = [];
		$currentStepFactorCounterValue = 0;
		$currentAppliedStepCounter = 0 ;
		$currentLoanFactor = $loanAmount;
		$currentPrincipleFactor = 0 ;
		foreach($datesIndexAndDaysCount as $currentDateAsIndex => $currentDaysCount){
			
				/**
				 * * calculate Interest Factor 
				 */
				$interestFactors[$currentDateAsIndex]=0;
				$currentInterestFactor = $interestFactors[$currentDateAsIndex] ;
				/**
				 * * Calculate Loan Factors 
				 */
				if(!$isWithCapitalization && $currentDateAsIndex < $installmentStartDateAsIndex  ){
					$currentLoanFactor = $loanAmount;
				}else{
					$currentLoanFactor = $currentLoanFactor + ($currentLoanFactor * $currentInterestFactor);
				}
				$loanFactors[$currentDateAsIndex] = $currentLoanFactor;
				 
				 /**
				  * * Calculate Step Factor
				  */
				  if($currentDateAsIndex < $installmentStartDateAsIndex ){
					$stepFactors[$currentDateAsIndex]= 0 ;
				}else{
					$currentAppliedStepCounter++ ;
					$stepFactors[$currentDateAsIndex] = $currentStepFactorCounterValue;
					if($currentAppliedStepCounter == $appliedStepValue/$installmentPaymentIntervalValue){
						$currentAppliedStepCounter = 0 ;
						$currentStepFactorCounterValue++;
					}
				  }
				  
				  if($currentDateAsIndex < $installmentStartDateAsIndex ){
					$currentPrincipleFactor =  0 ;
				  }
				  else{
					$v = $stepFactors[$currentDateAsIndex] ;
					$currentPrincipleFactor = ($currentPrincipleFactor + (($currentPrincipleFactor-((pow((1 + $stepRate), ($v))))) * $currentInterestFactor) - (1 * pow((1 + $stepRate), ($v))));
				  }
				  $principleFactors[$currentDateAsIndex] = $currentPrincipleFactor; 
				  /**
				   * * Calculate Installment Factor
				   */
		
		}
		$principleAmounts = $this->calculatePrincipleAmount($installmentPaymentIntervalValue,$loanFactors,$principleFactors, $stepRate, $installmentStartDateAsIndex, $endDateAsIndex, $tenor, $installmentPaymentIntervalValue, $appliedStepValue);
		$loanScheduleResult = $this->calculateLoanScheduleResult($installmentPaymentIntervalValue,$datesIndexAndDaysCount,$loanType, $loanAmount, $principleAmounts,$dailyPricing,$principlePaymentIntervalValue,$interestPaymentIntervalValue,$dateIndexWithDate);
	
		
		if($indexOfLoop == -1){
		
			return [
				'final_result'=>$loanScheduleResult,
			];
		}
		$mergedResult = $indexOfLoop == 0 ? $loanScheduleResult :$previousResult ;
	
		$mergedResult = $loanScheduleResult;

		
			foreach($previousResult as $key => $currentArr){
				if($key == 'totals'){
					continue;
				}

				$firstKey = array_key_first($loanScheduleResult[$key]);
				unset($loanScheduleResult[$key][$firstKey]);
				$mergedResult[$key]=HArr::mergeTwoAssocArr($previousResult[$key],$loanScheduleResult[$key]);
			
		}

		
		return [
			'result'=>$loanScheduleResult ,
			'final_result'=>$mergedResult ,
		];
	
	}

	
	public function getInstallmentPaymentIntervalValue($installmentPayment):int
	{
		switch($installmentPayment) {
			case 'monthly':
				return 1;
			case 'quarterly':
				return 3;
			case 'semi annually':
				return 6;
			default:
				return 1;
		}
	}

	protected function getAppliedStepIntervalValue($appliedStepIntervalName):int
	{
	
		switch($appliedStepIntervalName) {
			case 'quarterly':
				return 3;
			case 'semi annually':
				return 6;
			case 'annually':
				return 12;
			default:
				return 12;
		}
	}


	protected function defaultDateFormat():string
	{
		return 'd-m-Y';
	}

	protected function addMonths(string $loanStartDateDay,string $date, int $duration):Carbon
	{
		return Carbon::make((new Date())->addMonths($loanStartDateDay,$date, $duration,0,1,2));
	}

	protected function getDateFormatted(Carbon $date):string
	{
		return $date->format($this->defaultDateFormat());
	}
	

	protected function calculatePrincipleAmount(int $intervalValue,array $loanFactors,array $principleFactory, float $stepRate, int $principleStartDateAsIndex, int $endDateAsIndex, float $tenor, int $principlePaymentIntervalValue, int $appliedStepValue )
	{
	
		$principlesAmounts = [];

		$loanFactoryAtEndDate = $loanFactors[$endDateAsIndex];
		
		$principleFactorAtEndDate = $principleFactory[$endDateAsIndex];

		$principleAmount = $loanFactoryAtEndDate / ($principleFactorAtEndDate * -1);

		$principlesAmounts[$principleStartDateAsIndex] = $principleAmount;

		
		for ($i=1 ; $i <= ($tenor / $principlePaymentIntervalValue) ; $i++) {
			$loopDateAsIndex = $principleStartDateAsIndex ;
				$stepVal = ($appliedStepValue / $principlePaymentIntervalValue ) ;
				if ($i != 1 && ($i %$stepVal ) == 1 ) {
					$principleAmount = $principleAmount * ((pow((1 + $stepRate), 1)));
				} else {
					$principleAmount = $principleAmount;
				}
				$principlesAmounts[$loopDateAsIndex]=$principleAmount;
				$principleStartDateAsIndex = $loopDateAsIndex+$intervalValue;
		}
		return $principlesAmounts;
	}

	protected function calculateLoanScheduleResult(int $intervalValue , array $datesIndexAndDaysCount,string $loanType, float $loanAmount, array $principleAmount, $dailyPricing,int $principlePaymentIntervalValue,int $interestPaymentIntervalValue , array $dateIndexWithDate =[])
	{
		$loanScheduleResult = [];
		$loanScheduleResult['totals']['totalSchedulePayment'] = 0;
		$loanScheduleResult['totals']['totalPrincipleAmount'] = 0;
		$loanScheduleResult['totals']['totalInterestAmount'] = 0;
		$isWithoutCapitalization =  Loan::isWithoutCapitalization($loanType);
		$firstLoop = true ;
		$intervalPrincipleAmount=0;
		$intervalInterestAmount=0;
	
		$loopIndex = 1 ;
		foreach ($datesIndexAndDaysCount as $dateAsIndex=>$currentDaysCount) {
			$previousDate = $dateAsIndex-$intervalValue;
			$dateAsString = $dateIndexWithDate[$dateAsIndex] ?? null ;
			$i = $dateAsIndex ; 
			$currentPricing = is_array($dailyPricing) ? ($dailyPricing[$dateAsString]??0) : $dailyPricing ;
			$loanScheduleResult['beginning'][$i] =  $firstLoop ? $loanAmount : $loanScheduleResult['endBalance'][$previousDate]??0;
			$loanScheduleResult['interestAmount'][$i] = $loanScheduleResult['beginning'][$i] * $currentPricing *  $currentDaysCount ;
			$loanScheduleResult['totals']['totalInterestAmount'] += $loanScheduleResult['interestAmount'][$i];
			$principleAmountAtIndex =$principleAmount[$i] ?? 0;
			$intervalPrincipleAmount += $principleAmountAtIndex;
			$isPrincipleQuarter = $loopIndex % $principlePaymentIntervalValue == 0 ;
			$loanScheduleResult['principleAmount'][$i] = $isPrincipleQuarter ? $intervalPrincipleAmount : 0 ;
			if($isPrincipleQuarter){
				$intervalPrincipleAmount = 0;
			}
			$intervalInterestAmount += $loanScheduleResult['interestAmount'][$i];
			$isInterestQuarter = $loopIndex % $interestPaymentIntervalValue == 0 ;
			$loanScheduleResult['interestPayment'][$i] = $isInterestQuarter ? $intervalInterestAmount : 0 ;
			if($isInterestQuarter){
				$intervalInterestAmount = 0;
			}
			$loanScheduleResult['schedulePayment'][$i] = $isWithoutCapitalization && $loanScheduleResult['principleAmount'][$i] == 0 ? $loanScheduleResult['interestAmount'][$i] : $loanScheduleResult['principleAmount'][$i] +$loanScheduleResult['interestPayment'][$i] ;
			$loanScheduleResult['totals']['totalSchedulePayment'] = $loanScheduleResult['totals']['totalSchedulePayment'] + $loanScheduleResult['schedulePayment'][$i];
			$loanScheduleResult['totals']['totalPrincipleAmount'] += $loanScheduleResult['principleAmount'][$i];
			$loanScheduleResult['endBalance'][$i] = $loanScheduleResult['beginning'][$i]  -$loanScheduleResult['principleAmount'][$i];
			$loanScheduleResult['endBalance'][$i] = $loanScheduleResult['endBalance'][$i] < 1 && $loanScheduleResult['endBalance'][$i] > -1 ? 0 : $loanScheduleResult['endBalance'][$i];
			$firstLoop = false ;
			$loopIndex++;
		}
		return $loanScheduleResult;
	}
	

	
}
