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
        Schema::create('td_renewal_date_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('time_of_deposit_id')->index('td_renewal_foreign');
            $table->string('expiry_date')->nullable()->comment('تاريخ الانتهاء هنحتاجه هنا علشان نجيب بيه ال start date القديمه');
            $table->string('renewal_date')->comment('تاريخ التجديد');
            $table->decimal('interest_rate', 8, 4);
            $table->integer('company_id');
            $table->timestamps();
            $table->decimal('interest_amount', 14, 5)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('td_renewal_date_histories');
    }
};
