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
        Schema::create('outgoing_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('money_payment_id')->nullable()->index('outgoing_transfers_money_payment_id_foreign');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->boolean('is_bank_charges')->default(false);
            $table->integer('delivery_bank_id')->nullable()->index('outgoing_transfers_delivery_bank_id_foreign');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->date('actual_payment_date')->nullable()->comment('هو تاريخ التحويل الفعلي لان لازم ياكد');
            $table->string('status')->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_transfers');
    }
};
