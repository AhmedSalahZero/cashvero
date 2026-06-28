<?php 
namespace App\ReadyFunctions;


class SupplierPayableEndBalance  
{
	
	public function getDisposablePayableStatement(array $purchase , array $collection , array $dateIndexWithDate ,  $hospitalitySector )
	{
		$purchasesForIntervals = [
			'monthly'=>$purchase,
			'quarterly'=>sumIntervals($purchase,'quarterly'),
			'semi-annually'=>sumIntervals($purchase,'semi-annually'),
			'annually'=>sumIntervals($purchase,'annually'),
		];
		$collectionForInterval = [
			'monthly'=>$collection,
			'quarterly'=>sumIntervals($collection,'quarterly'),
			'semi-annually'=>sumIntervals($collection,'semi-annually'),
			'annually'=>sumIntervals($collection,'annually'),
		];
		
		
		
		$result = [];
		$beginning_balances =[];
		$dueAmounts =[];
		$end_balance =[];
		foreach(getIntervalFormatted() as $intervalName=>$intervalNameFormatted){
			$beginning_balance = 0 ;
			foreach ($purchasesForIntervals[$intervalName]  as $date=>$purchaseAtDate) {
				$result[$intervalName]['beginning_balance'][$date] = $beginning_balance ; 
				$beginning_balances[$date] = $beginning_balance ;
				$result[$intervalName]['purchase'][$date] = $purchaseAtDate ?? 0 ; 
				$due_amount =($purchaseAtDate??0) + $beginning_balance ;
				$result[$intervalName]['due_amount'][$date] = $due_amount ; 
				
				$dueAmounts[$date] = $due_amount ;
				$end_balance[$date] = $due_amount - ($collectionForInterval[$intervalName][$date]??0);
				$result[$intervalName]['payment'][$date] = $collectionForInterval[$intervalName][$date]??0 ; 
				$result[$intervalName]['end_balance'][$date] = $end_balance[$date] ; 
				$beginning_balance = $end_balance[$date];
			}
		
		}
		return $result ;
	}
	

}
