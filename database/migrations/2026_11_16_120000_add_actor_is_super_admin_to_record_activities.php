<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_activities', function (Blueprint $table) {
            /**
             * Snapshot the actor's privilege at write time. Role assignments
             * are mutable and have no history, so the current role cannot
             * reliably classify an old audit entry.
             *
             * Existing rows deliberately remain null: the chosen legacy
             * policy is to leave their visibility unchanged.
             */
            $table->boolean('actor_is_super_admin')->nullable()->after('user_name');
        });
    }

    public function down(): void
    {
        Schema::table('record_activities', function (Blueprint $table) {
            $table->dropColumn('actor_is_super_admin');
        });
    }
};
