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
        Schema::create('factoring_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('recourse_type');
            $table->date('factoring_date');
            $table->unsignedBigInteger('factoring_company_id')->index();
            $table->unsignedBigInteger('factoring_contract_id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('customer_invoice_id')->unique();
            $table->string('invoice_currency');
            $table->decimal('invoice_amount', 20);
            $table->decimal('factoring_percentage', 8, 4);
            $table->decimal('factoring_amount', 20);
            $table->decimal('contract_interest_rate', 8, 4);
            $table->unsignedInteger('diff_in_days');
            $table->decimal('factoring_interest_amount', 20);
            $table->decimal('other_charges', 20)->default(0);
            $table->decimal('received_amount', 20);
            $table->unsignedBigInteger('financial_institution_id');
            $table->unsignedBigInteger('account_type_id');
            $table->string('account_number');
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->boolean('is_settled')->default(false);
            $table->date('settled_at')->nullable();
            $table->boolean('is_difference_received')->default(false);
            $table->date('difference_received_date')->nullable();
            $table->decimal('difference_received_amount', 20)->nullable();
            $table->unsignedBigInteger('difference_financial_institution_id')->nullable();
            $table->unsignedBigInteger('difference_account_type_id')->nullable();
            $table->string('difference_account_number')->nullable();
            $table->boolean('is_collected')->default(false);
            $table->date('collected_at')->nullable();
            $table->date('collection_date')->nullable();
            $table->unsignedBigInteger('collection_financial_institution_id')->nullable();
            $table->unsignedBigInteger('collection_account_type_id')->nullable();
            $table->string('collection_account_number')->nullable();
            $table->decimal('collection_difference_amount', 20)->nullable();
            $table->boolean('is_rejected')->default(false);
            $table->date('rejected_at')->nullable();
            $table->date('rejection_date')->nullable();
            $table->unsignedBigInteger('rejection_financial_institution_id')->nullable();
            $table->unsignedBigInteger('rejection_account_type_id')->nullable();
            $table->string('rejection_account_number')->nullable();
            $table->decimal('uncollected_invoice_charges', 20)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'recourse_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factoring_transactions');
    }
};
