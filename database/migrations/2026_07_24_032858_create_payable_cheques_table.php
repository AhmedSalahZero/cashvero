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
        Schema::create('payable_cheques', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('company_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('status')->default('pending');
            $table->integer('money_payment_id')->nullable()->index('payable_cheques_money_payment_id_foreign');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->integer('delivery_bank_id')->nullable()->index('payable_cheques_delivery_bank_id_foreign')->comment('هو البنك اللي انا طلعت منة الشيك للمورد وبالتالي لازم يكون من بنوكي');
            $table->string('account_type')->comment('نوع الحساب اللي هسحب منة الشيك علشان ادية للمورد');
            $table->string('account_number')->nullable()->comment('رقم الحساب اللي هسحب منة الشيك علشان ادية للمورد');
            $table->date('due_date')->nullable()->comment('هو تاريخ استحقاق الشيك .. يعني اقدر اسحبة امتة');
            $table->date('delivery_date')->nullable()->comment('هو تاريخ الي اديت فيه الشيك للمورد');
            $table->date('actual_payment_date')->nullable()->comment('هو تاريخ التسليم الفعلي لان لازم ياكد');
            $table->decimal('account_balance', 14)->default(0)->comment('دي اجمالي اللي معايا في الحساب بعد اما الشيك مثلا انسحب ودي احنا اللي بنجسبها افتراضيا');
            $table->timestamps();

            $table->index(['money_payment_id', 'status'], 'payable_chq_mp_status_idx');
            $table->index(['status', 'actual_payment_date'], 'payable_chq_status_actual_pay_idx');
            $table->index(['status', 'due_date'], 'payable_chq_status_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payable_cheques');
    }
};
