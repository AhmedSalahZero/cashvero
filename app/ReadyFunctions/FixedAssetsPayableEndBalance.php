<?php 
namespace App\ReadyFunctions;

use App\Helpers\HArr;
use App\Models\HospitalitySector;

class FixedAssetsPayableEndBalance  
{
	
	  public function calculateEndBalance(array $purchase, array $collection, array $dateIndexWithDate)
    {
        $collection = HArr::fillArr($collection);
        $financialYearStartMonth = 12 ;
        $purchasesForIntervals = [
            'monthly'=>$purchase,
            'quarterly'=>sumIntervals($purchase, 'quarterly'),
            'semi-annually'=>sumIntervals($purchase, 'semi-annually'),
            'annually'=>sumIntervals($purchase, 'annually'),
        ];
        
        $collectionForInterval = [
            'monthly'=>$collection,
            'quarterly'=>sumIntervals($collection, 'quarterly'),
            'semi-annually'=>sumIntervals($collection, 'semi-annually'),
            'annually'=>sumIntervals($collection, 'annually'),
        ];
        
        
        
        $result = [];
        $beginning_balances =[];
        $dueAmounts =[];
        $end_balance =[];
        foreach (getIntervalFormatted() as $intervalName=>$intervalNameFormatted) {
            $beginning_balance = 0 ;
            foreach ($collectionForInterval[$intervalName] as $dateAsIndex=>$currenPayment) {
                $result[$intervalName]['beginning_balance'][$dateAsIndex] = $beginning_balance ;
                $beginning_balances[$dateAsIndex] = $beginning_balance ;
                $purchaseAtDate = $purchasesForIntervals[$intervalName][$dateAsIndex]??0;
                $result[$intervalName]['asset_purchases'][$dateAsIndex] = $purchaseAtDate  ;
                $due_amount =$purchaseAtDate + $beginning_balance ;
                $result[$intervalName]['due_amount'][$dateAsIndex] = $due_amount ;
                $dueAmounts[$dateAsIndex] = $due_amount ;
                $end_balance[$dateAsIndex] = $due_amount - $currenPayment;
                $result[$intervalName]['payment'][$dateAsIndex] = $currenPayment ;
                $result[$intervalName]['end_balance'][$dateAsIndex] = $end_balance[$dateAsIndex] ;
                $beginning_balance = $end_balance[$dateAsIndex];
            }
        
        }
        return $result ;
    }
	

}
