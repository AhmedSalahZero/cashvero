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
		// Full Blade notifications index removed with dashboard layout.
		// The Inertia top-nav bell uses notifications.detail (JSON) instead.
		return redirect()->route('view.customer.invoice.dashboard.cash', ['company' => $company->id]);
    }





	
	// public function destroy(Company $company , Notification $Notification)
	// {
	// 	$Notification->deleteRelations();
	// 	$Notification->delete();
	// 	return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	// }

	/**
	 * ✅ NEW — powers the top-nav notification panel's per-type table.
	 * Replicates resources/views/notifications/popup.blade.php exactly:
	 * filter this company's notifications by the granular subtype
	 * (data.type — NOT the coarser data.tap_type index() above uses),
	 * derive the table's columns dynamically from the first record's
	 * data.data_array keys (different notification types have
	 * different columns), and return everything as JSON so the
	 * sidebar can show it in a modal without leaving the current page —
	 * same UX as the original.
	 */
	public function detail(Company $company, string $type)
	{
		$items = $company->notifications->where('data.type', $type)->values();
		$first = $items->first();
		$dataArray = $first ? (is_array($first->data) ? $first->data : json_decode(json_encode($first->data), true)) : [];
		$headers = $first ? array_keys($dataArray['data_array'] ?? []) : [];

		return response()->json([
			'title' => \App\Notification::getAllMainTypes()[$type] ?? $type,
			'headers' => $headers,
			'rows' => $items->map(function ($notification) use ($headers) {
				$data = is_array($notification->data) ? $notification->data : json_decode(json_encode($notification->data), true);
				$row = $data['data_array'] ?? [];
				return collect($headers)->mapWithKeys(fn ($h) => [$h => $row[$h] ?? '---'])->all();
			})->values(),
		]);
	}
}
