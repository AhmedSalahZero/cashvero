<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class InstallmentMethod
{
	public function __calculate(
		string $startDate,
		float $amount,
		float $downPaymentOneRate,
		float $balanceRate,
		float $installmentCount,
		string $installmentInterval,
		float $downPaymentTwoRate = 0,
		int $downPaymentTwoMonth = 0
	):array {
		$downPaymentOneAmount = $downPaymentOneRate / 100 * $amount;
		$downPaymentOneDateFormatted = Carbon::make($startDate)->format('d-m-Y');
		$downPaymentTwoDate =  Carbon::make($startDate)->addMonths($downPaymentTwoMonth);
		$downPaymentTwoDateFormatted =  Carbon::make($startDate)->addMonths($downPaymentTwoMonth)->format('d-m-Y');
		$downPaymentTwoAmount =  $downPaymentTwoRate / 100 * $amount;
		$balanceAmount = 	$balanceRate / 100 * $amount;
		$installmentAmount = $balanceAmount / $installmentCount;
		if ($downPaymentTwoMonth == 0) {
			$installmentsDates = [
				$downPaymentOneDateFormatted => $downPaymentOneAmount + $downPaymentTwoAmount,
			];
		} else {
			$installmentsDates = [
				$downPaymentOneDateFormatted => $downPaymentOneAmount,
				$downPaymentTwoDateFormatted => $downPaymentTwoAmount
			];
		}

		$installmentDuration = ($installmentInterval == 'monthly' ? 1 : ($installmentInterval == 'quarterly' ? 3 : ($installmentInterval == 'semi-annually' ? 6 : ($installmentInterval == 'annually' ? 12 : 0))));
		for ($i = 1; $i <= $installmentCount; $i++) {
			$currentInstallmentDate = $downPaymentTwoDate->addMonths($installmentDuration)->format('d-m-Y');
			$installmentsDates[$currentInstallmentDate] = $installmentAmount;
		}

		return $installmentsDates;
	}
}
