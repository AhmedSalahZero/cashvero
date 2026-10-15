<?php

namespace App\Listeners;

use App\Jobs\SyncOdooPaymentMethodIdsJob;
use Illuminate\Support\Facades\Log;

/**
 * * بتبعت الجوب اللي بتصلح اعمدة الـ payment method بتاعت اودو بعد اللوجين
 * * كل حاجة في try/catch علشان اي مشكلة في الكيو متمنعش المستخدم من الدخول
 */
class SyncOdooPaymentMethodIdsOnLogin
{
    public function handle($event): void
    {
        try {
            if (! $event->user) {
                return ;
            }

            SyncOdooPaymentMethodIdsJob::dispatch($event->user->id);
        } catch (\Throwable $exception) {
            Log::warning('SyncOdooPaymentMethodIdsOnLogin failed : ' . $exception->getMessage());
        }
    }
}
