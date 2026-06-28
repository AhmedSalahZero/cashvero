<?php

namespace App\Providers;

use App\Helpers\HArr;
use App\Helpers\HAuth;
use App\Http\Controllers\ExportTable;

use App\Models\Company;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpSpreadsheet\Shared\Font;
use Spatie\Permission\Models\Permission;
use stdClass;

class AppServiceProvider extends ServiceProvider
{
	
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	
	 
	public function register()
	{
	
	
		
	
		// if ($this->app->isLocal()) {
			// $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
		// }
		// 
	}
	
	
	public function boot()
	{	
		if ($this->app->environment('production')) {
			URL::forceScheme('https');
		}

		app()->singleton('currentCompany', fn () => resolveCompanyFromRequest());
		\PhpOffice\PhpSpreadsheet\Shared\Font::setAutoSizeMethod(Font::AUTOSIZE_METHOD_EXACT);
		require_once storage_path('dompdf/vendor/autoload.php');
		require_once app_path('Helpers/HArr.php');
		Collection::macro('formattedForSelect',function(bool $isFunction , string $idAttrOrFunction ,string $titleAttrOrFunction ){
			/**
			 * @var Collection $this 
			 */
			return $this->map(function($item) use ($isFunction , $idAttrOrFunction ,$titleAttrOrFunction ){
				return [
					'value' => $isFunction ? $item->$idAttrOrFunction() : $item->{$idAttrOrFunction} ,
					'title' => $isFunction ? $item->$titleAttrOrFunction() : $item->{$titleAttrOrFunction}
				];
			})->toArray();
		});
		
		
		
		Collection::macro('filterByReceivingDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('receiving_date','>=',Carbon::make($startDate)->startOfDay())->where('receiving_date','<=',Carbon::make($endDate)->endOfDay());
			})->sortByDesc('receiving_date') ;
		});
		
		
		Collection::macro('filterByStartDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('start_date','>=',Carbon::make($startDate)->startOfDay())->where('start_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});

		Collection::macro('filterByLoanEndDate',function(?string $endDate){
			/**
			 * @var Collection $this
			 */
			return $this->when($endDate ,function(Collection $items) use ($endDate){
				return $items->where('end_date','>=',Carbon::make($endDate)->startOfDay());
			}) ;
		});
		
		Collection::macro('filterByDateColumn',function(string $dateColumnName,?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate,$dateColumnName){
				return $items->where($dateColumnName,'>=',Carbon::make($startDate)->startOfDay())->where($dateColumnName,'<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByTransferDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('transfer_date','>=',Carbon::make($startDate)->startOfDay())->where('transfer_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByTransactionDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('transaction_date','>=',Carbon::make($startDate)->startOfDay())->where('transaction_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByCreatedAt',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('created_at','>=',Carbon::make($startDate)->startOfDay())->where('created_at','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByDeliveryDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('delivery_date','>=',Carbon::make($startDate)->startOfDay())->where('delivery_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByPaymentDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('payment_date','>=',Carbon::make($startDate)->startOfDay())->where('payment_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		Collection::macro('filterByIssuanceDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('issuance_date','>=',Carbon::make($startDate)->startOfDay())->where('issuance_date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
	
		Collection::macro('filterByDate',function(?string $startDate, ?string $endDate  ){
			/**
			 * @var Collection $this 
			 */
			return $this->when($startDate && $endDate ,function(Collection $items) use ($startDate,$endDate){
				return $items->where('date','>=',Carbon::make($startDate)->startOfDay())->where('date','<=',Carbon::make($endDate)->endOfDay());
			}) ;
		});
		
		
	
		

		$Language = new stdClass();
		$Language->id = 2;
		$Language->name = 'Arabic';
		$Language->code = 'ar';
		$Language->create_at = Carbon::make('2021-05-27 09:04:17');
		$Language2 = new stdClass();
		$Language2->id = 1;
		$Language2->name = 'English';
		$Language2->code = 'en';
		$Language2->create_at = Carbon::make('2021-05-27 09:04:17');

		$languages = collect([
			$Language2,
			$Language
		]);
		
		
		
		// if(false ){
		// 	app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
		// 	app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
		// 	$permissions = HAuth::getPermissions();
		// 	foreach($permissions as $permissionArr){
		// 		try{
		// 			 Permission::findByName($permissionArr['name']);
		// 		}
		// 		catch(\Exception $e){
				
		// 			$permission = Permission::create([
		// 				'name'=>$permissionArr['name']
		// 			]);

		// 			foreach(User::all() as $user){
		// 				/**
		// 				 * @var User $user;
		// 				 */
						
		// 				$user->assignNewPermission($permissionArr,$permission);
						
		// 			}
		// 		}
		// 	}	
		// }
		

		View::share('langs', $languages);
		// View::share('langs',Language::all());
		View::share('lang', app()->getLocale());
		$currentCompany = null ;
		try {
			$currentCompany = Company::find(Request()->segment(2));
		}
		catch(\Exception $e){
			
		}
	
		if ($currentCompany) {
			$excelType ='CustomerInvoice';
			if(in_array('uploading',Request()->segments())){
				$excelType = Request()->segment(4);
			}
			if($excelType){	
				View::share('exportables', (new ExportTable)->customizedTableField($currentCompany, $excelType, 'selected_fields'));
			}
			View::share('company', $currentCompany);
		}

		View::composer('*', function ($view) {

			$requestData = Request()->all();
			if (isset($requestData['start_date']) && isset($requestData['end_date'])) {
				$view->with([
					'start_date' => $requestData['start_date'],
					'end_date' => $requestData['end_date'],
				]);
			} elseif (isset($requestData['date'])) {
				$view->with([
					'date' => $requestData['date']
				]);
			}
		});
		
	}
	
	




	



	// }
}
