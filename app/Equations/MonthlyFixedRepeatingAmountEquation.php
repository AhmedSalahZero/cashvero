<?php
namespace App\Equations;

use App\Helpers\HArr;

class MonthlyFixedRepeatingAmountEquation
{
    public function calculate(float $amount, int $startDateAsIndex, int $endDateAsIndex, string $increaseInterval, $increaseRate, bool $isDeductible, float $vatRate, float $withholdRate, $dateIndexWithYearIndex = [], $contractCount = null, $numberOfBranches = 1):array
    {
		$withholdAmounts = [];
		
        $resultWithoutVat = [];
        $resultWithVat = [];
        $resultVat = [];
        $currentStartDateAsIndex = $startDateAsIndex ;
        $intervalMode = [
            'quarterly'=> 3 ,
            'semi-annually'=>6,
            'annually'=>12
        ][$increaseInterval];
        $counter = 0 ;
        for ($currentStartDateAsIndex ; $currentStartDateAsIndex <= $endDateAsIndex ; $currentStartDateAsIndex++) {
        
            $currentIncreaseRate = 0;
            if ($counter!=0&&$counter % $intervalMode == 0) {
                $currentIncreaseRate = is_array($increaseRate) ? $increaseRate[$dateIndexWithYearIndex[$currentStartDateAsIndex]-1]??0 : $increaseRate ;
            }
            $currentIncreaseRate  = (1+$currentIncreaseRate/100) ;
            $amountBeforeVat = $currentIncreaseRate  * ($resultWithoutVat[$currentStartDateAsIndex-1]??$amount);
            $resultWithoutVat[$currentStartDateAsIndex] = $amountBeforeVat  ;
            $withholdAmounts[$currentStartDateAsIndex]=$amountBeforeVat * $withholdRate / 100  ;
            $resultWithVat[$currentStartDateAsIndex] = $amountBeforeVat *   (1+($vatRate / 100)) ;
            $resultVat[$currentStartDateAsIndex] = ($resultWithVat[$currentStartDateAsIndex] - $amountBeforeVat) ;
            
            $counter++;
        }
        if (is_array($contractCount)) {
            $withholdAmounts = HArr::multipleTwoArrAtSameIndex($withholdAmounts, $contractCount);
            $resultWithoutVat = HArr::multipleTwoArrAtSameIndex($resultWithoutVat, $contractCount);
            $resultVat = HArr::multipleTwoArrAtSameIndex($resultVat, $contractCount);
            $resultWithVat = HArr::multipleTwoArrAtSameIndex($resultWithVat, $contractCount);
        }
        if ($numberOfBranches > 1) {
            $withholdAmounts = HArr::MultiplyWithNumber($withholdAmounts, $numberOfBranches);
            $resultWithoutVat = HArr::MultiplyWithNumber($resultWithoutVat, $numberOfBranches);
            $resultVat = HArr::MultiplyWithNumber($resultVat, $numberOfBranches);
            $resultWithVat = HArr::MultiplyWithNumber($resultWithVat, $numberOfBranches);
        }
    
        return [
            'withhold_amounts'=>$withholdAmounts ,
            'total_before_vat'=>$resultWithoutVat,
            'total_vat'=>$resultVat,
            'total_after_vat'=>$resultWithVat
        ];
    
    }
}
