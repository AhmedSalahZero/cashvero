<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashflow_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_contract')->default(false)->comment('is contract cash flow (1) or is cash flow (0)');
            $table->string('report_name')->nullable();
            $table->string('report_interval')->comment('monthly,  weekly ..etc');
            $table->string('start_date');
            $table->string('end_date');
            $table->longText('report_data');
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashflow_reports');
    }
};
