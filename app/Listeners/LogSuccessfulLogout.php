<?php

namespace App\Listeners;

use App\Models\Log;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    /**
     * بيسجل سطر logout في logs. أي خطأ هنا ما يمنعش الخروج.
     */
    public function handle(Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        try {
            Log::storeSessionEvent('successLogout', $user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
