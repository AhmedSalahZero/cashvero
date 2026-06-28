<?php

namespace App\Providers;

use App\Events\ImportFailedEvent;
use App\Listeners\ImportFailedListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
   
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
		'Illuminate\Auth\Events\Login' => [
			'App\Listeners\LogSuccessfulLogin',
		],
		'Illuminate\Auth\Events\Logout' => [
			'App\Listeners\LogSuccessfulLogout',
		],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
