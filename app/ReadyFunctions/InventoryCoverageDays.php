<?php

namespace App\ReadyFunctions;

use App\Models\DepartmentExpense;
use App\Models\HospitalitySector;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class InventoryCoverageDays
{
	public function coverageDurationInMonths($days)
	{
		$coverage_duration = 0;
		if ($days == 7) {
			$coverage_duration = 0.25;
		} elseif ($days == 15) {
			$coverage_duration = 0.5;
		} elseif ($days == 30) {
			$coverage_duration = 1;
		} elseif ($days == 45) {
			$coverage_duration = 1.5;
		} elseif ($days == 60) {
			$coverage_duration = 2;
		} elseif ($days == 75) {
			$coverage_duration = 2.5;
		} elseif ($days == 90) {
			$coverage_duration = 3;
		} elseif ($days == 120) {
			$coverage_duration = 4;
		} elseif ($days == 150) {
			$coverage_duration = 5;
		} elseif ($days == 180) {
			$coverage_duration = 6;
		}
		return $coverage_duration;
	}
	public function inventoryCoverageDaysValues($product_purchase_cost,array $dateIndexWithDate,array $dateWithDateIndex,$hospitalitySector=null,$identifier='name')
	{
		// $product_purchase_cost 
		/*
		[
			// 1== $directExpenseIdentifier
			1 => [
				'01-01-2023'=>25,
				'01-02-2023'=>26,
				'01-03-2024'=>27,
				'01-04-2024'=>28,
				'01-05-2024'=>28,
			]
		]
		*/
		$store_final_balance = 0;
		$purchases = [];
		$ending_balances = [];
		$result = [];
		foreach ($product_purchase_cost as $directExpenseIdentifier => $product_purchase_cost) {
			$directExpense = $hospitalitySector->departmentExpenses->where('id',$directExpenseIdentifier)->first();
			$inventory_coverage_days = $directExpense->getInventoryCoverageDays();
			$inventory_coverage_days = $this->coverageDurationInMonths($inventory_coverage_days);
			$begining_balance = $directExpense->getBeginningInventoryBalanceValue();
			$last_12_months =  array_slice($product_purchase_cost, -12, 12, true);
			$last_year_avg_sales =   $this->average($last_12_months);
			
			// last date
			$last_date = array_key_last($product_purchase_cost);
			$last_date = $dateIndexWithDate[$last_date];
			// first month number
			$counter = 0;
			foreach ($product_purchase_cost as $dateIndex => $value) {
				
				$result[$directExpense->{$identifier}]['begining_balance'][$dateIndex] = $begining_balance;
				$result['total']['begining_balance'][$dateIndex] = isset($result['total']['begining_balance'][$dateIndex]) ? $result['total']['begining_balance'][$dateIndex] +$begining_balance : $begining_balance;
				
				$date = $dateIndexWithDate[$dateIndex];
				// year
				// $year = date('Y', strtotime($date));

				// $dateIndex3
				$dateIndex1=$dateWithDateIndex[$this->customMonth($date, 1)];
				$dateIndex2=$dateWithDateIndex[$this->customMonth($date, 2)];
				$dateIndex3=$dateWithDateIndex[$this->customMonth($date, 3)];
				$dateIndex4=$dateWithDateIndex[$this->customMonth($date, 4)];
				$dateIndex5=$dateWithDateIndex[$this->customMonth($date, 5)];
				$dateIndex6=$dateWithDateIndex[$this->customMonth($date, 6)];
				
				
				$one = isset($product_purchase_cost[$dateIndex1]) ? $product_purchase_cost[$dateIndex1] : 0;
				// if it is the last month
				if (strtotime($last_date) == strtotime($date)) {
					$store_final_balance = $last_year_avg_sales * $inventory_coverage_days;
				} elseif ($inventory_coverage_days == 0) {
					$store_final_balance = 0;
				} elseif ($inventory_coverage_days ==  0.25) {
					$store_final_balance = $one * 0.25;
				} elseif ($inventory_coverage_days ==  0.5) {
					$store_final_balance = $one * 0.5;
				} elseif ($inventory_coverage_days == 1) {
					$store_final_balance = $one;
				} elseif ($inventory_coverage_days == 1.5) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$store_final_balance = ($one + ($two * 0.5));
				} elseif ($inventory_coverage_days == 2) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$store_final_balance = $one + $two;
				} elseif ($inventory_coverage_days == 2.5) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$three = isset($product_purchase_cost[$dateIndex3]) ? ($product_purchase_cost[$dateIndex3]) : 0;
					$store_final_balance = $one + $two + ($three * 0.5);
				} elseif ($inventory_coverage_days == 3) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$three = isset($product_purchase_cost[$dateIndex3]) ? ($product_purchase_cost[$dateIndex3]) : 0;
					$store_final_balance = $one + $two + $three;
				} elseif ($inventory_coverage_days == 4) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$three = isset($product_purchase_cost[$dateIndex3]) ? ($product_purchase_cost[$dateIndex3]) : 0;
					$four = isset($product_purchase_cost[$dateIndex4]) ? ($product_purchase_cost[$dateIndex4]) : 0;
					$store_final_balance = $one + $two + $three + $four;
				} elseif ($inventory_coverage_days == 5) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$three = isset($product_purchase_cost[$dateIndex3]) ? ($product_purchase_cost[$dateIndex3]) : 0;
					$four = isset($product_purchase_cost[$dateIndex4]) ? ($product_purchase_cost[$dateIndex4]) : 0;
					$five = isset($product_purchase_cost[$dateIndex5]) ? ($product_purchase_cost[$dateIndex5]) : 0;
					$store_final_balance = $one + $two + $three + $four + $five;
				} elseif ($inventory_coverage_days == 6) {
					$two = isset($product_purchase_cost[$dateIndex2]) ? ($product_purchase_cost[$dateIndex2]) : 0;
					$three = isset($product_purchase_cost[$dateIndex3]) ? ($product_purchase_cost[$dateIndex3]) : 0;
					$four = isset($product_purchase_cost[$dateIndex4]) ? ($product_purchase_cost[$dateIndex4]) : 0;
					$five = isset($product_purchase_cost[$dateIndex5]) ? ($product_purchase_cost[$dateIndex5]) : 0;
					$six = isset($product_purchase_cost[$dateIndex6]) ? ($product_purchase_cost[$dateIndex6]) : 0;
					$store_final_balance = $one + $two + $three + $four + $five + $six;
				}

				// Purchases

				if ($begining_balance == 0) {
					$purchases[$directExpenseIdentifier][$dateIndex] = (($value) + $store_final_balance);
				} elseif (((($value) + $store_final_balance) - $begining_balance) <= 0) {
					$purchases[$directExpenseIdentifier][$dateIndex] = 0;
				} else {
					$purchases[$directExpenseIdentifier][$dateIndex] = ((($value) + $store_final_balance) - $begining_balance);
				}


				// Available For Sales
				$available_for_sales = $begining_balance +  $purchases[$directExpenseIdentifier][$dateIndex];
			
				// Ending Balance
				$ending_balance = $available_for_sales - ($value);
				
				// Updating the Begining Balance
		
				if (isset($product_purchase_cost[$dateIndex])) {
				// if (isset($product_purchase_cost[$this->customMonth($dateIndex, 1)])) {
					
					$begining_balance = $ending_balance;
				}
			
				
				$purchases[$directExpenseIdentifier][$dateIndex] = $purchases[$directExpenseIdentifier][$dateIndex];
				$ending_balances[$dateIndex] = $ending_balance;
				$result[$directExpense->{$identifier}]['purchases'][$dateIndex] = $purchases[$directExpenseIdentifier][$dateIndex];
				$result['total']['purchase'][$dateIndex] = isset($result['total']['purchase'][$dateIndex]) ? $result['total']['purchase'][$dateIndex] +$purchases[$directExpenseIdentifier][$dateIndex] : $purchases[$directExpenseIdentifier][$dateIndex];
				$result[$directExpense->{$identifier}]['total_available'][$dateIndex] = $available_for_sales;
				$result['total']['total_available'][$dateIndex] = isset($result['total']['total_available'][$dateIndex]) ? $result['total']['total_available'][$dateIndex] +$available_for_sales : $available_for_sales;
				
				$result[$directExpense->{$identifier}]['dispensed_disposable_cost'][$dateIndex] = $value;
				$result['total']['dispensed_disposable_cost'][$dateIndex] = isset($result['total']['dispensed_disposable_cost'][$dateIndex]) ? $result['total']['dispensed_disposable_cost'][$dateIndex] +$result[$directExpense->{$identifier}]['dispensed_disposable_cost'][$dateIndex] : $result[$directExpense->{$identifier}]['dispensed_disposable_cost'][$dateIndex];
				
				$result[$directExpense->{$identifier}]['end_balance'][$dateIndex] =$ending_balance;
				$result['total']['end_balance'][$dateIndex] = isset($result['total']['end_balance'][$dateIndex]) ? $result['total']['end_balance'][$dateIndex] +$ending_balance : $ending_balance;
				$total_ending_balances[$dateIndex] = isset($total_ending_balances[$dateIndex]) ? $total_ending_balances[$dateIndex] + $ending_balance : $ending_balance;
				$counter++;
			}
		}
		$total = $result['total'] ?? [];
		unset($result['total']);
		$result['total'] = $total;
		
		
		return $result ; 
	}
	public function calculateForIntervals(array $items,array $dateIndexWithDate, $hospitalitySector,bool $convertIndexesToNames=false)
	{
		$result = [];
		$directExpenseName = null;
		
		unset($items['total']);
		foreach($items as $directExpenseIdentifier => $item)
		{
			if($convertIndexesToNames){
				$directExpenseName = $hospitalitySector->departmentExpenses->where('id',$directExpenseIdentifier)->first()->getName();
			}
			$resultIdentifier = $convertIndexesToNames ?$directExpenseName: $directExpenseIdentifier;
			$initialBalance = Arr::first($item['begining_balance']);
		
			$purchases = $item['purchases'];
			$dispensedDisposableCost = $item['dispensed_disposable_cost'];
			
			
			$purchasesForInterval = [
				'monthly'=>$purchases,
				'quarterly'=>sumIntervals($purchases,'quarterly'),
				'semi-annually'=>sumIntervals($purchases,'semi-annually'),
				'annually'=>sumIntervals($purchases,'annually'),
			];
			
			$dispensedDisposableCostForInterval = [
				'monthly'=>$dispensedDisposableCost,
				'quarterly'=>sumIntervals($dispensedDisposableCost,'quarterly'),
				'semi-annually'=>sumIntervals($dispensedDisposableCost,'semi-annually'),
				'annually'=>sumIntervals($dispensedDisposableCost,'annually'),
			];
			foreach(getIntervalFormatted() as $intervalName=>$intervalNameFormatted){
				$beginningBalance = $initialBalance ;
				
				foreach($purchasesForInterval[$intervalName] as $dateIndex=>$purchaseAtInterval){
					$result[$resultIdentifier][$intervalName]['begining_balance'][$dateIndex]=$beginningBalance;
					$result['total'][$intervalName]['begining_balance'][$dateIndex] = isset($result['total'][$intervalName]['begining_balance'][$dateIndex]) ? $result['total'][$intervalName]['begining_balance'][$dateIndex] +  $result[$resultIdentifier][$intervalName]['begining_balance'][$dateIndex] : $result[$resultIdentifier][$intervalName]['begining_balance'][$dateIndex];
					$result[$resultIdentifier][$intervalName]['purchases'][$dateIndex]=$purchaseAtInterval;
					$result['total'][$intervalName]['purchases'][$dateIndex] = isset($result['total'][$intervalName]['purchases'][$dateIndex]) ? $result['total'][$intervalName]['purchases'][$dateIndex] +  $result[$resultIdentifier][$intervalName]['purchases'][$dateIndex] : $result[$resultIdentifier][$intervalName]['purchases'][$dateIndex];
					$result[$resultIdentifier][$intervalName]['total_available'][$dateIndex]=$beginningBalance + $purchaseAtInterval ;
					$result['total'][$intervalName]['total_available'][$dateIndex] = isset($result['total'][$intervalName]['total_available'][$dateIndex]) ? $result['total'][$intervalName]['total_available'][$dateIndex] +  $result[$resultIdentifier][$intervalName]['total_available'][$dateIndex] : $result[$resultIdentifier][$intervalName]['total_available'][$dateIndex];
					
					$result[$resultIdentifier][$intervalName]['dispensed_disposable_cost'][$dateIndex] = $dispensedDisposableCostForInterval[$intervalName][$dateIndex];
					$result['total'][$intervalName]['dispensed_disposable_cost'][$dateIndex] = isset($result['total'][$intervalName]['dispensed_disposable_cost'][$dateIndex]) ? $result['total'][$intervalName]['dispensed_disposable_cost'][$dateIndex] +  $dispensedDisposableCostForInterval[$intervalName][$dateIndex] : $dispensedDisposableCostForInterval[$intervalName][$dateIndex];
					
					$result[$resultIdentifier][$intervalName]['end_balance'][$dateIndex] =$result[$resultIdentifier][$intervalName]['total_available'][$dateIndex] - $dispensedDisposableCostForInterval[$intervalName][$dateIndex];
					$result['total'][$intervalName]['end_balance'][$dateIndex] = isset($result['total'][$intervalName]['end_balance'][$dateIndex]) ? $result['total'][$intervalName]['end_balance'][$dateIndex] +  $result[$resultIdentifier][$intervalName]['end_balance'][$dateIndex] : $result[$resultIdentifier][$intervalName]['end_balance'][$dateIndex];
					$beginningBalance = $result[$resultIdentifier][$intervalName]['end_balance'][$dateIndex] ;
				}
			}
		}
		
		$total = $result['total'] ?? [];
		unset($result['total']);

		$result['total']=$total;
		return $result ;
	}
	public function customMonth($date, $number_of_added_months)
	{
		return Carbon::parse($date)->addMonths($number_of_added_months)->format('d-m-Y');
	}
	public function average($volumes)
	{
		$last_year_avg_sales = array_sum($volumes) / @count($volumes);
		return $last_year_avg_sales;
	}
	
}
