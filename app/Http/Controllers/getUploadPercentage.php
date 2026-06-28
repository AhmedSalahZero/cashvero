<?php

namespace App\Http\Controllers;

use App\Models\CachingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class getUploadPercentage extends Controller
{
	public function __invoke(Request $request, $companyId,string $modelName)
	{
		$totalCachedItems = 0;
		$currentPercentage = 0;
		$jobId = 0;
		CachingCompany::where('company_id', $companyId)->where('model',$modelName)->get()->each(function ($cachingCompany) use (&$totalCachedItems, &$jobId) {
			$caches = Cache::get($cachingCompany->key_name) ?: [];
			foreach ($caches as $cacheItem) {
				++$totalCachedItems;
			}
			$jobId = $cachingCompany->job_id ?? 0;
		});

		$currentUploadedNumber = cache::get(getTotalUploadCacheKey($companyId, $jobId,$modelName)) ?: 0;
		$currentPercentage =  $totalCachedItems ? $currentUploadedNumber / $totalCachedItems * 100 : 0;
		$cacheHasReloadKey = Cache::has(getCanReloadUploadPageCachingForCompany($companyId,$modelName));

		if ($cacheHasReloadKey) {
			cache::forget(getCanReloadUploadPageCachingForCompany($companyId,$modelName));
		}

		return response()->json([
			'totalCacheNo' => $totalCachedItems,
			'totalPercentage' => $currentPercentage,
			'company_id' => $companyId,
			'currentUploaded' => $currentUploadedNumber,
			'reloadPage' => $jobId == 0 || $cacheHasReloadKey || ($currentPercentage == 0 && CachingCompany::where('company_id', $companyId)->where('model',$modelName)->count() == 0)
		]);
	}
}
