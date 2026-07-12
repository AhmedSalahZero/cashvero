<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leasing_companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('branch_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('leasing_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('leasing_company_id');
            $table->string('status')->default('running');
            $table->string('name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency');
            $table->decimal('limit', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('outstanding_amount', 20, 2)->default(0);
            $table->decimal('borrowing_rate', 8, 4)->default(0);
            $table->decimal('margin_rate', 8, 4)->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->string('installment_payment_interval')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'leasing_company_id']);
        });

        Schema::create('contract_loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leasing_contract_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->decimal('beginning_balance', 20, 2)->nullable();
            $table->decimal('cheque_amount', 20, 2)->nullable();
            $table->decimal('interest_amount', 20, 2)->nullable();
            $table->decimal('principle_amount', 20, 2)->nullable();
            $table->decimal('end_balance', 20, 2)->nullable();
            $table->decimal('remaining', 20, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('cheque_number')->nullable();
            $table->unsignedBigInteger('drawee_bank_id')->nullable();
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'leasing_contract_id']);
            $table->index('drawee_bank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_loan_schedules');
        Schema::dropIfExists('leasing_contracts');
        Schema::dropIfExists('leasing_companies');
    }
};
