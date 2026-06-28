<?php 
namespace App\ReadyFunctions ;

use App\Models\HospitalitySector;
use Carbon\Carbon;
use Illuminate\Routing\Matching\HostValidator;
use Illuminate\Support\Arr;

class CalculateLoanWithdrawal 
{
	public function __calculate(array $loanWithdrawal,float $baseRate , float $marginRate,array $dateWithDateIndex)
	{
		$pricing = ($baseRate + $marginRate) /100 ;
		$daysDifference =$this->calculateDaysCountForWithdrawal($loanWithdrawal);
		$interestFactor = $this->calcInterestFactorForWithdrawal($daysDifference,$pricing);
		$withdrawalWithInterest = $this->calculateWithdrawalWithInterest($loanWithdrawal,$interestFactor,$dateWithDateIndex);
		$withdrawalEndBalance =Arr::last($withdrawalWithInterest['withdrawalEndBalance'] ?? []) ;  
		$withdrawalEndBalance  = $withdrawalEndBalance  ?: 0;
		$withdrawalEndBalanceDate =array_key_last($loanWithdrawal) ;  
		return [
			'withdrawal_interest_amounts'=>$withdrawalWithInterest['interestAmount']??[],
			'withdrawalEndBalance'=>$withdrawalWithInterest['withdrawalEndBalance']??[],
			'loanWithdrawal'=>$withdrawalWithInterest['loanWithdrawal']??[],
			$withdrawalEndBalanceDate =>$withdrawalEndBalance, // [$withdrawalEndBalanceDate =>$withdrawalEndBalance] must be the last key in array to be sent to loan function 
		];
	}
	

	protected function calculateDaysCountForWithdrawal(array $loanWithdrawal):array
	{
		$days = [];
		$obj = [];
		foreach ($loanWithdrawal as $dateString => $amount) {
			$currentDate = $dateString;
			$previousDate = getPreviousDate($loanWithdrawal, $currentDate);
			if (!$previousDate) {
				$obj['date'] = $currentDate;
				$obj['daysDiff'] = 0;
				$days[]=$obj;
			} else {
				$obj['date'] = $currentDate;
				$obj['daysDiff'] = getDifferenceBetweenTwoDatesInDays(Carbon::make($currentDate), Carbon::make($previousDate));
				$days[]=$obj;
			}
		}

		return [
			'daysCount' => $days
		];
	}
	protected function calcInterestFactorForWithdrawal(array $daysCount, float $pricing):array
	{
		$interestFactor = [];
		for ($i = 0; $i < count($daysCount['daysCount']); $i++) {
			$interest = ($pricing / 360) * ($daysCount['daysCount'][$i]['daysDiff']);
			$obj = [];
			$obj['date'] = $daysCount['daysCount'][$i]['date'];
			$obj['interestFactor'] = $interest;
			$interestFactor[]=$obj;
		}

		return [
			'interestFactor' => $interestFactor
		];
	}
	protected function calculateWithdrawalWithInterest(array $loanWithdrawalArray, array $interestFactor,array $dateWithDateIndex)
	{
		$result['loanWithdrawal'] = $loanWithdrawalArray ;
		$indexes = array_keys($interestFactor['interestFactor']);
		$finalResult = ['loanWithdrawal'=>$loanWithdrawalArray];

		$date = null ;
		foreach ($indexes as $i) {
			$date = array_keys($loanWithdrawalArray)[$i];
			$loanWithdrawalAtDate = $loanWithdrawalArray[$date];
			$dateAsIndex = $dateWithDateIndex[$date];
			$result['beginning'][$i] =  $i == 0 ? $loanWithdrawalAtDate : ($result['withdrawalEndBalance'][$i-1] +$loanWithdrawalAtDate ) ;
			$finalResult['beginning'][$dateAsIndex] =  $result['beginning'][$i];
			$result['interestAmount'][$i] = $result['beginning'][$i] *   $interestFactor['interestFactor'][$i]['interestFactor'];
			$finalResult['interestAmount'][$dateAsIndex] =$result['interestAmount'][$i]; 
			$result['withdrawalEndBalance'][$i] = $result['beginning'][$i]  + $result['interestAmount'][$i]  ;
			$result['withdrawalEndBalance'][$i] = $result['withdrawalEndBalance'][$i] < 1 && $result['withdrawalEndBalance'][$i] > -1 ? 0 : $result['withdrawalEndBalance'][$i];
			$finalResult['withdrawalEndBalance'][$dateAsIndex] =$result['withdrawalEndBalance'][$i];
			 

		}
		return $finalResult ;
	}
	
	
}
