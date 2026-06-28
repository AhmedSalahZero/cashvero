<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class RemoveCompanyController extends Controller
{
    
    public function __invoke(Request $request)
    {
	
        $company_id = $request->get('company_id') ;
     
        $company = Company::where('id',$company_id)->firstOrFail();
		Artisan::call('delete:all',['company_id'=>$company_id]);
        $company->delete();
       return response()->json([
           'status'=>true 
       ]);

    }
}
