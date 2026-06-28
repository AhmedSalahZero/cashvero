<?php

namespace App\ReadyFunctions;

use App\Helpers\HArr;
use App\Helpers\HDate;
use App\Models\Loan;
use App\Models\NonBankingService\FixedAsset;
use App\Models\NonBankingService\Study;
use App\ReadyFunctions\Date;
use Arr;
use Carbon\Carbon;

class CalculateFixedLoanAtEndService
{
    
	 public function __calculateBasedOnDiffBaseRates(array $baseRatesMapping, string $loanType, string $loanStartDate, float $loanAmount, float $marginRate, float $tenor, string $installmentPaymentIntervalName, int $installmentPaymentIntervalValue, float $stepUpRate = 0, ?string $stepUpIntervalName = null, float $stepDownRate = 0, ?string $stepDownIntervalName = null, float $gracePeriod  = 0, int $monthIndex = 0, array $datesAsStringAndIndex = [], array $dateWithDateIndex = [] , $daysCount = null):array
    {
      
        $currentStartDateAsIndex=$monthIndex ;
        $originalTenor = $tenor;
        if ($loanAmount <= 0) {
            return [] ;
        }
		
        $fixedAtEndResult = [];
        $i = 0 ;
        $previousResult = [];
		
        foreach ($baseRatesMapping as $currentBaseRateDate => $currentBaseRate) {
            if ($i != 0) {
                $currentBaseRateDateAsIndex = $datesAsStringAndIndex[$currentBaseRateDate];
				$gracePeriod = 0;
				if ($tenor >= 1){
					$currentStartDateAsIndex = HArr::getNowOrNextNonZeroValue($fixedAtEndResult['current_result'][$i-1]['schedulePayment']??[],$currentBaseRateDateAsIndex);
					$loanAmount =$fixedAtEndResult['current_result'][$i-1]['endBalance'][$currentStartDateAsIndex]??0;
					}
                $loanStartDate = $dateWithDateIndex[$currentStartDateAsIndex]??null;
			
				if(is_null($loanStartDate)){
					continue;
				}
                $tenor = $originalTenor -($currentStartDateAsIndex - $monthIndex);
            }
            $currentResultArr = [];
            if ($tenor >= 1) {
				$currentResultArr =$this->__calculate($previousResult, $i, $loanType, $loanStartDate, $loanAmount, $currentBaseRate, $marginRate, $tenor, $installmentPaymentIntervalName, $stepUpRate, $stepUpIntervalName, $stepDownRate, $stepDownIntervalName, $gracePeriod, $currentStartDateAsIndex , $daysCount);
                $previousResult =$currentResultArr['final_result']??[];
				
                $fixedAtEndResult['current_result'][]= $currentResultArr['result']??[]  ;
                $fixedAtEndResult['final_result']= $currentResultArr['final_result']??[]  ;
				
                $i++ ;
            }
            
        }
        $finalResult = $fixedAtEndResult['final_result']??[] ;
		
        unset($finalResult['totals']);
		
		if ($installmentPaymentIntervalName != 'monthly') {
            $finalResult = $this->extendPerMonth($finalResult, $installmentPaymentIntervalValue);
        }
        return $finalResult;
    }
	
    protected function getInstallmentDates($currentStartDateAsIndex, $startDate, $tenor, $installmentPaymentIntervalName, $gracePlusInstallmentIntervalValue, $currentDaysCount)
    {
        $datesAsIndexString=HDate::generateDatesBetweenStartDateAndDuration($currentStartDateAsIndex, $startDate, $tenor, $installmentPaymentIntervalName);
        $datesAsStringIndex = array_flip($datesAsIndexString);
        $installmentPaymentIntervalValue = $this->getInstallmentPaymentIntervalValue($installmentPaymentIntervalName);
        $datesIndexAndDaysCount =HDate::calculateDaysCountAtEnd($datesAsIndexString, $installmentPaymentIntervalValue, $currentDaysCount);
        $installmentStartDateAsIndex = $datesAsStringIndex[HDate::getDateAfterIndex($datesAsIndexString, $datesAsStringIndex, $startDate, $gracePlusInstallmentIntervalValue/$installmentPaymentIntervalValue)] ;
        return $installmentStartDateAsIndex;
                
                
    }
    
    
    public function __calculate($previousResult, int $indexOfLoop, string $loanType, string $startDate, float $loanAmount, $baseRate, float $marginRate, float $tenor, string $installmentPaymentIntervalName, float $stepUpRate = 0, ?string $stepUpIntervalName = null, float $stepDownRate = 0, ?string $stepDownIntervalName = null, float $gracePeriod  = 0, $currentStartDateAsIndex=0, ?int $currentDaysCount = null, ?array $pricingPerMonths = null)
    {
		
        if ($loanAmount <= 0) {
            return [] ;
        }
        $loanFactors = [];
        $installmentFactors = [];
        $datesAsIndexString=HDate::generateDatesBetweenStartDateAndDuration($currentStartDateAsIndex, $startDate, $tenor, $installmentPaymentIntervalName);
        
        
        $datesAsStringIndex = array_flip($datesAsIndexString);
        $installmentPaymentIntervalValue = $this->getInstallmentPaymentIntervalValue($installmentPaymentIntervalName);
        $datesIndexAndDaysCount =HDate::calculateDaysCountAtEnd($datesAsIndexString, $installmentPaymentIntervalValue, $currentDaysCount);
        $interestFactors=[];
        $currentPricing =  ($baseRate + $marginRate) /100  ;
        $stepRate = Loan::getStepRate($loanType, $stepUpRate, $stepDownRate);
        $stepRate = $stepRate / 100;
        $isWithCapitalization = Loan::isWithCapitalization($loanType);
        $appliedStepName = Loan::getAppliedStepIntervalName($loanType, $stepUpIntervalName, $stepDownIntervalName);
        $appliedStepValue = $this->getAppliedStepIntervalValue($appliedStepName);
        $gracePlusInstallmentIntervalValue = (int) ($gracePeriod+$installmentPaymentIntervalValue);
        $dateAfterIndex = HDate::getDateAfterIndex($datesAsIndexString, $datesAsStringIndex, $startDate, $gracePlusInstallmentIntervalValue/$installmentPaymentIntervalValue);
        $installmentStartDateAsIndex = $datesAsStringIndex[$dateAfterIndex] ;
        $endDateAsIndex = array_key_last($datesAsIndexString);
        $stepFactors = [];
        $currentStepFactorCounterValue = 0;
        $currentAppliedStepCounter = 0 ;
        $currentLoanFactor = $loanAmount;
        $currentInstallmentFactor = 0 ;
        $previousPricing = 0 ;
        foreach ($datesIndexAndDaysCount as $currentDateAsIndex => $currentDaysCount) {

            $currentPricing = is_null($pricingPerMonths) ? $currentPricing : ($pricingPerMonths[$currentDateAsIndex]??$previousPricing);
            $previousPricing = $currentPricing ;
            /**
             * * calculate Interest Loan Factor
             */
                
                
            $interestFactors[$currentDateAsIndex]=($currentPricing / 360) * $currentDaysCount;
            $currentInterestFactor = $interestFactors[$currentDateAsIndex] ;
            /**
             * * Calculate Loan Factors
             */
            
            if (!$isWithCapitalization && $currentDateAsIndex < $installmentStartDateAsIndex) {
                $currentLoanFactor = $loanAmount;
            } else {
                $currentLoanFactor = $currentLoanFactor + ($currentLoanFactor * $currentInterestFactor);
            }
            
            $loanFactors[$currentDateAsIndex] = $currentLoanFactor;
                
            /**
             * * Calculate Step Factor
             */
            if ($currentDateAsIndex < $installmentStartDateAsIndex) {
                $stepFactors[$currentDateAsIndex]= 0 ;
            } else {
                $currentAppliedStepCounter++ ;
                $stepFactors[$currentDateAsIndex] = $currentStepFactorCounterValue;
                if ($currentAppliedStepCounter == $appliedStepValue/$installmentPaymentIntervalValue) {
                    $currentAppliedStepCounter = 0 ;
                    $currentStepFactorCounterValue++;
                }
            }
                  
            if ($currentDateAsIndex < $installmentStartDateAsIndex) {
                $currentInstallmentFactor =  0 ;
            } elseif ($currentDateAsIndex == $installmentStartDateAsIndex) {
                $currentInstallmentFactor = -1 ;
            } else {
                $v = $stepFactors[$currentDateAsIndex] ;
                $currentInstallmentFactor = ($currentInstallmentFactor + ($currentInstallmentFactor * $currentInterestFactor) - (1 * pow((1 + $stepRate), ($v))));
            }
            $installmentFactors[$currentDateAsIndex] = $currentInstallmentFactor;
            /**
             * * Calculate Installment Factor
             */
        
        }
        
        $installmentAmounts = $this->calculateInstallmentAmount($installmentPaymentIntervalValue, $loanFactors, $installmentFactors, $stepRate, $installmentStartDateAsIndex, $endDateAsIndex, $tenor, $installmentPaymentIntervalValue, $appliedStepValue);

        $loanScheduleResult = $this->calculateLoanScheduleResult($installmentPaymentIntervalValue, $datesIndexAndDaysCount, $loanType, $loanAmount, $interestFactors, $installmentAmounts);
        $loanScheduleResult['accured_interest'] = [];
        // $loanScheduleResult = HArr::replacePreviousValues($loanScheduleResult);
        if ($indexOfLoop == -1) {
            
            if ($installmentPaymentIntervalName != 'monthly') {
                $loanScheduleResult = $this->extendPerMonth($loanScheduleResult, $installmentPaymentIntervalValue);
            }
            return [
                'final_result'=>$loanScheduleResult,
            ];
        }
		
        $mergedResult = $indexOfLoop == 0 ? $loanScheduleResult :$previousResult ;
		
        $mergedResult = $loanScheduleResult;

        
    
        foreach ($previousResult as $key => $currentArr) {
                
            if ($key == 'totals') {
                continue;
            }

            $firstKey = array_key_first($loanScheduleResult[$key]);
		
            unset($loanScheduleResult[$key][$firstKey]);
            $mergedResult[$key]=HArr::mergeTwoAssocArr($previousResult[$key], $loanScheduleResult[$key]);
       
            
                
            
        }
	
		if(!count($mergedResult)){
			
		}
        return [
            'result'=>$loanScheduleResult ,
            'final_result'=>$mergedResult ,
        ];
    
    }

    protected function extendPerMonth(array $loanScheduleResult, $installmentPaymentIntervalValue):array
    {
        $result = [];
        $startDateAsIndex = array_key_first($loanScheduleResult['endBalance']??[]);
        $endDateAsIndex = array_key_last($loanScheduleResult['endBalance']??[]);
        $previousBeginningBalance = Arr::first($loanScheduleResult['beginning']??[]) ;
        $result['beginning'] = [];
        $result['endBalance'] = [];
        // $currentInterestAmount = $loanScheduleResult['schedulePayment'][$currentDateAsIndex];
        for ($currentDateAsIndex  =$startDateAsIndex ; $currentDateAsIndex <=$endDateAsIndex ; $currentDateAsIndex++) {
            $currentInterestAmount = $loanScheduleResult['interestAmount'][$currentDateAsIndex]??0;
            if ($currentInterestAmount > 0) {
                for ($i = $currentDateAsIndex ; $i> $currentDateAsIndex - $installmentPaymentIntervalValue ; $i--) {
                    $result['interestAmount'][$i] = $currentInterestAmount / $installmentPaymentIntervalValue;
                }
            }
            $previousEndBalanceValue = $result['endBalance'][$currentDateAsIndex-1]??0;
            // if (count($result['endBalance'])) {
                
            // }
            $currentEndBalance=$loanScheduleResult['endBalance'][$currentDateAsIndex]??null ;
            $result['schedulePayment'][$currentDateAsIndex] = $loanScheduleResult['schedulePayment'][$currentDateAsIndex]??0;
            $result['principleAmount'][$currentDateAsIndex] = $loanScheduleResult['principleAmount'][$currentDateAsIndex]??0;
            //        $result['no_securitization'][$currentDateAsIndex] = $loanScheduleResult['no_securitization'][$currentDateAsIndex]??0;
            $result['endBalance'][$currentDateAsIndex] = isset($loanScheduleResult['endBalance'][$currentDateAsIndex]) ? $currentEndBalance :$previousEndBalanceValue;
            $result['beginning'][$currentDateAsIndex] = $previousBeginningBalance ;
            $previousBeginningBalance = $result['endBalance'][$currentDateAsIndex];
        }
        $currentInterestAmountArr = $result['interestAmount']??[];
        ksort($currentInterestAmountArr);
        $dateAsIndexes = array_keys($result['beginning']);
        if (app()->bound('dateIndexWithDate')) {
            $result['accured_interest']=Loan::calculateSettlementStatement($dateAsIndexes, $loanScheduleResult['interestAmount'], $result['interestAmount']??[], 0, false, true);
        }
        $result['interestAmount'] = $currentInterestAmountArr ;
        return $result;
    }
    public function getInstallmentPaymentIntervalValue($installmentPayment):int
    {
        switch ($installmentPayment) {
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
    
        switch ($appliedStepIntervalName) {
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

    protected function addMonths(string $loanStartDateDay, string $date, int $duration):Carbon
    {
        return Carbon::make((new Date())->addMonths($loanStartDateDay, $date, $duration, 0, 1, 2));
    }

    protected function getDateFormatted(Carbon $date):string
    {
        return $date->format($this->defaultDateFormat());
    }
    

    protected function calculateInstallmentAmount(int $intervalValue, array $loanFactors, array $installmentFactory, float $stepRate, int $installmentStartDateAsIndex, int $endDateAsIndex, float $tenor, int $installmentPaymentIntervalValue, int $appliedStepValue)
    {
    
        $installmentsAmounts = [];
        
        
        $loanFactoryAtEndDate = $loanFactors[$endDateAsIndex];
        
        $installmentFactorAtEndDate = $installmentFactory[$endDateAsIndex];
        
        $installmentAmount = $loanFactoryAtEndDate / ($installmentFactorAtEndDate * -1);

        $installmentsAmounts[$installmentStartDateAsIndex] = $installmentAmount;

        
        for ($i=1 ; $i <= ($tenor / $installmentPaymentIntervalValue) ; $i++) {
            $loopDateAsIndex = $installmentStartDateAsIndex ;
            $stepVal = ($appliedStepValue / $installmentPaymentIntervalValue) ;
            if ($i != 1 && ($i %$stepVal) == 1) {
                $installmentAmount = $installmentAmount * ((pow((1 + $stepRate), 1)));
            } else {
                $installmentAmount = $installmentAmount;
            }
            $installmentsAmounts[$loopDateAsIndex]=$installmentAmount;
            $installmentStartDateAsIndex = $loopDateAsIndex+$intervalValue;
        }
        
        return $installmentsAmounts;
    }

    protected function calculateLoanScheduleResult(int $intervalValue, array $datesIndexAndDaysCount, string $loanType, float $loanAmount, array $interestFactor, array $installmentAmount)
    {
        $loanScheduleResult = [];
        $loanScheduleResult['totals']['totalSchedulePayment'] = 0;
        $loanScheduleResult['totals']['totalPrincipleAmount'] = 0;
        $loanScheduleResult['totals']['totalInterestAmount'] = 0;
        $isWithoutCapitalization =  Loan::isWithoutCapitalization($loanType);
        $firstLoop = true ;
        
        foreach ($datesIndexAndDaysCount as $dateAsIndex => $currentDaysCount) {
            $previousDate = $dateAsIndex-$intervalValue;
            $i = $dateAsIndex ;
            $loanScheduleResult['beginning'][$i] =  $firstLoop ? $loanAmount : $loanScheduleResult['endBalance'][$previousDate]??0;
            $loanScheduleResult['interestAmount'][$i] = $loanScheduleResult['beginning'][$i] *   $interestFactor[$i] ;
            $loanScheduleResult['totals']['totalInterestAmount'] += $loanScheduleResult['interestAmount'][$i];
            $installmentAmountAtIndex =$installmentAmount[$i] ?? 0;
            $loanScheduleResult['schedulePayment'][$i] = $isWithoutCapitalization && $installmentAmountAtIndex == 0 ? $loanScheduleResult['interestAmount'][$i] : $installmentAmountAtIndex;
            $loanScheduleResult['totals']['totalSchedulePayment'] = $loanScheduleResult['totals']['totalSchedulePayment'] + $loanScheduleResult['schedulePayment'][$i];
            $loanScheduleResult['principleAmount'][$i] = $loanScheduleResult['schedulePayment'][$i] - $loanScheduleResult['interestAmount'][$i];
            $loanScheduleResult['totals']['totalPrincipleAmount'] += $loanScheduleResult['principleAmount'][$i];
            $loanScheduleResult['endBalance'][$i] = $loanScheduleResult['beginning'][$i]  + $loanScheduleResult['interestAmount'][$i] -$loanScheduleResult['schedulePayment'][$i];
            $loanScheduleResult['endBalance'][$i] = $loanScheduleResult['endBalance'][$i] < 1 && $loanScheduleResult['endBalance'][$i] > -1 ? 0 : $loanScheduleResult['endBalance'][$i];
            $firstLoop = false ;
        }
        return $loanScheduleResult;

    }
    

    
    public function calculateExecutionAndPaymentAndLoan(string $fixedAssetType, int $currentDateIndex, float $totalFFECost, int $ffeStartDateAsIndex,  $ffe)
    {
       
        $fixedLoanAtEndService = new CalculateFixedLoanAtEndService();
        $ffeExecutionAndPaymentService  = new FfeExecutionAndPayment();
        $contractPaymentService  = new ContractPaymentService();
        $loanWithdrawalService = new CalculateLoanWithdrawal();
        $study = $ffe->study;
        $dateIndexWithDate = $study->getDateIndexWithDate();
        $dateWithDateIndex = $study->getDateWithDateIndex();
        $contractPayments = [];
       
        
        $duration = $ffe->getDuration();
        $ffeCollectionPolicyValue  = $ffe->getCollectionPolicyValue();
        $downPaymentOneAmount = 0 ;
        $executionAndPayment =$ffeExecutionAndPaymentService->__calculate($totalFFECost, $ffeStartDateAsIndex, $duration, $dateIndexWithDate);
        $ffePayment =$contractPaymentService->__calculate($totalFFECost, $executionAndPayment, $ffeStartDateAsIndex, $downPaymentOneAmount, $ffeCollectionPolicyValue, $dateIndexWithDate, $dateWithDateIndex);
        $contractPayments['FFE Payment'] = $ffePayment;
            
            
        
        $ffeLoan = $study->getLoanStructure($fixedAssetType);
        $equityFundingRate = $ffeLoan ? $ffeLoan->getEquityFundingRatesAtMonthIndex($currentDateIndex) : 100;
            
        $ffeEquityPayment['FFE Equity Injection'] = $ffeExecutionAndPaymentService->calculateFFEEquityPayment($contractPayments['FFE Payment'], $totalFFECost, $equityFundingRate);
        /**
         * * والباقي هاخد بيه قرض
         */
            
        $ffeLoanWithdrawal['FFE Loan Withdrawal'] = $ffeExecutionAndPaymentService->calculateFFELoanWithdrawal($contractPayments['FFE Payment'], $totalFFECost, $equityFundingRate);
        $ffeLoanWithdrawalInterestAmounts = [];
            
        if ($equityFundingRate < 100 &&  $ffeLoan->getTenorsAtMonthIndex($currentDateIndex) >0 ){
            $ffeLoanType = $ffeLoan->getLoanType();
            $ffeBaseRate = 0;
            $ffeMarginRate = $ffeLoan->getMarginRateAtMonthIndex($currentDateIndex);
            $ffeTenor = $ffeLoan->getTenorsAtMonthIndex($currentDateIndex);
            $ffeInstallmentIntervalName = $ffeLoan->getInstallmentIntervalAtMonthIndex($currentDateIndex);
            $ffeStepUpRate=0;
            $ffeStepUpIntervalName='annually';
            $ffeStepDownRate=0;
            $ffeStepDownIntervalName='annually';
            $ffeGracePeriod=$ffeLoan->getGracePeriodAtMonthIndex($currentDateIndex);
            $ffeLoanPricing = $ffeMarginRate + $ffeBaseRate;
            $ffeLoanWithdrawalInterest=$loanWithdrawalService->__calculate($study->replaceIndexWithItsStringDate($ffeLoanWithdrawal['FFE Loan Withdrawal'], $dateIndexWithDate), $ffeBaseRate, $ffeMarginRate, $dateWithDateIndex);
            $ffeLoanWithdrawalInterestAmounts =$ffeLoanWithdrawalInterest['withdrawal_interest_amounts']??[];
            $ffeLoanWithdrawalEndBalance = $ffeLoanWithdrawalInterest['withdrawalEndBalance']??[];
            $ffeLoanWithdrawalAmounts = $ffeLoanWithdrawalInterest['loanWithdrawal']??[];
                
            $ffeLoanStartDate =array_key_last($ffeLoanWithdrawalInterest);
            $ffeLoanAmount = $ffeLoanWithdrawalInterest[$ffeLoanStartDate];
            if ($ffeLoanStartDate) {
                $ffeLoanStartDateAsIndex=$study->convertDateStringToDateIndex($ffeLoanStartDate);
                $ffeLoanCalculations = $fixedLoanAtEndService->__calculate([], -1, $ffeLoanType, $ffeLoanStartDate, $ffeLoanAmount, $ffeBaseRate, $ffeMarginRate, $ffeTenor, $ffeInstallmentIntervalName, $ffeStepUpRate, $ffeStepUpIntervalName, $ffeStepDownRate, $ffeStepDownIntervalName, $ffeGracePeriod, $ffeLoanStartDateAsIndex);
                $ffeLoanCalculations = $ffeLoanCalculations['final_result']??[];
                $currentEndBalances = $ffeLoanCalculations['endBalance']??[] ;
                $ffeLoanCalculations['endBalance'] =HArr::fillMissedKeysFromPreviousKeys($currentEndBalances, $study->getCalculatedExtendedStudyDates());
                $ffeLoanCalculations['month_as_index'] = $ffeLoanStartDateAsIndex;
                $ffeLoanCalculations['interest_rate'] = $ffeMarginRate;
                $ffeLoanCalculations['loan_type'] = $ffeLoanType;
                $ffeLoanInterestAmounts = $ffeLoanCalculations['interestAmount'] ?? [];
                $ffeLoanEndBalanceAtStudyEndDate = $ffeLoanCalculations['endBalance'][$study->getStudyEndDateFormatted()] ?? 0;
                $ffeLoanEndBalance = $ffeLoanCalculations['endBalance'];
                $ffeLoanInstallment['FFE Loan Installment'] = $ffeLoanCalculations['schedulePayment']??[];
            }
        }
           
        return [
            'contractPayments'=>$contractPayments,
            'ffeEquityPayment'=>$ffeEquityPayment,
            'ffeLoanWithdrawal'=>$ffeLoanWithdrawal,
            'ffeLoanInstallment'=>$ffeLoanInstallment??[],
            'ffeLoanInterestAmounts'=>$ffeLoanInterestAmounts??[],
            'ffeExecutionAndPayment'=>$executionAndPayment,
            'ffeLoanWithdrawalInterest'=>$ffeLoanWithdrawalInterestAmounts,
            'ffeLoanStartDate'=>$ffeLoanStartDate??null,
            'ffeLoanAmount'=>$ffeLoanAmount??0,
            'ffeLoanEndBalanceAtStudyEndDate'=>$ffeLoanEndBalanceAtStudyEndDate??null,
            'ffeLoanPricing'=>$ffeLoanPricing??0 ,
            'ffeLoanEndBalance'=>$ffeLoanEndBalance??[],
            'ffeLoanWithdrawalEndBalance'=>$ffeLoanWithdrawalEndBalance??[],
            'ffeLoanWithdrawalAmounts'=>$ffeLoanWithdrawalAmounts??[] ,
            'ffeLoanCalculations'=>$ffeLoanCalculations??[],
            'ffePayment'=>$contractPayments['FFE Payment']??[]
        ];
        
            
            
    
    
    }
    

}
