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
        Schema::create('contract_loan_schedule_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('contract_loan_schedule_id');
            $table->string('current_account_number');
            $table->date('date');
            $table->decimal('amount', 20);
            $table->timestamps();

            $table->index(['company_id', 'contract_loan_schedule_id'], 'cls_settlements_company_schedule_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_loan_schedule_settlements');
    }
};
