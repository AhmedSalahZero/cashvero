<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * عدد الايام اللي قبلها الجرس بينبه ان خطاب ضمان او تسهيل هيخلص
 * * (القيم الافتراضية زي NotificationSetting::*_EXPIRY_NOTIFICATION_DAYS)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->integer('lg_expiry_notification_days')->default(7);
            $table->integer('facility_expiry_notification_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['lg_expiry_notification_days', 'facility_expiry_notification_days']);
        });
    }
};
