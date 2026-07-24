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
        Schema::create('medium_term_loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('financial_institution_id');
            $table->string('status')->default('running');
            $table->string('name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency');
            $table->decimal('limit', 14)->default(0);
            $table->decimal('paid_amount', 14)->default(0);
            $table->decimal('outstanding_amount', 14)->default(0);
            $table->string('account_number')->nullable();
            $table->decimal('borrowing_rate', 14)->default(0);
            $table->decimal('margin_rate', 14)->default(0);
            $table->unsignedBigInteger('duration')->nullable()->comment('tenor (duration in months) ');
            $table->string('installment_payment_interval');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medium_term_loans');
    }
};
