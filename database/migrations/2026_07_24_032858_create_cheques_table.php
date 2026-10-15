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
        Schema::create('cheques', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('status')->default('in-safe');
            $table->integer('money_received_id')->index('cheques_money_received_id_foreign');
            $table->integer('drawee_bank_id')->nullable()->index('cheques_drawee_bank_id_foreign')->comment('هو البنك اللي جالي منة الشيك من العميل فا مش شرط يكون من بنوكي');
            $table->integer('drawl_bank_id')->nullable()->index('cheques_drawl_bank_id_foreign')->comment('هو البنك اللي انا باخد الشيك واسحبة منة وبالتالي لازم يكون من بنوكي');
            $table->string('account_type')->comment('نوع الحساب اللي هينزلك عليه فلوس الشيك بعد اما تودية البنك');
            $table->string('account_number')->nullable()->comment('رقم الحساب اللي هينزلك عليه فلوس الشيك بعد اما تودية البنك');
            $table->date('due_date')->nullable()->comment('هو تاريخ استحقاق الشيك .. يعني اقدر اسحبة امتة');
            $table->date('deposit_date')->nullable()->comment('هو تاريخ ايداع الشيك في البنك.. يعني ممكن يكون تاريخ الاستحقاق بكرا بس هطيته في البنك النهاردا');
            $table->bigInteger('days_count')->default(0);
            $table->date('expected_collection_date')->nullable()->comment('هو تاريخ اللي متوقع ان البنك يحطلي فيه قيمة الشيك في حسابي');
            $table->date('actual_collection_date')->nullable()->comment('هو تاريخ اللي البنك حطلي فيه قيمة الشيك في حسابي بشكل فعلي لاني ممكن اتوقع في يوم بس فعليا البنك حطة في يوم تاني بس وجود اجازة مثلا في اليوم اللي انا توقعته');
            $table->integer('clearance_days')->nullable()->default(0);
            $table->decimal('account_balance', 14)->default(0)->comment('دي اجمالي اللي معايا في الحساب بعد اما الشيك مثلا انسحب ودي احنا اللي بنجسبها افتراضيا');
            $table->decimal('collection_fees', 14)->default(0)->comment('الرسوم اللي البنك بياخدها منك لتحصيل الشيك');
            $table->timestamps();
            $table->integer('company_id')->nullable();

            $table->index(['money_received_id', 'status'], 'cheques_mr_status_idx');
            $table->index(['status', 'actual_collection_date'], 'cheques_status_actual_coll_idx');
            $table->index(['status', 'expected_collection_date'], 'cheques_status_expected_coll_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
