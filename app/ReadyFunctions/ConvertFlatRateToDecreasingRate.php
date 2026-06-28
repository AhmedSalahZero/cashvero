<?php
namespace App\ReadyFunctions;

use MathPHP\Finance;

class ConvertFlatRateToDecreasingRate
{
    public function excel_rate($flatInterest,$nper, $pv = 1 , $fv = 0, $type = 0, $guess = 0.1)
    {
		$flatInterest = $flatInterest/100 ; 
		$pmt = -(1 + (1 * $flatInterest / 12 * $nper)) / $nper; // Payment: -0.10525641
		$annuallyDecreasingRate = Finance::rate($nper, $pmt, $pv, $fv,$type,$guess) * 12 ;
		return $annuallyDecreasingRate * 100;
    }
}
