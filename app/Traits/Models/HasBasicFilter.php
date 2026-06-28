<?php 
namespace App\Traits\Models;


use App\Models\Cheque;
use App\Models\MoneyReceived;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait HasBasicFilter 
{
	/**
	 * Detail row for “cheque-*” money-received tabs lives on the `cheque` relation,
	 * not on names like chequeUnderCollection / chequeRejected (see dashesToCamelCase).
	 */
	protected function moneyReceivedDetailRelationName(?string $activeTab): string
	{
		if ($activeTab !== null && str_starts_with($activeTab, 'cheque')) {
			return 'cheque';
		}

		return dashesToCamelCase((string) $activeTab);
	}

	protected function normalizeDateForCompare($date): ?string
	{
		if ($date === null || $date === '') {
			return null;
		}
		if ($date instanceof Carbon) {
			return $date->format('Y-m-d');
		}

		return Carbon::parse((string) $date)->format('Y-m-d');
	}

	protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName = $searchFieldName === 'due_date' ? 'due_date' : 'receiving_date'; 
		if($searchFieldName =='deposit_date'){
			$dateFieldName = 'deposit_date';
		}
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');
		$collection = $collection
		->when($request->has('value'),function($collection) use ($value,$searchFieldName){
			return $collection->filter(function($moneyReceived) use ($value,$searchFieldName){
				$currentValue = $moneyReceived->{$searchFieldName} ;
				$moneyReceivedRelationName = $this->moneyReceivedDetailRelationName(Request('active')) ;
				$relationRecord = $moneyReceived->{$moneyReceivedRelationName} ;
				/**
				 * * بمعني لو مالقناش القيمة في جدول ال
				 * * moneyReceived
				 * * هندور عليها في العلاقه 
				 */
				$currentValue = is_null($currentValue) && $relationRecord ? $relationRecord->{$searchFieldName}  :$currentValue ;
				if ($searchFieldName === 'partner_name') {
					$currentValue = $moneyReceived->getCustomerName();
				}
				if ($searchFieldName === 'drawl_bank_name' && $relationRecord instanceof Cheque) {
					$currentValue = $relationRecord->getDrawlBankName();
				}
				if($searchFieldName == 'receiving_branch_id'){
					$currentValue = $moneyReceived->getCashInSafeBranchName() ;  
				}
				if($searchFieldName == 'receiving_bank_id'){
					$currentValue = $moneyReceived->getReceivingBankName() ;  
				}
				if($searchFieldName == 'drawee_bank_id'){
					$currentValue = $moneyReceived->getDraweeBankName() ;  
				}
				if(is_null($value)){
					return true;
				}
				$haystack = $currentValue === null || $currentValue === '' ? '' : (string) $currentValue;

				return $haystack !== '' && false !== stristr($haystack, $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			if ($dateFieldName === 'deposit_date') {
				return $collection->filter(function ($moneyReceived) use ($from) {
					$cheque = $moneyReceived instanceof MoneyReceived ? $moneyReceived->cheque : null;
					$d = $this->normalizeDateForCompare(optional($cheque)->deposit_date);

					return $d !== null && $d >= $from;
				});
			}
			if ($dateFieldName === 'due_date') {
				return $collection->filter(function ($moneyReceived) use ($from) {
					$cheque = $moneyReceived instanceof MoneyReceived ? $moneyReceived->cheque : null;
					$d = $this->normalizeDateForCompare(optional($cheque)->due_date);

					return $d !== null && $d >= $from;
				});
			}

			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			if ($dateFieldName === 'deposit_date') {
				return $collection->filter(function ($moneyReceived) use ($to) {
					$cheque = $moneyReceived instanceof MoneyReceived ? $moneyReceived->cheque : null;
					$d = $this->normalizeDateForCompare(optional($cheque)->deposit_date);

					return $d !== null && $d <= $to;
				});
			}
			if ($dateFieldName === 'due_date') {
				return $collection->filter(function ($moneyReceived) use ($to) {
					$cheque = $moneyReceived instanceof MoneyReceived ? $moneyReceived->cheque : null;
					$d = $this->normalizeDateForCompare(optional($cheque)->due_date);

					return $d !== null && $d <= $to;
				});
			}

			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('receiving_date');
		
		return $collection;
	}
	
}
