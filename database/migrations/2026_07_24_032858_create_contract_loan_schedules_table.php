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
        Schema::create('contract_loan_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('leasing_contract_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->decimal('beginning_balance', 20)->nullable();
            $table->decimal('cheque_amount', 20)->nullable();
            $table->decimal('interest_amount', 20)->nullable();
            $table->decimal('principle_amount', 20)->nullable();
            $table->decimal('end_balance', 20)->nullable();
            $table->decimal('remaining', 20)->default(0);
            $table->string('status')->nullable();
            $table->string('cheque_number')->nullable();
            $table->unsignedBigInteger('drawee_bank_id')->nullable()->index();
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'leasing_contract_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_loan_schedules');
    }
};
