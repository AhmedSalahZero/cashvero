<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كل صفحة CashVero بقت بتتسجل في logs، و storeNewLogRecord بيدور على
 * آخر سجل للمستخدم في الشركة مع كل زيارة. الجدول ما كانش عليه أي index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('logs', ['user_id', 'company_id'])) {
            return;
        }

        Schema::table('logs', function (Blueprint $table) {
            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('logs', ['user_id', 'company_id'])) {
            return;
        }

        Schema::table('logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'company_id']);
        });
    }
};
