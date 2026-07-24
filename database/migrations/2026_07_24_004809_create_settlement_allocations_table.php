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
        Schema::create('settlement_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->integer('money_payment_id')->nullable()->index('set_alloc_mp_idx');
            $table->unsignedBigInteger('letter_of_credit_issuance_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index('settlement_allocations_contract_id_foreign');
            $table->unsignedBigInteger('partner_id')->nullable()->index('settlement_allocations_partner_id_foreign');
            $table->decimal('allocation_amount', 14)->default(0);
            $table->timestamps();

            $table->index(['contract_id', 'money_payment_id'], 'set_alloc_contract_mp_idx');
            $table->index(['money_payment_id'], 'settlement_allocations_money_payment_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_allocations');
    }
};
