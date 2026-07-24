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
        Schema::create('cash_projections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_contract')->default(false);
            $table->string('name')->nullable();
            $table->string('type');
            $table->longText('amounts')->nullable();
            $table->unsignedBigInteger('cashflow_report_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_projections');
    }
};
