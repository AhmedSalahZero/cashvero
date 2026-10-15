<?php

namespace App\Jobs;

use App\Models\CachingCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RemoveCachingCompaniesData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $companyId ; 
    private $modelName ; 
    
    public function __construct(int $companyId,string $modelName)
    {
        $this->companyId = $companyId ;
        $this->modelName = $modelName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
         CachingCompany::where('company_id' , $this->companyId)->where('model',$this->modelName)->get()->each(function($companyCache){
            Cache::forget($companyCache->key_name);
            $companyCache->delete();
            $key = getTotalUploadCacheKey($this->companyId , $companyCache->job_id , $this->modelName) ;
            Cache::forget($key);
        });
        
    }
}
