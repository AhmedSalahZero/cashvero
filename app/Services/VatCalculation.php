<?php 
namespace App\Services;

use App\Helpers\HArr;
use App\SalesItems\DurationYears;
use App\Traits\GeneralFunctions;
use App\Traits\Intervals;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class VatCalculation
{
	private function calculateVatAdditions(array $salesRevenueDateAndValue , float $vatRate ){
		$vatAdditions = [];
		foreach($salesRevenueDateAndValue as $date => $value){
			$vatAdditions[$date] = $vatRate/100 * $value;
		}
		return $vatAdditions;
	}
	public function __execute(array $salesRevenues  , array $purchases  , array $expenses  , array $fixedAssets , string $studyStartDate , int $duration , bool $isPaidInstallment = false , array $paid_installments = []  , float $balance = 0  , string $vatType = 'credit_balance'  ,$result = 'view')
	{
		// $salesRevenues , $purchases , $expenses , $fixedAssets examples 
		
		// $salesRevenues = [
		// 	[
		// 		'vat_rate'=> 10 ,
		// 		'values'=>[
		// 			'01-01-2023'=>20 ,
		// 			'01-02-2023'=>30 ,
		// 		]
		// 	],
		// 		[
		// 			'vat_rate'=> 20 ,
		// 			'values'=>[
		// 				'01-01-2023'=>50 ,
		// 				'01-02-2023'=>50 ,
		// 				]
		// 		]
		// ];
		
		
        $vat_taxes_statement = [
            'Opening Balance (+Cr/-Dr)'         => [],
            'Monthly Debit Balance'             => [],
            'VAT Additions'                     => [],
            'VAT Deduction'                     => [],
            'Monthly Due Amounts'               => [],
            'Accumulated Due Amounts'           => [],
            'VAT Payment'                       => [], 
            'Monthly End Balance'               => [],
            'Opening Credit Balance Payment'    => [],
            'Accumulated End Balance'           => []
        ];
		
		
		
		$vatAdditions = [];
		$salesRevenuesDates = array_keys($salesRevenues[0]['values']??[]);
        foreach ($salesRevenues as  $salesRevenueArrWithVatRate) {
			$salesRevenueVatAddition = $salesRevenueArrWithVatRate['vat_rate'] ?? 0 ;
			$salesRevenue = $salesRevenueArrWithVatRate['values'] ?? [];
            $vatAdditions = HArr::sumAtDates([$vatAdditions , $this->calculateVatAdditions($salesRevenue , $salesRevenueVatAddition) ],$salesRevenuesDates);
			$vat_taxes_statement['VAT Additions'] = $vatAdditions;
        }
		
		
		$vatDeductions = [];
		$purchasesDates = array_keys($purchases[0]['values']??[]);
        foreach ($purchases as  $purchaseArrWithVatRate) {
			$purchaseVatDeduction = $purchaseArrWithVatRate['vat_rate'] ?? 0 ;
			$purchase = $purchaseArrWithVatRate['values'] ?? [];
            $vatDeductions = HArr::sumAtDates([$vatDeductions , $this->calculateVatAdditions($purchase , $purchaseVatDeduction) ],$purchasesDates);
			$vat_taxes_statement['VAT Deduction'] = $vatDeductions;
        }
		
        $expensesDates = array_keys($expenses[0]['values']??[]);
        foreach ($expenses as  $expenseArrWithVatRate) {
			$expenseVatDeduction = $expenseArrWithVatRate['vat_rate'] ?? 0 ;
            $expense = $expenseArrWithVatRate['values'] ?? [];
            $vatDeductions = HArr::sumAtDates([$vatDeductions , $this->calculateVatAdditions($expense , $expenseVatDeduction) ],$expensesDates);
            $vat_taxes_statement['VAT Deduction'] = $vatDeductions;
        }
		
        $fixedAssetsDates = array_keys($fixedAssets[0]['values']??[]);
        foreach ($fixedAssets as  $fixedAssetArrWithVatRate) {
			$fixedAssetVatDeduction = $fixedAssetArrWithVatRate['vat_rate'] ?? 0 ;
            $fixedAsset = $fixedAssetArrWithVatRate['values'] ?? [];
            $vatDeductions = HArr::sumAtDates([$vatDeductions , $this->calculateVatAdditions($fixedAsset , $fixedAssetVatDeduction) ],$fixedAssetsDates);
            $vat_taxes_statement['VAT Deduction'] = $vatDeductions;
        }
        // Calculation Of Monthly Debit Balance & End Balance & Due Amounts
        $vat_beginning = $vatType == 'debit_balance' ? (-1*($balance)) : ($balance);
        $vat =[ date('d-m-Y',strtotime($studyStartDate)) => $vat_beginning]; 
		
        $beginning_balance = ($vat_beginning > 0) ? 0 : $vat_beginning;
        $payment = 0;
        $monthly_end_balance = 0;
        $dates =array_keys(Arr::collapse($vat_taxes_statement));
        $dates = GeneralFunctions::sortingDatesAsValues($dates);
        
        $taxes_vat_month_count = 1 ;
		$balanceDate = Carbon::make($studyStartDate)->subMonth()->format('d-m-Y');
        $vat_start_date =$balance   ? $balanceDate :  $studyStartDate ;
        $vat_start_date = date('d-m-Y',strtotime($vat_start_date));
    
        if(($vatType == 'credit_balance') && $isPaidInstallment ) {
            // $financial_vat_dates =  array_keys($vatInstallmentDatesAndValues);
			// $paid_installments $vatInstallmentDatesAndValues
            // $paid_installments = array_combine($financial_vat_dates,$vatInstallmentDatesAndValues);
        } elseif(($vatType == 'credit_balance')  ) {
            $paid_installments = [$this->dateCalc($vat_start_date,1) =>$vat_beginning] ;
        } 
 
        $accumulated_opening_balance_payment = 0;
        foreach ($dates as $month_num => $date) {
			
			$additions = ( $vat_taxes_statement['VAT Additions'][$date] ?? 0);
            $deduction = ($vat_taxes_statement['VAT Deduction'][$date]?? 0);
            if ($month_num < $taxes_vat_month_count) {
				$vat_taxes_statement['VAT Payment'][$date] = 0;
            }else{
				$previous_date = $this->dateCalc($date,-$taxes_vat_month_count);
				
                $vat_taxes_statement['VAT Payment'][$date] = ($vat_taxes_statement['Monthly Due Amounts'][$previous_date]??0) < 0 ? 0 :($vat_taxes_statement['Monthly Due Amounts'][$previous_date]??0); 
            }
			
            $vat_taxes_statement['Opening Credit Balance Payment'][$date] = ($paid_installments[$date]?? 0);
            $payment   = ($vat_taxes_statement['VAT Payment'][$date]?? 0) ;
            $vat_taxes_statement['Monthly Debit Balance'][$date] =$beginning_balance;
            
            // Monthly Due Amounts 
            $monthly_amount_due      = ($beginning_balance > 0) ? ($additions - $deduction) :  ($beginning_balance + $additions - $deduction);
            // Accumulated Due Amounts 
            $accumulated_amount_due  = ($beginning_balance + $additions - $deduction);
            // Monthly End Balance
            
            $previous_end_balance = isset($dates[$month_num-1]) ?  ($vat_taxes_statement['Monthly End Balance'][$dates[$month_num-1]]??0) : 0;
            $monthly_end_balance     = ($previous_end_balance < 0) ? $monthly_amount_due 
            : ($previous_end_balance + $monthly_amount_due - $payment) ; 
            
            $accumulated_opening_balance_payment += ($paid_installments[$date]?? 0); 
            //  Accumulated End Balance
            $accumulated_end_balance =  $vat_beginning < 0 ? $monthly_end_balance
                                                           : ($vat_beginning + $monthly_end_balance - $accumulated_opening_balance_payment );

            $vat_taxes_statement['Monthly Due Amounts'][$date] = $monthly_amount_due; 
            $vat_taxes_statement['Accumulated Due Amounts'][$date] = $accumulated_amount_due; 
            $vat_taxes_statement['Monthly End Balance'][$date] = $monthly_end_balance;
            $vat_taxes_statement['Accumulated End Balance'][$date] = $accumulated_end_balance; 
            $beginning_balance = $monthly_end_balance < 0 ? $monthly_end_balance : 0;

        } 
        // Opening Balance (+Cr/-Dr) 
        $vat_taxes_statement['Opening Balance (+Cr/-Dr)'] = $vat ;  
 
       
            // Total  
            $intervals_type = [ 
                'Opening Balance (+Cr/-Dr)'         => 'intervals_summation',
                'Monthly Debit Balance'             => 'beginning_balance_interval',
                'VAT Additions'                     => 'intervals_summation',
                'VAT Deduction'                     => 'intervals_summation',
                'Monthly Due Amounts'               => 'intervals_summation',
                'Accumulated Due Amounts'           => 'end_balance_interval',
                'VAT Payment'                       => 'intervals_summation', 
                'Opening Credit Balance Payment'    => 'intervals_summation', 
                'Monthly End Balance'               => 'end_balance_interval',
                'Accumulated End Balance'           => 'end_balance_interval',
			];
            $data =  self::intervals($vat_taxes_statement, $studyStartDate , $duration , 'multi', $intervals_type, false, false, false);
			return $data['data_intervals']??[] ;
	}

	public static function dateCalc($first_date, $number_of_months, $formate = "d-m-Y")
    {
        $dt = Carbon::parse($first_date);
        $final_date = $dt->addMonths($number_of_months)->format($formate);
        return $final_date;
    }
	
	public static function intervals($data, $startDate  , $duration , $levels = 'multi', $type_of_interval = 'intervals_summation', $report_with_total = true, $report_with_percentages = true, $show_header_totals = true)
    {
        $first_index = array_key_first($data);

        $years_intervals_months['monthly'] =  (isset($data[$first_index]) && (count($data[$first_index]) > 0)) ? array_keys($data[$first_index]) : [];
        $data_intervals['monthly'] = $levels == 'multi' ? $data : $data[$first_index];


        $data_totals = [];


        if (@count($data) > 0) {
            $years_intervals_months = self::intervalsDates($years_intervals_months, $startDate  , $duration );
            foreach ($data as $key => $values) {
                $intervalType = is_array($type_of_interval) ? $type_of_interval[$key] : 'intervals_summation';


                // 1 - Beginning Balance Interval
                if ($intervalType == 'beginning_balance_interval') {
                    foreach ($years_intervals_months as $interval => $months) {
                        $data_intervals[$interval] = self::beginningBalanceInterval($months, $values, $data_intervals, $interval, $key, $levels);

                        $levels == 'multi' ? $data_totals[$interval] = GeneralFunctions::finalTotal($data_intervals[$interval]) : '';
                    }
                }
                // 2 - End Balance Interval
                elseif ($intervalType == 'end_balance_interval') {
                    foreach ($years_intervals_months as $interval => $months) {
                        $data_intervals[$interval] = self::endBalanceInterval($months, $values, $data_intervals, $interval, $key, $levels);

                        $levels == 'multi' ? $data_totals[$interval] = GeneralFunctions::finalTotal($data_intervals[$interval]) : '';
                    }
                }
                // 3 - Intervals Summation
                elseif ($intervalType == 'intervals_summation') {
                    foreach ($years_intervals_months as $interval => $months) {
                    if(! is_array($months))
                    {
                    }
                        $data_intervals[$interval] = self::intervalsSummation($months, $values, $data_intervals, $interval, $key, $levels);
                        $data_intervals[$interval] = self::sortingData($levels, $data_intervals, $interval, $key);
                        $levels == 'multi' ? $data_totals[$interval] = GeneralFunctions::finalTotal($data_intervals[$interval]) : '';
                    }
                }
            }
        }
        return ['data_intervals' => $data_intervals, 'years_intervals_months' => $years_intervals_months, 'data_totals' => $data_totals, 'settings' => ['total' => $report_with_total, 'percentage' => $report_with_percentages, 'show_header_totals' => $show_header_totals]];
    }
	public static function sortingData($levels, $data_intervals, $interval, $key)
    {
        $levels == 'multi' ?
            array_multisort(array_map('strtotime', array_keys($data_intervals[$interval][$key])), SORT_ASC, $data_intervals[$interval][$key])
            :
            array_multisort(array_map('strtotime', array_keys($data_intervals[$interval])), SORT_ASC, $data_intervals[$interval]);
        return $data_intervals[$interval];
    }
	public static function intervalsSummation($months, $values, $data_intervals, $interval, $key, $levels)
    {
        // if(!is_array($values))
        // {
            
        // }
        $total_per_interval = 0;
        $dates = array_unique(array_merge($months, array_keys($values)));
        array_multisort(array_map('strtotime', $dates), SORT_ASC, $dates);

        array_walk($dates, function ($date, $index) use ($months, $values, $interval, &$data_intervals, &$total_per_interval, $key, $levels) {

            if ($found = false !== array_search($date, $months)) {
                $total_per_interval +=( $values[$date] ?? 0);

                $levels == 'multi' ?  $data_intervals[$interval][$key][$date] = $total_per_interval
                    : $data_intervals[$interval][$date] = $total_per_interval;
                $total_per_interval = 0;
            } elseif (isset($values[$date])) {
          
                $total_per_interval += ($values[$date] ?? 0);
            }
        });
        // if($levels == 'multi'){
        //     foreach ($data_intervals[$interval] as $key => $value) {
        //         array_multisort(array_map('strtotime', array_keys($data_intervals[$interval][$key])), SORT_ASC, $data_intervals[$interval][$key]);
        //     }
        // }
        return $data_intervals[$interval];
    }
	public static function endBalanceInterval($months, $values, $data_intervals, $interval, $key, $levels)
    {
        $total_per_interval = 0;
        $dates = array_unique(array_merge($months, array_keys($values)));
        array_multisort(array_map('strtotime', $dates), SORT_ASC, $dates);

        array_walk($dates, function ($date, $index) use ($months, $values, $interval, &$data_intervals, &$total_per_interval, $key, $levels) {

            if ($found = false !== array_search($date, $months)) {
                $total_per_interval = $values[$date] ?? 0;

                $levels == 'multi' ?  $data_intervals[$interval][$key][$date] = $total_per_interval
                    : $data_intervals[$interval][$date] = $total_per_interval;
                $total_per_interval = 0;
            }
        });
        return $data_intervals[$interval];
    }
	public static function beginningBalanceInterval($months, $values, $data_intervals, $interval, $key, $levels)
    {
        $total_per_interval = 0;
        $dates = array_unique(array_merge($months, array_keys($values)));
        array_multisort(array_map('strtotime', $dates), SORT_ASC, $dates);

        array_walk($dates, function ($date, $index) use ($months, $values, $interval, &$data_intervals, &$total_per_interval, $key, $levels) {

            if ($found = false !== array_search($date, $months)) {
                $total_per_interval = $values[$date] ?? 0;
                if ($interval == 'quarterly') {
                    $bedinning_date = GeneralFunctions::dateCalc($date, (-2));
                    $total_per_interval = $values[$bedinning_date] ?? 0;
                } elseif ($interval == 'semi_annually') {
                    $bedinning_date = GeneralFunctions::dateCalc($date, (-5));
                    $total_per_interval = $values[$bedinning_date] ?? 0;
                } elseif ($interval == 'annually') {
                    $bedinning_date = GeneralFunctions::dateCalc($date, (-11));
                    $total_per_interval = $values[$bedinning_date] ?? 0;
                }
                $levels == 'multi' ?  $data_intervals[$interval][$key][$date] = $total_per_interval
                    : $data_intervals[$interval][$date] = $total_per_interval;
                $total_per_interval = 0;
            }
        });
        return $data_intervals[$interval];
    }
	public static function intervalsDates($years_intervals_months, $startDate , $duration)
    {
        $years = self::years($startDate, 0, $duration, 'years_only');

        //Creating Tree Arrays For Each Interval
        // $intervals_names = ['monthly'];
        $intervals = [
            'monthly'  => ['01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-07', '01-08', '01-09', '01-10', '01-11', '01-12'],
            'quarterly' => ['01-03', '01-06', '01-09', '01-12'],
            'semi_annually' => ['01-06', '01-12'],
            'annually'  => ['01-12']
        ];
        foreach ($intervals as $interval_name => $interval) {
            // array_push($intervals_names, $interval_name);
            $counter = 0;
            foreach ($years as $year) {
                foreach ($interval as $month) {
                    $new_month = $month . '-' . $year;
                    $years_intervals_months[$interval_name][$counter] = $new_month;
                    $counter++;
                }
            }
        }
        return $years_intervals_months;
    }
	public static function years($financial_start_date,$start_from,$duration,$type=null)
    {

    	//  type = years to return years array for the target section destribution
        $duration = $duration-1;
    		$start_date = date("01-m-Y",strtotime(date("Y-m-d", strtotime($financial_start_date)) . " +$start_from  month"));
	
    		$start_month = date("m", strtotime($start_date));
    		// $current_year = date("Y", strtotime($current_date));
    		$end_date 	= date("Y-m-d",strtotime(date("Y-m-d", strtotime($start_date)) . " +$duration  month"));
            // Years Between Start And End Date
            $getRangeYears = range(gmdate('Y', strtotime($start_date)), gmdate('Y', strtotime($end_date)));
    	if ($type == "years_only") {
    	    return $getRangeYears ;
        }
    	elseif ($type == "years") {

    		$duration_monthes_in_years = [];

    		// If the month is in the duration of the sales plan ; the month value will be 1 else 0 
    		foreach ($getRangeYears as $key => $year) {
    			
    			for ($i=1; $i <= 12 ; $i++) { 
    				
    				$current_date = "01-".$i."-".$year;
    				$current_date= date("d-m-Y",strtotime($current_date));
    			
    				if (strtotime($current_date) >= strtotime($start_date) && strtotime($current_date) <= strtotime($end_date)) {
    					$duration_monthes_in_years[$year][$current_date] = 1;
    				}else{
    					$duration_monthes_in_years[$year][$current_date] = 0;
    				}
    			}    			
    		}
    		return $duration_monthes_in_years;
    	}
    	
    }
}
