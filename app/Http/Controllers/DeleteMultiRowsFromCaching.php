<?php

namespace App\Http\Controllers;

use App\Models\CachingCompany;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DeleteMultiRowsFromCaching extends Controller
{
  
    public function __invoke(Company $company , Request $request,string $modelName)
    {
         $selectedRows = (array)$request->rows ;
		 $dateFrom = $request->get('delete_date_from',$request->get('delete_serial_from'));
		 $dateTo = $request->get('delete_date_to',$request->get('delete_serial_to'));
		 if(!empty($selectedRows)|| ($dateFrom && $dateTo))
		 {
			 $caches = CachingCompany::where('company_id' , $company->id )->where('model',$modelName)->get();
			
			 $caches->each(function($cache) use($selectedRows,$dateFrom,$dateTo) {
				 $reCache = false ; 
				 $cachesGroup = Cache::get($cache->key_name) ?: [] ;
				 foreach($cachesGroup as $index=>$cachesElement){
		
					 $found = $dateFrom && $dateTo ?  dateIsBetween($cachesElement['date'] , $dateFrom , $dateTo) : true ;
					 if(count($selectedRows)){
						 $found = in_array($cachesElement['id'] , $selectedRows);
						}else{
							$found = dateIsBetween($cachesElement['date'] , $dateFrom , $dateTo);
						}
               if($found)
               {
                   $reCache = true ;
                   unset($cachesGroup[$index]);
               }
           }
           if($reCache)
           {
               Cache::forget($cache->key_name);
               Cache::forever($cache->key_name , $cachesGroup );
           }
           
       });
	  
	   
	   if($request->ajax()){
		return response()->json([
			'status'=>true ,
		]);
	   }
       return redirect()->back()->with('success',__('Items Has Been Removed Successfully'));

   }
   return redirect()->back()->with('fail',__('No Selected Rows'));
    }
}
