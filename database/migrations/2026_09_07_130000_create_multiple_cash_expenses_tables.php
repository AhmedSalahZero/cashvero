<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * "المصروفات النقدية المتعددة" — نسخة مستقلة تمامًا عن المصروفات
 * * النقدية العادية ، بتسمح بصرف أكتر من بند في حركة واحدة
 *
 * * ليه موديل منفصل مش تعديل على الموجود : المصروفات النقدية الحالية
 * * شغالة و مربوطة بأودو و بحركات مالية حقيقية ، فأي تعديل عليها خطر .
 * * دي جنبها ، و ملهاش تكامل مع أودو حاليًا
 *
 * * الحاجة المهمة في التصميم : كل بند في الحركة بينزل **سطر مستقل** في
 * * كشف الحساب (الخزنة / البنك) بمبلغه ، مش سطر واحد بالإجمالي — عشان
 * * كده البند نفسه (item) هو اللي بيملك سطر الكشف ، مش الحركة الأم
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * * الحركة الأم : التاريخ ، طريقة الدفع ، الحساب/الخزنة ،
         * * العملة ، و الإجمالي المحسوب من البنود
         */
        Schema::create('multiple_cash_expenses', function (Blueprint $table) {
            $table->id();

            $table->string('type')->nullable();
            $table->date('payment_date')->nullable();

            /**
             * * الإجمالي : مجموع بنود الـ repeater ، و هو اللي بيتصرف فعلا
             * * من الخزنة أو البنك
             */
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->double('amount_in_paying_currency')->nullable();
            $table->string('currency')->nullable();
            $table->double('exchange_rate')->nullable();

            /**
             * * تفاصيل الدفع متخزنة هنا مباشرةً بدل جداول منفصلة
             * * (cash_payments / payable_cheques / outgoing_transfers) عشان
             * * الموديل ده يفضل مستقل و ما يلمسش جداول المصروفات الحالية
             */
            $table->unsignedBigInteger('delivery_bank_id')->nullable();
            $table->unsignedBigInteger('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('delivery_branch_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('cheque_number')->nullable();
            $table->date('due_date')->nullable();

            $table->text('user_comment')->nullable();
            $table->string('comment_ar')->nullable();
            $table->string('comment_en')->nullable();

            $table->boolean('is_reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('company_id');

            $table->timestamps();

            $table->index(['company_id', 'payment_date']);
        });

        /**
         * * بنود الـ repeater — كل بند مصروف قائم بذاته
         */
        Schema::create('multiple_cash_expense_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('multiple_cash_expense_id');
            $table->foreign('multiple_cash_expense_id', 'mce_items_parent_fk')
                ->references('id')->on('multiple_cash_expenses')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('cash_expense_category_name_id')->nullable();
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->unsignedBigInteger('company_id');

            $table->timestamps();

            $table->index(['company_id']);
        });

        /**
         * * التوزيع على العقود — مربوط بالبند نفسه مش بالحركة كلها ، عشان
         * * المستخدم يعرف التوزيع ده بتاع أنهي مصروف بالظبط
         */
        Schema::create('multiple_cash_expense_allocations', function (Blueprint $table) {
            $table->id();

            /**
             * * الاسم التلقائي للمفتاح الأجنبي هنا بيعدّي حد الـ 64 حرف
             * * بتاع MySQL ، فبنسميه بإيدنا
             */
            $table->unsignedBigInteger('multiple_cash_expense_item_id');
            $table->foreign('multiple_cash_expense_item_id', 'mce_allocations_item_fk')
                ->references('id')->on('multiple_cash_expense_items')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('contract_id');
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedBigInteger('company_id');

            $table->timestamps();

            $table->index(['company_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiple_cash_expense_allocations');
        Schema::dropIfExists('multiple_cash_expense_items');
        Schema::dropIfExists('multiple_cash_expenses');
    }
};
