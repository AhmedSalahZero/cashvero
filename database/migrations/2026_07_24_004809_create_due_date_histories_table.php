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
        Schema::create('due_date_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('model_id')->index('due_date_histories_customer_invoice_id_foreign');
            $table->string('due_date')->comment('التاريخ اللي تم تاجيل الدفع ليه');
            $table->decimal('amount', 14)->comment('هي عباره عن القيمة المتبقه من الفاتورة خلال تاريخ هذا التاجيل بمعني انك لما اجلت الفاتورة كان متبقي عليك الف جنية مثلا تاني مره اجلتها كان باقي عليك500 مثلا');
            $table->integer('company_id');
            $table->timestamps();
            $table->string('model_type')->comment('وليكن مثلا CustomerInvoice , SupplierInvoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('due_date_histories');
    }
};
