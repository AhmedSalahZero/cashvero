<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
		Schema::drop('tool_tip_data');
        DB::table('sections')->where('route','toolTipData.index')->delete();
		Artisan::call('optimize:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
