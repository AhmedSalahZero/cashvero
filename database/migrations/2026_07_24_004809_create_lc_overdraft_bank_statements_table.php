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
        Schema::create('lc_overdraft_bank_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source');
            $table->string('type')->comment('وليكن مثلا beginning_balance,incoming_transfer,cheque_payment  , etc');
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            $table->integer('priority')->default(3)->comment('عباره عن اولويه التسديد بمعني لما يحين وقت التسديد مين هيتسدد الاول لان الفؤائد بتسدد الاول');
            $table->unsignedBigInteger('lc_facility_id')->nullable();
            $table->unsignedInteger('lc_issuance_id');
            $table->unsignedBigInteger('lc_settlement_internal_money_transfer_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->decimal('limit', 14)->default(0);
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->decimal('room', 14)->default(0);
            $table->enum('interest_type', ['normal', 'end_of_month'])->default('normal')->comment('الفايدة اما بتنزل بعد كل سحبة او ايداع او بتنزل بشكل اوتوماتك اخر كل شهر');
            $table->decimal('interest_rate_annually', 8, 5)->default(0);
            $table->decimal('interest_rate_daily', 8, 5)->default(0);
            $table->integer('days_count')->default(0);
            $table->decimal('interest_amount', 14)->default(0);
            $table->timestamps();
            $table->dateTime('full_date')->nullable()->comment('دا هنستخدمة علشان نرتب بيه ونجيب ال الرو السابق بناء علي التاريخ و الوقت');
            $table->date('outstanding_withdrawal_date')->nullable();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lc_overdraft_bank_statements');
    }
};
