<?php

namespace App\Listeners;

use App\Models\Log;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * بيحدّث users.last_login_at وبيسجل سطر login في logs.
     * التحديث بيتعمل على الـ query مباشرة عشان updated_at بتاع
     * المستخدم ما يتغيرش. أي خطأ هنا ما يمنعش الدخول.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        try {
            User::whereKey($user->getKey())->toBase()->update(['last_login_at' => now()]);
            Log::storeSessionEvent('successLogin', $user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
