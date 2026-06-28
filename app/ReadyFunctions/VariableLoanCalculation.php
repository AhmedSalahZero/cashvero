<?php 
namespace App\ReadyFunctions;
class VariableLoanCalculation 
{
	public function calculate(string $loanStartDate,float $loanAmount ,float $baseRate , float $marginRate , float $minInterestRate ,int $duration  ,int $InterestIntervalNum,int $InstallmentIntervalNum,  string $installmentIntervalName ,  int $gracePeriod = 0 ):array 
	{
		$result = [];
            if ($installmentIntervalName == 'monthly') {
                $installment_amount = $loanAmount/($duration);
            }elseif ($installmentIntervalName == 'quarterly') {
                $installment_amount = $loanAmount/(($duration/12)*4);
            }else{
                $installment_amount = $loanAmount/(($duration/12)*2);
            }
            $margin_borrowing_rate = $baseRate+$marginRate;
            $interest_rate = $margin_borrowing_rate > $minInterestRate ? $margin_borrowing_rate : $minInterestRate; 

            $interest_percent = $interest_rate;

            $interestPerInterval = 0;
            $interestPayment = 0;
            $totalPaymentInterest = 0;
            $totalInstallment = 0;
            $counter = 1;
			for($monthIndex = 0 ; $monthIndex < $duration ; $monthIndex++){
                $loanDate = date("d-m-Y",strtotime(date("Y-m-d", strtotime($loanStartDate)) . " + $monthIndex  month"));
                $numberOfDaysInMonth = cal_days_in_month(CAL_GREGORIAN,date('m',strtotime($loanDate)),date('Y',strtotime($loanDate)));
                $current_interest_percent = (($interest_percent/360)*$numberOfDaysInMonth)/100;
                $interest_amount = ($current_interest_percent) * $loanAmount ;
                $interestPerInterval += $interest_amount;
                if ($gracePeriod > $monthIndex || !is_int($counter / $InstallmentIntervalNum)) {
                    $installment = 0;
                }else{
                    $installment = $installment_amount;
                }

                $totalDue = $loanAmount+$interest_amount;
                
                if (is_int($counter / $InterestIntervalNum) ) {
                    $interestPayment = $interestPerInterval;
                    $endBalance = $totalDue - $interestPayment - $installment;
                    $interestPerInterval = 0;
                }else{
                    $interestPayment = 0;
                    $endBalance = $totalDue - $interestPayment - $installment;
                    
                }
                
				$result[$monthIndex]['month'] = $monthIndex;
                    $result [$monthIndex]['loan_date'] = date('Y-m-d',strtotime($loanDate));
                    $result [$monthIndex]['loan_amount'] = $loanAmount;
                    $result [$monthIndex]['interest_percent'] = $current_interest_percent*100;
                    $result [$monthIndex]['interest_amount'] = $interest_amount ;
                    $result [$monthIndex]['total_due'] = $totalDue ;
                    $result [$monthIndex]['interest_payment'] =$interestPayment ;
                    $result [$monthIndex]['installment'] = $installment ;
                    $result [$monthIndex]['end_balance'] = $endBalance ; 
					$totalPaymentInterest += $interestPayment;
					$totalInstallment += $installment;
					$loanAmount = $endBalance ;
					$counter++;
            }
			return $result;
         
          
	}
}
