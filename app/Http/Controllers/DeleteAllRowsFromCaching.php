<?php

namespace App\Http\Controllers;

use App\Models\CachingCompany;
use App\Models\Company;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DeleteAllRowsFromCaching extends Controller
{
	
	public function __invoke(Request $request, Company $company,$modelName)
	{
		
		CachingCompany::where('company_id', $company->id)->where('model',$modelName)->get()->each(function ($companyCache) {
			Cache::forget($companyCache->key_name);
			$companyCache->delete();
		});
		Cache::forget(getShowCompletedTestMessageCacheKey($company->id,$modelName));
		return redirect()->back()->with('success', __('All Data Has Been Removed'));
	}
}
