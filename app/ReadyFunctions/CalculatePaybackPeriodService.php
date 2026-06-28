<?php 
namespace App\ReadyFunctions;

use Carbon\Carbon;

class CalculatePaybackPeriodService
{
	public function __calculate(array $accumulatedFreeCashFlowForEquity,string $studyStartDate,float $totalEquityInjection ){
		
        $paybackPeriod = 1;
        $paybackDate = null;
        foreach ($accumulatedFreeCashFlowForEquity as $accumulatedFreeCashFlowForEquity) {
            if ($accumulatedFreeCashFlowForEquity < 0) {
                $paybackPeriod++;
            } else {
                if ($accumulatedFreeCashFlowForEquity >= $totalEquityInjection) {
					$paybackDate = (Carbon::make($studyStartDate)->addMonths($paybackPeriod - 1)->format('M Y'));
                    break;
                } else {
                    $paybackPeriod++;
                }
            }
        }
		
		$paybackPeriod = number_format($paybackPeriod/ 12 , 1) . ' Years' ;
		
		return [
			$paybackDate =>$paybackPeriod 
		];
	}
}
