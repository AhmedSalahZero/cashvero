<?php 
namespace App\ReadyFunctions;

class InstallmentWithGraceMethod
{
	public function __calculate(
		int $startDateAsIndex,
		float $amount,
		float $downPaymentOneRate,
		float $balanceRate,
		int $gracePeriod ,
		float $installmentCount,
		string $installmentInterval,
		float $downPaymentTwoRate = 0,
		int $downPaymentTwoMonth = 0
	):array {
		$downPaymentOneAmount = $downPaymentOneRate / 100 * $amount;
		// $downPaymentOneDateFormatted = Carbon::make($startDate)->format('Y-m-d');
		$downPaymentTwoDateAsIndex = $startDateAsIndex+$downPaymentTwoMonth;
		$downPaymentTwoAmount =  $downPaymentTwoRate / 100 * $amount;
		$balanceAmount = 	$balanceRate / 100 * $amount;
		$installmentAmount = $balanceAmount / $installmentCount;
		if ($downPaymentTwoMonth == 0) {
			$installmentsDates = [
				$startDateAsIndex => $downPaymentOneAmount + $downPaymentTwoAmount,
			];
		} else {
			$installmentsDates = [
				$startDateAsIndex => $downPaymentOneAmount,
				$downPaymentTwoDateAsIndex => $downPaymentTwoAmount
			];
		}
		$installmentDuration = ($installmentInterval == 'monthly' ? 1 : ($installmentInterval == 'quarterly' ? 3 : ($installmentInterval == 'semi-annually' ? 6 : ($installmentInterval == 'annually' ? 12 : 0))));
		$currentRemaining = $balanceAmount ;
		for ($i = 0; $i < $installmentCount; $i+=$installmentDuration ) {
			$currentInstallmentDate = $i+$downPaymentTwoDateAsIndex +$installmentDuration+$gracePeriod;
			$installmentsDates[$currentInstallmentDate] = $installmentAmount * $installmentDuration;
			$currentRemaining = $currentRemaining - $installmentsDates[$currentInstallmentDate] ;
			if($currentRemaining < 0){
				$installmentsDates[$currentInstallmentDate] = $installmentsDates[$currentInstallmentDate] + $currentRemaining;
			}
		}
		return $installmentsDates;
	}
}
