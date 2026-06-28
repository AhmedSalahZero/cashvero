<?php
namespace App\Http\Controllers;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Notification;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * * To View All Notifications [invoices for example]
 */
class NotificationsController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'created_at' ; // change it 
		// $dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');
		$collection = $collection
		->when($request->has('value'),function($collection) use ($value,$searchFieldName){
			return $collection->filter(function($moneyReceived) use ($value,$searchFieldName){
				$currentValue = $moneyReceived->{$searchFieldName} ;
				if($searchFieldName == 'bank_id'){
					$currentValue = $moneyReceived->getBankName() ;  
				}
				return false !== stristr($currentValue , $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('id')->values();
		
		return $collection;
	}
	public function index(Company $company,Request $request,string $currentType )
	{
		$models = [];
		$numberOfMonthsBetweenEndDateAndStartDate = 100 ;
		// $numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		// $currentType = $request->get('active',Notification::CUSTOMER);
		$searchFields = Notification::getSearchFieldsBasedOnTypes();
		$filterDates = [];
		$notificationTypes = Notification::getAllTypesFormatted() ;
		foreach($notificationTypes as $type => $detailsArray ){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
			
		// 	$bankToBankStartDate = $filterDates[$type]['startDate'] ?? null ;
		// $bankToBankEndDate = $filterDates[$type]['endDate'] ?? null ;
		$items = $company->getNotificationsBasedOnType($type) ;
	
		// $items =  $items->filterByCreatedAt($startDate,$endDate) ;
		// $items =  $currentType == $type ? $this->applyFilter($request,$items):$items ;

		$models[$type]  = $items;
	
		}
		

        return view('notifications.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'notificationTypes'=>$notificationTypes,
			'activeType'=>$currentType
		]);
    }





	
	// public function destroy(Company $company , Notification $Notification)
	// {
	// 	$Notification->deleteRelations();
	// 	$Notification->delete();
	// 	return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	// }
}
