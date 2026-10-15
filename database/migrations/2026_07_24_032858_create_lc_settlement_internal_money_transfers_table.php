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
        Schema::create('lc_settlement_internal_money_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type')->nullable();
            $table->date('transfer_date')->nullable()->comment('هو التاريخ اللي اللي هيتم فيه العميله ');
            $table->unsignedInteger('transfer_days')->comment('عدد الايام المتوقع فيها اتمام هذه العمليه');
            $table->integer('from_bank_id')->nullable()->index('lc_settlement_internal_money_transfers_from_bank_id_foreign');
            $table->unsignedBigInteger('from_account_type_id')->nullable()->index('qqq2');
            $table->string('from_account_number')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedBigInteger('to_letter_of_credit_issuance_id')->nullable()->index('qqq3');
            $table->decimal('amount', 14)->default(0)->comment('مقدار مبلغ التحويل');
            $table->unsignedBigInteger('company_id')->index('lc_settlement_internal_money_transfers_company_id_foreign');
            $table->string('from_comment_ar')->nullable();
            $table->string('to_comment_ar')->nullable();
            $table->string('from_comment_en')->nullable();
            $table->string('to_comment_en')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->text('user_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lc_settlement_internal_money_transfers');
    }
};
