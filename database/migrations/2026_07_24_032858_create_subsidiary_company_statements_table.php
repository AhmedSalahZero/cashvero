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
        Schema::create('subsidiary_company_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('currency_name');
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            $table->date('date')->nullable();
            $table->unsignedBigInteger('partner_id')->index('subsidiary_company_statements_partner_id_foreign');
            $table->unsignedBigInteger('money_received_id')->nullable();
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->timestamps();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subsidiary_company_statements');
    }
};
