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
        Schema::create('leasing_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('leasing_company_id');
            $table->string('status')->default('running');
            $table->string('name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency');
            $table->decimal('limit', 20)->default(0);
            $table->decimal('paid_amount', 20)->default(0);
            $table->decimal('outstanding_amount', 20)->default(0);
            $table->decimal('borrowing_rate', 8, 4)->default(0);
            $table->decimal('margin_rate', 8, 4)->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->string('installment_payment_interval')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'leasing_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leasing_contracts');
    }
};
