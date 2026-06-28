<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ShowCompletedMessageForSuccessJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	private  $companyId,$modelName;

	public function __construct($companyId,$modelName)
	{
		$this->companyId = $companyId;
	
		$this->modelName = $modelName;
	}

	public function handle()
	{

		Cache::forever(\getShowCompletedTestMessageCacheKey($this->companyId,$this->modelName), 1);
	}
}
