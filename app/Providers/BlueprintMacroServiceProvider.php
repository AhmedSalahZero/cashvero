<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Str;

class BlueprintMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
		
		Blueprint::macro('studyFields', function () {
			/**
			 * @var Blueprint $this
			 */
			$this->unsignedBigInteger('study_id');
			$time = now()->toTimeString();
			$key = Str::uuid();
			$fullKey = $time.$key;
			$this->unsignedBigInteger('company_id');
		});
    }
}
