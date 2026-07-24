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
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('medium_term_loan_id')->index('loan_schedules_medium_term_loan_id_foreign');
            $table->date('date')->nullable();
            $table->decimal('beginning_balance', 14)->nullable()->default(0);
            $table->decimal('schedule_payment', 14)->nullable()->default(0);
            $table->decimal('interest_amount', 14)->nullable()->default(0);
            $table->decimal('principle_amount', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->nullable()->default(0);
            $table->decimal('remaining', 14)->default(0);
            $table->string('status')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_schedules');
    }
};
