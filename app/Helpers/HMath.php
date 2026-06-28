<?php
namespace App\Helpers;


class HMath 
{
	public static function calculateStandardDeviation($arr){
		$arr_size=count($arr);
		$mu=array_sum($arr)/$arr_size;
		$ans=0;
		foreach($arr as $elem){
			$ans+=pow(($elem-$mu),2);
		}
		return sqrt($ans/$arr_size);
	}
	public static function removeOutliers($array) {
		// $array = [
		// 	"2010-01-01"=>5,
		// 	"2010-01-02"=>7,
		// 	"2010-01-03"=>7,
		// 	"2010-01-04"=>9,
		// 	"2010-01-05"=>10,
		// 	"2010-01-06"=>11,
		// 	"2010-01-07"=>12,
		// 	"2010-01-08"=>13,
		// 	"2010-01-09"=>15,
		// 	"2010-01-10"=>28,
			
		// ];
		if(count($array) == 0) {
		  return $array;
		}
		$ret = array();
		$mean = array_sum($array)/count($array);
		$standardDeviation = self::calculateStandardDeviation($array);
		$upperOutliersValue =$mean +  (2 * $standardDeviation);
		$lowerOutliersValue =$mean -  (2 * $standardDeviation);

		foreach($array as $date=>$currentValue) {
			
				if($currentValue > $upperOutliersValue || $currentValue < $lowerOutliersValue){
					$ret[$date] = $currentValue;
				}
		}
		return $ret;
	}
	public static function calculateCorrelationCoefficient(array $x, array $y): float
{
    $n = count($x);

    if ($n !== count($y) || $n === 0) {
        throw new \InvalidArgumentException("Arrays must have the same length and cannot be empty.");
    }

    $sumX = array_sum($x);
    $sumY = array_sum($y);
    $sumXY = 0;
    $sumXSquare = 0;
    $sumYSquare = 0;

    for ($i = 0; $i < $n; $i++) {
        $sumXY += $x[$i] * $y[$i];
        $sumXSquare += $x[$i] ** 2;
        $sumYSquare += $y[$i] ** 2;
    }

    $numerator = ($n * $sumXY) - ($sumX * $sumY);
    $denominator = sqrt(
        ($n * $sumXSquare - $sumX ** 2) * ($n * $sumYSquare - $sumY ** 2)
    );

    return $denominator != 0 ? $numerator / $denominator : 0.0;
}

public static function generateTextBasedOnCoefficientCorrelationValue($r){
	if(self::isFixedExpense($r)){
		return __('Fixed Expense');
	}
	if($r > 0.7 && $r <= 1){
		return __('Direct Relation Variable Expense');
	}
	if($r >= -1 && $r <= -0.7){
		return __('Inverse Relation Variable Expense');
	}
	if($r > 0.3 && $r < 0.7){
		return __('Direct Relation Semi Variable Expense');
	}
	if($r > -0.7 && $r <= -0.3){
		return __('Inverse Relation Semi Variable Expense');
	}
	return 'tt';
	
}	
	public static function isFixedExpense($r){
		return $r > -0.3 && $r < 0.3;
	}
	public static function calculateIncreaseInExpensePerSalesValue($r , $expenseItemsValues ,$salesValues , $salesChange )
	{
		$standardDeviationOfExpense = self::calculateStandardDeviation($expenseItemsValues); 
		$standardDeviationOfSales = self::calculateStandardDeviation($salesValues);
		$expenseChange = $r * ($standardDeviationOfExpense  / $standardDeviationOfSales ) * $salesChange ; 
		return $expenseChange ;
	}
	
	
	public static function calculateRegression($x, $y) {
		$n = count($x);
		$sumX = array_sum($x);
		$sumY = array_sum($y);
		$sumXSq = array_sum(array_map(function($a) { return pow($a, 2); }, $x));
		$sumXY = array_sum(array_map(function($a, $b) { return $a * $b; }, $x, $y));

		$slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXSq - pow($sumX, 2));
		$intercept = ($sumY - $slope * $sumX) / $n;
		
		return array($intercept, $slope);
	}
	
	
	public static function calculateBreakevenPoint(array $sales , array $expenses)
	{
		if(count($sales) && count($expenses)){
			list($intercept, $slope) = self::calculateRegression($sales, $expenses);
			return $intercept / (1 - $slope);
		}
		return 0;
	}
	
}
