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
        Schema::create('factoring_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('factoring_company_id');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('recourse_type');
            $table->string('currency')->nullable();
            $table->decimal('limit', 20)->default(0);
            $table->decimal('outstanding_balance', 20)->default(0);
            $table->date('balance_date')->nullable();
            $table->decimal('borrowing_rate', 8, 4)->nullable();
            $table->decimal('margin_rate', 8, 4)->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->decimal('min_interest_rate', 8, 4)->nullable();
            $table->decimal('highest_debt_balance_rate', 8, 4)->nullable();
            $table->decimal('admin_fees_rate', 8, 4)->nullable();
            $table->unsignedInteger('to_be_setteled_max_within_days')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'factoring_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factoring_contracts');
    }
};
