<?php

namespace App\ReadyFunctions;

use App\Helpers\HArr;
use App\Models\Cheque;
use App\Models\CustomerInvoice;
use App\Traits\Services\IsAgingService;
use Exception;

/**
 * * هي اسمها اعمار الديون
 * * هو عباره عن الفواتير اللي لسه مفتوحة ( اعمار الديون) .. سواء الدين لسه جايه او المتاخر او حق اليوم
 * * وبالتالي بمجرد ما تندفع مش بتيجي هنا (لو النت بلانس اكبر من صفر يبقي لسه ما استدتش كاملا)
 */
class ChequeAgingService
{
	use IsAgingService ;
    public const MORE_THAN_150 = 'More Than 150';
    protected $company_id ;
    protected $aging_date ;
    protected $currency ;

    public function __construct(int $companyId, string $agingDate,string $currency)
    {
        $this->company_id = $companyId ;
        if (!isValidDateFormat($agingDate, 'Y-m-d')) {
            throw new Exception('Custom Exception Invalid Date Format Passed .. Excepted Format To Be Y-m-d');
        }
        $this->aging_date = $agingDate ;
        $this->currency = $currency ;
    }

	public function __execute(array $partnerIds, string $modelType)
    {

        $fullModelName = ("\App\Models\\" . $modelType) ;
        $modelModelName = $fullModelName::MONEY_MODEL_NAME ;
		$chequeModelName = $fullModelName::AGING_CHEQUE_MODEL_NAME;
		$moneyReceivedOrPaymentTableName = $fullModelName::MONEY_RECEIVED_OR_PAYMENT_TABLE_NAME;
		$moneyReceivedOrPaymentTableForeignName = $fullModelName::MONEY_RECEIVED_OR_PAYMENT_TABLE_FOREIGN_NAME;
		$chequesTableName = $fullModelName::AGING_CHEQUE_TABLE_NAME;
		 $chequeTypesForSafe = ('\App\Models\\'.$chequeModelName)::getChequeTypesForAging() ;
	
        $result = [];
		$chequesResultForChart = [];
		/**
		 * * هنا شرط الديو ديت اكبر من او يساوي
		 */
		
        $invoices = ('\App\Models\\'.$chequeModelName)
		::whereIn('status',$chequeTypesForSafe)
		->where('due_date', '>=', $this->aging_date)
		->where('currency',$this->currency)
		->join($moneyReceivedOrPaymentTableName,$moneyReceivedOrPaymentTableName.'.id','=',$chequesTableName.'.'.$moneyReceivedOrPaymentTableForeignName)
		->has($modelModelName)
		->where($chequesTableName.'.company_id', $this->company_id);
        if (count($partnerIds)) {
            $invoices->whereHas($modelModelName,function($q) use($partnerIds){
                $q->whereIn('partner_id',$partnerIds);
            });
        }
        $invoices = $invoices->get();


        foreach ($invoices as $index => $invoice) {
			/**
			 * @var Cheque $invoice
			 */
			
            $clientName = $invoice->{$modelModelName}->getName() ;
            $invoiceNumber = $invoice->getNumber();
            $invoiceDueDate = $invoice->getDueDate();
		
            $netBalance = $invoice->{$modelModelName}->getAmount() ;
            if (!$netBalance) {
                continue;
            }
			$chequesResultForChart[$invoiceDueDate] = isset($chequesResultForChart[$invoiceDueDate]) ? $chequesResultForChart[$invoiceDueDate] +$netBalance  : $netBalance ;
            $dueNameWithDiffDays = $this->getDueNameWithDiffInDays($invoiceDueDate, $this->aging_date);
            $dueName = array_key_first($dueNameWithDiffDays);
            $diffInDays = $dueNameWithDiffDays[$dueName];
            $dayInterval = $this->getDayInterval($diffInDays);
            if ($diffInDays == 0) {
                $dueName = 'current_due';
                $dayInterval = 0 ;
            }
            $result[$clientName][$dueName][$dayInterval] = isset($result[$clientName][$dueName][$dayInterval]) ? $result[$clientName][$dueName][$dayInterval] + $netBalance : $netBalance ;
            // if ($netBalance) {
                $result[$clientName][$dueName]['no_invoices'][$dayInterval] = isset($result[$clientName][$dueName]['no_invoices'][$dayInterval]) ? $result[$clientName][$dueName]['no_invoices'][$dayInterval] + 1 : 1 ;
            // }

            $result[$clientName][$dueName]['total'] = isset($result[$clientName][$dueName]['total']) ? $result[$clientName][$dueName]['total'] + $netBalance : $netBalance ;
            $result[$clientName]['total'] = isset($result[$clientName]['total']) ? $result[$clientName]['total'] + $netBalance : $netBalance;
            $result[$clientName]['invoices'][$invoiceNumber][$dueName][$dayInterval] = $netBalance;
            $result[$clientName]['invoices'][$invoiceNumber][$dueName]['total'] = isset($result[$clientName]['invoices'][$invoiceNumber][$dueName]['total']) ? $result[$clientName]['invoices'][$invoiceNumber][$dueName]['total'] + $netBalance : $netBalance;
            $result[$clientName]['invoices'][$invoiceNumber]['total'] = isset($result[$clientName]['invoices'][$invoiceNumber]['total']) ? $result[$clientName]['invoices'][$invoiceNumber]['total'] + $netBalance : $netBalance;
            $result['total'][$dueName][$dayInterval] = isset($result['total'][$dueName][$dayInterval]) ? $result['total'][$dueName][$dayInterval] + $netBalance : $netBalance ;
            // if ($netBalance) {
                $result['invoice_count'][$dueName][$dayInterval] = isset($result['invoice_count'][$dueName][$dayInterval]) ? $result['invoice_count'][$dueName][$dayInterval] + 1 : 1 ;
                $result['invoice_count'][$dueName]['clients'][$dayInterval][$clientName] = $clientName;
            // }
            $result['grand_total'] = isset($result['grand_total']) ? $result['grand_total'] + $netBalance : $netBalance ;
            // if ($netBalance) {
                $result['grand_clients_total'][$clientName] = $clientName ;
            // }
            $result['total_of_due'][$dueName] = isset($result['total_of_due'][$dueName]) ? $result['total_of_due'][$dueName] + $netBalance : $netBalance ;
			if($dueName == 'coming_due'){
				$result['total_per_date_for_coming'][$invoiceDueDate] =  isset($result['total_per_date_for_coming'][$invoiceDueDate]) ? $result['total_per_date_for_coming'][$invoiceDueDate] + $netBalance :$netBalance;  
			}
			if($dueName == 'current_due'){
				$result['total_per_date_for_current'][$invoiceDueDate] =  isset($result['total_per_date_for_current'][$invoiceDueDate]) ? $result['total_per_date_for_current'][$invoiceDueDate] + $netBalance :$netBalance;  
			}
            // if ($netBalance) {
                $result['total_clients_due'][$dueName][$clientName] = $clientName ;
            // }
        }

        foreach (['coming_due', 'current_due', 'past_due'] as $dueName) {
            $totalOfAllDue = isset($result['total_of_due']) ? array_sum($result['total_of_due']) : 0 ;
            $result['charts']['Total Aging Analysis Chart'][] = [
                'item' => $this->getTotalAgainItemNameFromDue($dueName, $this->aging_date),
                'value' => $totalAgingValue = $result['total_of_due'][$dueName] ?? 0,
                'percentage' => $totalAgingValue && $totalOfAllDue ? $totalAgingValue / $totalOfAllDue * 100 : 0,
                'total_for_all_values' =>$totalOfAllDue ,
                'total_for_all_percentages' =>  $totalOfAllDue > 0 ? '100' : 0
            ];
            if ($dueName == 'coming_due' || $dueName == 'past_due') {
                foreach (array_merge(getInvoiceDayIntervals(), [self::MORE_THAN_150]) as $dayInterval) {
                    $chartKeyName = null;
                    if ($dueName == 'past_due') {
                        $chartKeyName = 'Total Past Dues Aging Analysis Chart';
                    }
                    if ($dueName == 'coming_due') {
                        $chartKeyName = 'Total Coming Dues Aging Analysis Chart';
                    }
                    $totalOfAllValues = isset($result['total'][$dueName]) ? array_sum($result['total'][$dueName]) : 0 ;
					
                    $result['charts'][$chartKeyName][] = [
                        'item' => $dayInterval . ' ' . __('Days'),
                        'value' => $currentValue = $result['total'][$dueName][$dayInterval] ?? 0,
                        'percentage' => $currentValue && $totalOfAllValues ? $currentValue / $totalOfAllValues * 100 : 0,
                        'total_for_all_values' => $totalOfAllValues,
                        'total_for_all_percentages' => $totalOfAllValues > 0 ? 100 : 0
                    ];
                }
            }
        }
		
		$chequesResultForChart = $this->formatChart($chequesResultForChart);
        return [
			'result_for_table'=>$result ,
			'result_for_chart'=>$chequesResultForChart
		] ;
    }
	public function formatChart(array $chequesResultForChart){
		$result = [];
		ksort($chequesResultForChart);
		
		foreach($chequesResultForChart as $date=>$value){
			$result[] = [
				'date'=>$date,
				'value'=>$value
			];
		}
		return $result ;
	}

	
	public function formatForDashboard(array $agings,string $modelType)
	{

		$totalsArray = $agings['total'] ?? [] ;
		$formattedForTable = $totalsArray ;
		$formattedForChat = [];
		
		$dates = array_keys(array_merge($agings['total_per_date_for_current']??[] ,$agings['total_per_date_for_coming']??[] ));
		foreach(HArr::sumAtDates([$agings['total_per_date_for_coming']??[] , $agings['total_per_date_for_current'] ?? []],$dates) ?? [] as $date => $value){
			$formattedForChat[]  =['date'=>$date , 'value'=>$value];
		}
	
		return [
				'table'=>$formattedForTable,
				'chart'=>$formattedForChat
		];
	
		
	}

   
}
